<?php

/**
 * Ported public API from edu2/services/ClassService.php (10eng §4).
 * Supporting private methods preserved for parity with edu2.
 */

namespace App\Services;

use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Models\EduUser;
use App\Models\WpTermTaxonomy;
use App\Models\WpUser;
use App\Services\Common\ArrayServiceCommon;
use App\Services\Common\AttendanceQueryService;
use App\Services\Common\ClassesServiceCommon;
use App\Services\Common\DateDayWeekService;
use App\Services\Common\JsonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ClassService
{
    /** @var array<int, array> Same-request user cache (edu2 userCache) */
    private array $userCache = [];

    /** @var array<int, array>|null All classes keyed by class_id (get_edu_class static) */
    private ?array $allClassesById = null;

    public function __construct(
        private Request $request,
        private JsonService $jsonService,
        private ArrayServiceCommon $arrayServiceCommon,
        private DateDayWeekService $dateDayWeekService,
        private ClassesServiceCommon $classesServiceCommon,
        private AttendanceQueryService $attendanceQueryService,
    ) {}

    /**
     * Mirrors edu2 RequestService::generate_url for student links used in processClasses.
     */
    private function generateUrlLegacy(string $route): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($script !== '') {
            $base = rtrim(dirname($script), '/\\');

            return $base . '/index.php?route=' . $route;
        }

        return rtrim(config('app.url'), '/') . '/index.php?route=' . $route;
    }

    /**
     * @return array{0: list<array>, 1: array<int|string, array>}
     */
    public function getClasses($district_id, $district_id2, $lv3): array
    {
        $where = [];

        if ($district_id2) {
            $where['district_id'] = $district_id2;
        } elseif ($district_id) {
            $dist_id = [];
            $terms = $this->getTermTaxonomyByDistrictId((int) $district_id);
            foreach ($terms as $value) {
                if (str_contains($value['name'], '游泳班')) {
                    $dist_id[] = $value['term_id'];
                }
            }
            $where['district_id'] = $dist_id;
        }

        // Single DB call — fetch all classes matching district filters only
        $all_classes = $this->findClassesWhere($where);

        // Derive unique lv3 list from the full result
        $seen_lv3 = [];
        $unique_classes_lv3 = [];

        foreach ($all_classes as $class) {
            if (! in_array($class['lv3'], $seen_lv3)) {
                $seen_lv3[] = $class['lv3'];
                $unique_classes_lv3[] = $class;
            }
        }

        // Filter in PHP instead of a second query
        $classes = $all_classes;
        if ($lv3) {
            $classes = array_filter(
                $all_classes,
                fn($class) => str_contains($class['class_name'], $lv3)
            );
        }

        $classes = $this->arrayServiceCommon->arrlist_change_key($classes, 'class_id');

        return [$unique_classes_lv3, $classes];
    }

    /**
     * @param  list<array>  $classes
     * @return list<array>
     */
    public function getUniqueLv3Classes($classes): array
    {
        if (empty($classes) || ! is_array($classes)) {
            return [];
        }

        $unique = [];
        $seen = [];

        foreach ($classes as $class) {
            if (! isset($class['lv3']) || empty($class['lv3'])) {
                continue;
            }

            if (! isset($seen[$class['lv3']])) {
                $unique[] = $class;
                $seen[$class['lv3']] = true;
            }
        }

        return $unique;
    }

    /**
     * @param  array<int|string, array>  $classes  classesMap keyed by class_id
     * @return array{0: array<int|string, array>, 1: list<array>}
     */
    public function getClassesUserData($classes, $get_user_id): array
    {
        if ($get_user_id) {
            $needle = '"' . $get_user_id . '"';
            $classes_user = EduClassUser::query()
                ->whereAnyRoleJsonLike($needle)
                ->get()
                ->map(fn(EduClassUser $m) => $m->toArray())
                ->all();
        } else {
            $classes_user = EduClassUser::query()
                ->get()
                ->map(fn(EduClassUser $m) => $m->toArray())
                ->all();
        }

        foreach ($classes_user as $value) {
            if (! isset($classes[$value['class_id']])) {
                continue;
            }
            if (! is_array($classes[$value['class_id']]['date_month'])) {
                $classes[$value['class_id']]['date_month'] = [];
            }
            $classes[$value['class_id']]['date_month'][] = $value['month'];
        }

        return [$classes, $classes_user];
    }

    /**
     * @param  array<int|string, array>  $classes
     * @param  list<array>  $classes_user
     * @return array{0: array<int|string, array>, 1: int, 2: int}
     */
    public function processAllClasses($classes, $classes_user, $targetMonthYm = null): array
    {
        $no_teacher_num = 0;
        $no_days_num = 0;

        $normalizedMonth = $this->resolveMonthYm($targetMonthYm);
        $monthTimestamp = strtotime($normalizedMonth . '-01');
        $class_year = date('Y', $monthTimestamp);
        $targetMonthNumber = date('n', $monthTimestamp);

        $pending = [];
        foreach ($classes as $key => $value) {
            $months = is_array($value['date_month']) ? $value['date_month'] : [];
            $month = '';

            foreach ($months as $_month) {
                if ($this->dateDayWeekService->is_current_month($_month, $targetMonthNumber)) {
                    $month = $_month;
                    break;
                }
            }

            $month = $month ?: 'x';
            $_user = $this->arrayServiceCommon->arrlist_search_one($classes_user, ['class_id' => $value['class_id'], 'month' => $month, 'class_year' => $class_year]);

            if (empty($_user)) {
                unset($classes[$key]);
                continue;
            }

            $pending[] = ['key' => $key, 'value' => $value, '_user' => $_user, 'month' => $month];
        }

        $usersList = array_column($pending, '_user');
        $summaries = $this->attendanceQueryService->buildClassDailySummariesBatch($usersList);

        foreach ($pending as $i => $row) {
            $key = $row['key'];
            $value = $row['value'];
            $_user = $row['_user'];
            $month = $row['month'];

            $value['student'] = $this->decodeJsonIdList($_user['student'] ?? []);
            $value['student_transfer'] = $this->decodeJsonIdList($_user['student_transfer'] ?? []);
            $value['teacher'] = $this->decodeJsonIdList($_user['teacher'] ?? []);
            $value['sort'] = empty($value['student']) ? 1 : 0;
            $value['days'] = empty($_user['days']) ? $this->dateDayWeekService->get_days($value['date_time'], $month) : $_user['days'];
            $value['class_every_day'] = $this->format_class_every_day_attend_from_summary($summaries[$i] ?? [], $_user);

            $value['style'] = (! empty($value['student']) && empty($value['teacher'])) ? 'red' : '';
            $value['style2'] = (str_contains($value['class_every_day'], '<span red>')) ? 'blue' : '';

            $value['month'] = $month;
            $value['class_year'] = $class_year;

            if ($value['style']) {
                $no_teacher_num++;
            }
            if ($value['style2']) {
                $no_days_num++;
            }

            $classes[$key] = $value;
        }

        $classes = $this->classesServiceCommon->classes_sort($classes);

        return [$classes, $no_teacher_num, $no_days_num];
    }

    /**
     * @param  list<array>  $classes
     * @return list<array>
     */
    public function processClasses($classes, $targetMonthYm = null): array
    {
        $processedClasses = [];

        $normalizedMonth = $this->resolveMonthYm($targetMonthYm);
        $monthTimestamp = strtotime($normalizedMonth . '-01');
        $currentMonth = date('n', $monthTimestamp) . '月';
        $currentMonthParam = $this->request->query('m', date('Y-m'));
        $currentYear = date('Y');
        $student_base_url = $this->generateUrlLegacy('student');

        $all_user_ids = [];
        foreach ($classes as $class) {
            $all_user_ids = array_merge(
                $all_user_ids,
                $class['teacher'] ?? [],
                $class['student'] ?? [],
                $class['student_transfer'] ?? []
            );
        }
        $all_user_ids = array_unique($all_user_ids);

        $users_by_id = $this->getUsersByIds($all_user_ids);

        foreach ($classes as $class) {
            $teacher = $this->getUsersFromCache($class['teacher'] ?? [], $users_by_id);
            $student = $this->getUsersFromCache($class['student'] ?? [], $users_by_id);
            $student_transfer = $this->getUsersFromCache($class['student_transfer'] ?? [], $users_by_id);

            $teacher_txt = $this->formatUserListText($teacher, 'teacher', $currentMonthParam, $currentYear);
            $student_txt = $this->formatUserListText($student, 'student', null, null, $student_base_url);
            $student_transfer_txt = $this->formatUserListText($student_transfer, 'student_transfer', null, null, $student_base_url);

            $date_month = $class['date_month'] ?? [];
            if (! is_array($date_month)) {
                $date_month = [];
            }
            $last_month = empty($date_month) ? '' : end($date_month);
            $class_month = in_array($currentMonth, $date_month)
                ? $currentMonth
                : ($last_month ?: '');

            $processedClasses[] = [
                'style' => $class['style'],
                'style2' => $class['style2'],
                'class_id' => $class['class_id'],
                'class_name' => $class['class_name'],
                'class_every_day' => $class['class_every_day'],
                'teacher_txt' => $teacher_txt,
                'student_txt' => $student_txt,
                'student_transfer_txt' => $student_transfer_txt,
                'class_month' => $class_month,
            ];
        }

        return $processedClasses;
    }

    /**
     * @return list<string> Same shape as edu2 DateDayWeekService::get_prev_next_month
     */
    public function getPreAndNextMonth(): array
    {
        return $this->dateDayWeekService->get_prev_next_month(
            $this->request->query('m', date('Y-m'))
        );
    }

    // -------------------------------------------------------------------------
    // Private — ported from edu2 ClassService
    // -------------------------------------------------------------------------

    private function getUsersByIds(array $user_ids): array
    {
        if (empty($user_ids)) {
            return [];
        }

        $missing_ids = [];
        $cached_users = [];

        foreach ($user_ids as $user_id) {
            if (isset($this->userCache[$user_id])) {
                $cached_users[$user_id] = $this->userCache[$user_id];
            } else {
                $missing_ids[] = $user_id;
            }
        }

        if (! empty($missing_ids)) {
            $new_users = $this->fetchWpUsersMerged($missing_ids);
            foreach ($new_users as $user) {
                $this->userCache[$user['ID']] = $user;
                $cached_users[$user['ID']] = $user;
            }
        }

        return $cached_users;
    }

    private function getUsersFromCache(array $user_ids, array $users_by_id): array
    {
        if (empty($user_ids) || ! is_array($user_ids)) {
            return [];
        }

        $result = [];
        foreach ($user_ids as $user_id) {
            if (isset($users_by_id[$user_id])) {
                $result[] = $users_by_id[$user_id];
            }
        }

        return $result;
    }

    private function buildUserDisplayName(array $user): string
    {
        $billing_first_name = empty($user['billing_first_name'])
            ? ($user['first_name'] ?? '')
            : $user['billing_first_name'];
        $billing_last_name = empty($user['billing_last_name'])
            ? ($user['last_name'] ?? '')
            : $user['billing_last_name'];

        return $billing_last_name . $billing_first_name;
    }

    private function formatUserListText(array $users, string $type = 'student', $month = null, $year = null, ?string $student_base_url = null): string
    {
        if (empty($users)) {
            return '';
        }

        if ($type === 'teacher' && $month === null) {
            $month = $this->request->query('m', date('Y-m'));
        }
        if ($type === 'teacher' && $year === null) {
            $year = date('Y');
        }
        if (($type === 'student' || $type === 'student_transfer') && $student_base_url === null) {
            $student_base_url = $this->generateUrlLegacy('student');
        }

        $text = '';

        foreach ($users as $user) {
            $name = $this->buildUserDisplayName($user);

            if ($type === 'teacher') {
                $text .= '<a teacher href="index.php?route=teacher&user_id=' . $user['ID']
                    . '&month=' . $month . '&year=' . $year . '">' . $name . '</a>';
            } else {
                $text .= '<a href="' . $student_base_url . '&user_id=' . $user['ID'] . '">' . $name . '</a>';
            }
        }

        return $text;
    }

    private function resolveMonthYm($monthYm = null): string
    {
        $candidate = $monthYm ?: $this->request->query('m', date('Y-m'));
        $candidate = trim((string) $candidate);

        if (! preg_match('/^\d{4}-\d{2}$/', $candidate)) {
            $timestamp = strtotime($candidate);
            if ($timestamp === false) {
                $timestamp = strtotime(date('Y-m'));
            }
            $candidate = date('Y-m', $timestamp);
        }

        return $candidate;
    }

    private function get_class_every_day_attend(array $class_user): string
    {
        $summary = $this->attendanceQueryService->buildClassDailySummary($class_user);

        return $this->format_class_every_day_attend_from_summary($summary, $class_user);
    }

    private function format_class_every_day_attend_from_summary(array $summary, array $class_user): string
    {
        if (empty($summary)) {
            return '';
        }

        $class_id = $class_user['class_id'];
        $class_name = $this->get_edu_class($class_id, 'class_name');
        $max = (str_contains($class_name, '幼兒')) ? 4 : 6;

        $analytisc_days_out = '';
        foreach ($summary as $value) {
            $date = $value['date'];
            $count = $value['count'];
            $style = '';
            if ($count > $max) {
                $style = 'red';
            }
            $analytisc_days_out .= "<span {$style}>{$date}({$count})</span>";
        }

        return $analytisc_days_out;
    }

    /**
     * Same as edu2 ClassService::get_edu_class (static cache of all classes).
     *
     * @return ($field is non-empty-string ? string : ($class_id is non-empty ? array : array<int, array>))
     */
    private function get_edu_class(string|int $class_id = '', string $field = ''): array|string
    {
        if ($this->allClassesById === null) {
            $this->allClassesById = EduClass::query()
                ->get()
                ->keyBy('class_id')
                ->map(fn(EduClass $m) => $m->toArray())
                ->all();
        }

        if ($field) {
            return isset($this->allClassesById[$class_id][$field]) ? $this->allClassesById[$class_id][$field] : '';
        }
        if ($class_id) {
            return $this->allClassesById[$class_id] ?? [];
        }

        return $this->allClassesById;
    }

    /**
     * Cached ~60 minutes (wp term_taxonomy + terms join via {@see WpTermTaxonomy::term}).
     *
     * @return list<array<string, mixed>>
     */
    private function getTermTaxonomyByDistrictId(int $district_id): array
    {
        $key = 'wp_term_taxonomy.product_cat.district.' . $district_id;

        return Cache::remember($key, 60 * 60, function () use ($district_id) {
            return $this->loadTermTaxonomyByDistrictIdUncached($district_id);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadTermTaxonomyByDistrictIdUncached(int $district_id): array
    {
        return WpTermTaxonomy::query()
            ->where('taxonomy', 'product_cat')
            ->where(function ($q) use ($district_id) {
                $q->where('parent', $district_id)
                    ->orWhere('term_taxonomy_id', $district_id)
                    ->orWhere('term_id', $district_id);
            })
            ->with('term')
            ->get()
            ->map(function (WpTermTaxonomy $tt) {
                $term = $tt->term;

                return [
                    'term_id' => $term ? (int) $term->term_id : (int) $tt->term_id,
                    'name' => $term ? (string) $term->name : '',
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $where  supports district_id (int|list<int>), %class_name => like
     * @return list<array<string, mixed>>
     */
    private function findClassesWhere(array $where): array
    {
        $q = EduClass::query();

        if (isset($where['district_id'])) {
            if (is_array($where['district_id'])) {
                $q->byDistrict($where['district_id']);
            } else {
                $q->where('district_id', $where['district_id']);
            }
        }

        if (isset($where['%class_name'])) {
            $q->where('class_name', 'like', '%' . $where['%class_name'] . '%');
        }

        return $q->get()->map(fn(EduClass $m) => $m->toArray())->all();
    }

    /**
     * Mirrors edu2 UserModelCommon::edu_get_user — returns list of merged user rows keyed by ID.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function fetchWpUsersMerged(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = array_values(array_unique($ids));

        $users = WpUser::query()
            ->whereIn('ID', $ids)
            ->with('meta')
            ->get();

        $rt = [];
        foreach ($users as $user) {
            $value = $user->getAttributes();
            foreach ($user->meta as $metaRow) {
                $value[$metaRow->meta_key] = $metaRow->meta_value;
            }
            $rt[$user->ID] = $value;
        }

        foreach ($rt as $key => $value) {
            $first_name = empty($value['billing_first_name']) ? ($value['first_name'] ?? '') : $value['billing_first_name'];
            $last_name = empty($value['billing_last_name']) ? ($value['last_name'] ?? '') : $value['billing_last_name'];
            $value['billing_first_name'] = $first_name;
            $value['first_name'] = $first_name;
            $value['billing_last_name'] = $last_name;
            $value['last_name'] = $last_name;
            $rt[$key] = $value;
        }

        return $rt;
    }

    /**
     * @param  array|string|null  $raw  DB JSON string or Eloquent JSON cast array
     * @return list<int|string>
     */
    private function decodeJsonIdList(array|string|null $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = $this->jsonService->decode_json((string) $raw);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Load complete class data including class_users and current month.
     * Adapted from edu2 ClassService::loadClassData() (Line 1377-1448)
     *
     * @param int $class_id
     * @return array ['class' => ..., 'class_users' => ..., 'class_month' => ...]
     */
    public function loadClassData(int $class_id): array
    {
        // Get class by class id (Eloquent instead of classModel)
        $class = EduClass::where('class_id', $class_id)->first();
        if (!$class) {
            abort(404, '班級不存在');
        }

        $classData = $class->toArray();
        $classData['class_exam'] = is_array($classData['class_exam'] ?? null)
            ? $classData['class_exam']
            : $this->jsonService->decode_json((string) ($classData['class_exam'] ?? ''));
        $classData['date_month'] = [];

        $classMonth = request()->get('month', '');

        // Get class_users (Eloquent instead of classModel)
        $classUsers = EduClassUser::where('class_id', $class_id)
            ->get()
            ->toArray();

        // Sort class_users by year and month (keep original logic)
        usort($classUsers, function ($a, $b) {
            // Extract last 4-digit year from class_year (handles formats like "2024-2025")
            preg_match_all('/\d{4}/', $a['class_year'], $matchesA);
            preg_match_all('/\d{4}/', $b['class_year'], $matchesB);

            $yearA = isset($matchesA[0]) ? max($matchesA[0]) : 0;
            $yearB = isset($matchesB[0]) ? max($matchesB[0]) : 0;

            // Extract numeric part of the Japanese month (e.g., "7月" => 7)
            $monthA = (int)filter_var($a['month'], FILTER_SANITIZE_NUMBER_INT);
            $monthB = (int)filter_var($b['month'], FILTER_SANITIZE_NUMBER_INT);

            // Compare year first
            if ((int)$yearA === (int)$yearB) {
                return $monthA <=> $monthB;
            }
            return (int)$yearA <=> (int)$yearB;
        });

        // Keep only last 4 months
        $classUsers = array_slice($classUsers, -4);

        // Filter and build date_month array
        foreach ($classUsers as $key => $value) {
            if (empty($value['month'])) {
                unset($classUsers[$key]);
                continue;
            }

            $student = $this->decodeJsonIdList($value['student'] ?? []);
            $studentTransfer = $this->decodeJsonIdList($value['student_transfer'] ?? []);

            if (!empty($student) || !empty($studentTransfer)) {
                $classData['date_month'][] = $value['month'];
                if (empty($classMonth) && $this->dateDayWeekService->is_current_month($value['month'])) {
                    $classMonth = $value['month'];
                }
            }
        }

        if (empty($classMonth)) {
            $classMonth = end($classData['date_month']);
        }

        if (empty($classData['date_month'])) {
            abort(404, '班級月份未找到');
        }

        return [
            'class' => $classData,
            'class_users' => $classUsers,
            'class_month' => $classMonth,
        ];
    }

    /**
     * Get single class_user record for specific month/year.
     * Adapted from edu2 ClassService::getClassUser() (Line 1332-1349)
     *
     * @param int $class_id
     * @param string $classMonth
     * @param string $classYear
     * @return array
     */
    public function getClassUser(int $class_id, string $classMonth, string $classYear): array
    {
        $classUser = EduClassUser::where('class_id', $class_id)
            ->where('month', $classMonth)
            ->where('class_year', $classYear)
            ->first();

        if (!$classUser) {
            abort(404, '未找到對應月份的班級');
        }

        return $classUser->toArray();
    }

    /**
     * Decode class_exam JSON from class_user record.
     * Adapted from edu2 ClassService::getClassExam() (Line 1351-1361)
     *
     * @param array $classUser
     * @return array
     */
    public function getClassExam(array $classUser): array
    {
        $classExam = is_array($classUser['class_exam'] ?? null)
            ? $classUser['class_exam']
            : $this->jsonService->decode_json((string) ($classUser['class_exam'] ?? ''));

        if (empty($classExam) || empty($classExam[0])) {
            $classExam = [];
        }

        return $classExam;
    }

    /**
     * Get exam items with full level details.
     * Adapted from edu2 ClassService::get_class_exam_v2() (Line 1295-1312)
     *
     * @param array $classExam
     * @return array
     */
    public function get_class_exam_v2(array $classExam): array
    {
        $exam = [];
        $level = $this->getAllLevels();

        if (!empty($classExam)) {
            foreach ($classExam as $value) {
                if ($value) {
                    $exam[] = $this->arrayServiceCommon->arrlist_search_one(
                        $level,
                        ['id' => $value]
                    );
                }
            }
        }

        return $exam;
    }

    /**
     * Filter top-level levels (pid=0).
     * Adapted from edu2 ClassService::sortLevelByPid() (Line 1678-1681)
     *
     * @param array $level
     * @return array
     */
    public function sortLevelByPid(array $level): array
    {
        return $this->arrayServiceCommon->arrlist_search($level, ['pid' => 0]);
    }

    /**
     * Get all assessment levels (with caching via EduService).
     * Adapted from edu2 ClassService::getAllLevels() (Line 1289-1293)
     *
     * @return array
     */
    public function getAllLevels(): array
    {
        // Use EduService which has Cache::rememberForever()
        return app(EduService::class)->getEduLevels();
    }

    /**
     * Coach hourly wage (edu2 ClassService::getHourlyWage): default 200 when unset or empty.
     */
    public function getHourlyWage(int $userId): float
    {
        $hourlyWage = 200.0;
        $w = EduUser::query()->where('user_id', $userId)->value('hourly_wage');
        if ($w !== null && $w !== '' && ! (is_numeric($w) && (float) $w === 0.0)) {
            $hourlyWage = (float) $w;
        }

        return $hourlyWage;
    }
}
