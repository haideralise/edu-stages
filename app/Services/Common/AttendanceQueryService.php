<?php
// version 1.0.0, create 19-11-2025
namespace App\Services\Common;

use App\Models\EduAttendance;
use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Models\EduClassUserDays;

class AttendanceQueryService
{
    /**
     * @var JsonService
     */
    private $jsonService;

    /**
     * @var ArrayServiceCommon
     */
    private $arrayServiceCommon;

    /**
     * @var DateDayWeekService
     */
    private $dateDayWeekService;

    /**
     * Cache for edu_class rows
     * @var array
     */
    private $classesCache = [];

    public function __construct(
        ?JsonService $jsonService = null,
        ?ArrayServiceCommon $arrayServiceCommon = null,
        ?DateDayWeekService $dateDayWeekService = null
    ) {
        $this->jsonService = $jsonService ?? new JsonService();
        $this->arrayServiceCommon = $arrayServiceCommon ?? new ArrayServiceCommon();
        $this->dateDayWeekService = $dateDayWeekService ?? new DateDayWeekService();
    }

    /**
     * 取得使用者在某班某月份的出席狀態陣列
     * @param string|null $role student|student_makeup|student_transfer，null=不篩選
     */
    public function fetchUserMonthAttend($user_id, $class_id, $month, $year = null, $role = null)
    {
        if (empty($user_id) || empty($class_id) || empty($month)) {
            return [];
        }
        $year = $year ?: date('Y');

        $daysReal = $this->getUserDaysReal($user_id, $class_id, $month, $year, true, $role);
        if (!is_array($daysReal)) {
            return [];
        }

        $attendanceRows = EduAttendance::query()
            ->where('class_id', $class_id)
            ->where('class_year', $year)
            ->where('user_id', $user_id)
            ->get()
            ->map(function (EduAttendance $m) {
                return [
                    'id' => $m->id,
                    'class_id' => (int) $m->class_id,
                    'month' => (string) ($m->getRawOriginal('month') ?? ''),
                    'user_id' => (int) $m->user_id,
                    'date' => (string) ($m->getRawOriginal('date') ?? ''),
                    'attendance' => (string) ($m->getRawOriginal('attendance') ?? ''),
                    'class_year' => (string) ($m->getRawOriginal('class_year') ?? ''),
                ];
            })->all();
        $attendanceRows = $this->arrayServiceCommon->arrlist_change_key($attendanceRows, 'date');

        $attends = [];
        foreach ($daysReal as $day) {
            $attends[$day] = isset($attendanceRows[$day]) ? $attendanceRows[$day]['attendance'] : 'present';
        }

        return $attends;
    }

    /**
     * 批量取得多個使用者在某班某月份的出席狀態陣列
     *
     * @param array $user_ids
     * @param int $class_id
     * @param string $month
     * @param int|null $year
     * @param string|null $role student|student_makeup|student_transfer，null=不篩選
     * @return array 以 user_id 為鍵的出席資料陣列
     */
    public function fetchUsersMonthAttend(array $user_ids, $class_id, $month, $year = null, $role = null)
    {
        if (empty($user_ids) || empty($class_id) || empty($month)) {
            return [];
        }

        $year = $year ?: date('Y');

        // 去重 & 過濾空值
        $user_ids = array_values(array_unique(array_filter($user_ids)));
        if (empty($user_ids)) {
            return [];
        }

        // 1. 批量查詢出席記錄
        $attendanceRows = EduAttendance::query()
            ->where('class_id', $class_id)
            ->where('class_year', $year)
            ->whereIn('user_id', $user_ids)
            ->get()
            ->map(function (EduAttendance $m) {
                return [
                    'id' => $m->id,
                    'class_id' => (int) $m->class_id,
                    'month' => (string) ($m->getRawOriginal('month') ?? ''),
                    'user_id' => (int) $m->user_id,
                    'date' => (string) ($m->getRawOriginal('date') ?? ''),
                    'attendance' => (string) ($m->getRawOriginal('attendance') ?? ''),
                    'class_year' => (string) ($m->getRawOriginal('class_year') ?? ''),
                ];
            })->all();

        $attendanceByUser = [];
        foreach ($attendanceRows as $row) {
            $uid = $row['user_id'];
            if (!isset($attendanceByUser[$uid])) {
                $attendanceByUser[$uid] = [];
            }
            $attendanceByUser[$uid][$row['date']] = $row['attendance'];
        }

        // 2. 批量取得使用者上堂日期（優化：一次查 edu_class_user_days，避免 N+1）
        $usersDaysMap = $this->getUsersDaysBatch($user_ids, $class_id, $month, $year, $role);

        $result = [];
        foreach ($user_ids as $uid) {
            $days = $usersDaysMap[$uid] ?? [];
            // 合併 attendance 的 date（補堂等）到 days
            $attendanceDates = array_keys($attendanceByUser[$uid] ?? []);
            $days = array_values(array_unique(array_merge($days, $attendanceDates)));
            $daysReal = $this->dateDayWeekService->exclude_other_month_days($days, $month);

            $attends = [];
            $userAttendances = $attendanceByUser[$uid] ?? [];
            foreach ($daysReal as $day) {
                $attends[$day] = $userAttendances[$day] ?? 'present';
            }
            $result[$uid] = $attends;
        }

        return $result;
    }

    /**
     * 批量取得多個使用者在多班多月份的出席狀態（支援多班一次查詢）
     *
     * @param array $user_ids
     * @param array $classMonths [['user_id'=>..,'class_id'=>..,'month'=>..,'year'=>..], ...]
     * @param string|null $role
     * @return array $result[user_id][class_id][month] = [date=>status, ...]
     */
    public function fetchUsersMonthAttendByClassMonths(array $user_ids, array $classMonths, $role = null)
    {
        $user_ids = array_values(array_unique(array_filter($user_ids)));
        $classMonths = array_filter($classMonths, function ($cm) {
            return !empty($cm['class_id']) && !empty($cm['month']);
        });
        if (empty($user_ids) || empty($classMonths)) {
            return [];
        }

        $class_ids = array_unique(array_column($classMonths, 'class_id'));
        $this->preloadEduClassesByIds($class_ids);

        $years = array_unique(array_filter(array_column($classMonths, 'year')));
        $year = !empty($years) ? (int) reset($years) : (int) date('Y');

        // 1. 批量查 edu_attendance
        $attRows = EduAttendance::query()
            ->whereIn('class_id', $class_ids)
            ->where('class_year', $year)
            ->whereIn('user_id', $user_ids)
            ->get()
            ->map(function (EduAttendance $m) {
                return [
                    'id' => $m->id,
                    'class_id' => (int) $m->class_id,
                    'month' => (string) ($m->getRawOriginal('month') ?? ''),
                    'user_id' => (int) $m->user_id,
                    'date' => (string) ($m->getRawOriginal('date') ?? ''),
                    'attendance' => (string) ($m->getRawOriginal('attendance') ?? ''),
                    'class_year' => (string) ($m->getRawOriginal('class_year') ?? ''),
                ];
            })->all();
        $attByUserClass = [];
        foreach ($attRows as $row) {
            $uid = (int) $row['user_id'];
            $cid = (int) $row['class_id'];
            if (!isset($attByUserClass[$uid])) {
                $attByUserClass[$uid] = [];
            }
            if (!isset($attByUserClass[$uid][$cid])) {
                $attByUserClass[$uid][$cid] = [];
            }
            $attByUserClass[$uid][$cid][$row['date']] = $row['attendance'];
        }

        // 2. 批量查 edu_class_user_days（不篩 month，在 PHP 過濾）
        $daysQ = EduClassUserDays::query()
            ->whereIn('class_id', $class_ids)
            ->where('class_year', $year)
            ->whereIn('user_id', $user_ids);
        if ($role !== null && $role !== '') {
            $daysQ->where('role', $role);
        }
        $daysRows = $daysQ->get()->map(fn(EduClassUserDays $m) => $m->getAttributes())->all();

        $daysByUserClassMonth = [];
        foreach ($daysRows as $row) {
            $uid = (int) $row['user_id'];
            $cid = (int) $row['class_id'];
            $m = trim($row['month'] ?? '');
            if (empty($m) || (empty($row['days']) || $row['days'] === '[]')) {
                continue;
            }
            $key = "{$uid}_{$cid}_{$m}";
            $days = array_filter(array_map('trim', explode(',', $row['days'])));
            $daysByUserClassMonth[$key] = $days;
        }

        // 3. 收集需 fallback 的 (class_id, month)，批量查 edu_class_user
        $fallbackKeys = [];
        foreach ($classMonths as $cm) {
            $uid = (int) ($cm['user_id'] ?? 0);
            $cid = (int) $cm['class_id'];
            $m = trim($cm['month'] ?? '');
            $y = (int) ($cm['year'] ?? $year);
            if (!in_array($uid, $user_ids) || $cid <= 0 || empty($m)) {
                continue;
            }
            $key = "{$uid}_{$cid}_{$m}";
            if (!isset($daysByUserClassMonth[$key])) {
                $fallbackKeys["{$cid}_{$m}"] = ['class_id' => $cid, 'month' => $m, 'year' => $y];
            }
        }

        $classUserCache = [];
        if ($fallbackKeys !== []) {
            $cuModels = EduClassUser::query()
                ->where(function ($q) use ($fallbackKeys) {
                    foreach ($fallbackKeys as $params) {
                        $q->orWhere(function ($w) use ($params) {
                            $w->where('class_id', $params['class_id'])
                                ->where('month', $params['month'])
                                ->where('class_year', $params['year']);
                        });
                    }
                })
                ->get();

            $cuModelByFk = [];
            foreach ($cuModels as $cuModel) {
                $fk = (int) $cuModel->class_id . '_' . trim((string) ($cuModel->getRawOriginal('month') ?? $cuModel->month));
                $cuModelByFk[$fk] = $cuModel;
            }

            foreach ($fallbackKeys as $fk => $params) {
                $cuModel = $cuModelByFk[$fk] ?? null;
                if ($cuModel) {
                    $cu = $cuModel->getAttributes();
                    foreach (['student', 'student_makeup', 'student_transfer', 'student_order', 'order_id', 'teacher', 'class_exam'] as $col) {
                        if (array_key_exists($col, $cu)) {
                            $cu[$col] = $cuModel->getRawOriginal($col);
                        }
                    }
                } else {
                    $cu = [];
                }
                $classUserCache[$fk] = $cu ?: ['class_id' => $params['class_id'], 'month' => $params['month'], 'class_year' => $params['year'], 'days' => ''];
            }
        }

        // 3b. 每個 (class_id, month) 只算一次排程日（對齊原本每 user 呼叫 getClassUserDaysArray，避免 N+1）
        $scheduleDaysByFk = [];
        $studentTransferListByFk = [];
        foreach ($classUserCache as $fk => $class_user) {
            $student_transfer = [];
            if (! empty($class_user['student_transfer'])) {
                $decoded = $this->jsonService->decode_json($class_user['student_transfer']);
                $student_transfer = is_array($decoded) ? $decoded : [];
            }
            $studentTransferListByFk[$fk] = $student_transfer;
            $scheduleDaysByFk[$fk] = $this->getClassUserDaysArray($class_user);
        }

        // 4. 為每個 (user_id, class_id, month) 組結果
        $result = [];
        foreach ($user_ids as $uid) {
            $result[$uid] = [];
        }

        foreach ($classMonths as $cm) {
            $uid = (int) ($cm['user_id'] ?? 0);
            $cid = (int) $cm['class_id'];
            $m = trim($cm['month'] ?? '');
            $y = (int) ($cm['year'] ?? $year);
            if (! in_array($uid, $user_ids)) {
                continue;
            }

            $key = "{$uid}_{$cid}_{$m}";
            $days = $daysByUserClassMonth[$key] ?? null;

            if ($days === null) {
                $fk = "{$cid}_{$m}";
                $class_user = $classUserCache[$fk] ?? null;
                if ($class_user) {
                    $student_transfer = $studentTransferListByFk[$fk] ?? [];
                    if (in_array($uid, $student_transfer)) {
                        $days = [];
                    } else {
                        $baseDays = $scheduleDaysByFk[$fk] ?? [];
                        $days = $this->dateDayWeekService->exclude_other_month_days($baseDays, $m);
                    }
                } else {
                    $days = [];
                }
            } else {
                $days = $this->dateDayWeekService->exclude_other_month_days($days, $m);
            }

            $attDates = array_keys($attByUserClass[$uid][$cid] ?? []);
            $days = array_values(array_unique(array_merge($days, $attDates)));
            $daysReal = $this->dateDayWeekService->exclude_other_month_days($days, $m);

            $attends = [];
            $userAtt = $attByUserClass[$uid][$cid] ?? [];
            foreach ($daysReal as $day) {
                $attends[$day] = $userAtt[$day] ?? 'present';
            }

            if (!isset($result[$uid][$cid])) {
                $result[$uid][$cid] = [];
            }
            $result[$uid][$cid][$m] = $attends;
        }

        return $result;
    }

    /**
     * 批量取得多個使用者的預設上堂日期（優化 getUserDays 的 N+1）
     * @param array $user_ids
     * @param int $class_id
     * @param string $month
     * @param int|null $year
     * @param string|null $role
     * @return array map[user_id] => [date, date, ...]
     */
    private function getUsersDaysBatch(array $user_ids, $class_id, $month, $year = null, $role = null)
    {
        $year = $year ?: date('Y');
        $user_ids = array_values(array_unique(array_filter($user_ids)));
        if (empty($user_ids)) {
            return [];
        }

        $batchDaysQ = EduClassUserDays::query()
            ->where('class_id', $class_id)
            ->where('month', $month)
            ->where('class_year', $year)
            ->whereIn('user_id', $user_ids);
        if ($role !== null && $role !== '') {
            $batchDaysQ->where('role', $role);
        }
        $rows = $batchDaysQ->orderBy('user_id')->orderBy('id')->get()->map(fn(EduClassUserDays $m) => $m->getAttributes())->all();

        $firstRowByUid = [];
        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            if (! isset($firstRowByUid[$uid])) {
                $firstRowByUid[$uid] = $row;
            }
        }

        $result = [];
        $usersWithDays = [];

        foreach ($user_ids as $uid) {
            $uid = (int) $uid;
            $row = $firstRowByUid[$uid] ?? null;
            if ($row && ! empty($row['days']) && $row['days'] !== '[]') {
                $days = explode(',', $row['days']);
                $result[$uid] = $this->dateDayWeekService->exclude_other_month_days($days, $month);
                $usersWithDays[$uid] = true;
            }
        }

        // Fallback：無有效 edu_class_user_days 的 user，用班級排程（對齊 {@see getUserDays} 只看第一筆 days 列）
        $cuFb = EduClassUser::query()
            ->where('class_id', $class_id)
            ->where('month', $month)
            ->where('class_year', $year)
            ->first();
        if ($cuFb) {
            $class_user = $cuFb->getAttributes();
            foreach (['student', 'student_makeup', 'student_transfer', 'student_order', 'order_id', 'teacher', 'class_exam'] as $col) {
                if (array_key_exists($col, $class_user)) {
                    $class_user[$col] = $cuFb->getRawOriginal($col);
                }
            }
        } else {
            $class_user = [];
        }
        $class_user = $class_user ?: ['class_id' => $class_id, 'month' => $month, 'class_year' => $year, 'days' => ''];

        $student_transfer = [];
        if (! empty($class_user['student_transfer'])) {
            $decoded = $this->jsonService->decode_json($class_user['student_transfer']);
            $student_transfer = is_array($decoded) ? $decoded : [];
        }

        $baseDays = $this->getClassUserDaysArray($class_user);

        foreach ($user_ids as $uid) {
            $uid = (int) $uid;
            if (isset($usersWithDays[$uid])) {
                continue;
            }
            if (in_array($uid, $student_transfer)) {
                $result[$uid] = [];
                continue;
            }
            $result[$uid] = $this->dateDayWeekService->exclude_other_month_days($baseDays, $month);
        }

        return $result;
    }

    /**
     * 取得使用者實際上堂日期（包含插班、請假處理）
     * @param string|null $role student|student_makeup|student_transfer，null=不篩選 role
     */
    public function getUserDaysReal($user_id, $class_id, $month, $year = null, $excludeOtherMonthDays = true, $role = null)
    {
        $year = $year ?: date('Y');
        $days = $this->getUserDays($user_id, $class_id, $month, $year, $excludeOtherMonthDays, $role);
        if (!is_array($days)) {
            return [];
        }

        $attendance = EduAttendance::query()
            ->where('class_id', $class_id)
            ->where('class_year', $year)
            ->where('user_id', $user_id)
            ->get()
            ->map(function (EduAttendance $m) {
                return [
                    'id' => $m->id,
                    'class_id' => (int) $m->class_id,
                    'month' => (string) ($m->getRawOriginal('month') ?? ''),
                    'user_id' => (int) $m->user_id,
                    'date' => (string) ($m->getRawOriginal('date') ?? ''),
                    'attendance' => (string) ($m->getRawOriginal('attendance') ?? ''),
                    'class_year' => (string) ($m->getRawOriginal('class_year') ?? ''),
                ];
            })->all();
        if ($attendance) {
            foreach ($attendance as $value) {
                $days[] = $value['date'];
            }
        }

        if ($excludeOtherMonthDays) {
            $days = $this->dateDayWeekService->exclude_other_month_days($days, $month);
        } else {
            $days = $this->dateDayWeekService->days_sort($days);
        }

        return array_values(array_unique($days));
    }

    /**
     * Same as {@see getUserDays} for one user/month/year, but one round-trip for
     * edu_class_user_days + batched edu_class / edu_class_user for fallbacks.
     *
     * @param  list<int>  $class_ids
     * @return array<int, list<string>> class_id => day strings
     */
    private function getUserDaysForClassIdsBatch(
        int $user_id,
        array $class_ids,
        string $month,
        $year = null,
        bool $excludeOtherMonthDays = true,
        $role = null
    ): array {
        $year = $year ?: date('Y');
        $yearStr = (string) $year;
        $class_ids = array_values(array_unique(array_filter(array_map('intval', $class_ids))));
        $out = [];
        foreach ($class_ids as $cid) {
            $out[$cid] = [];
        }
        if ($user_id <= 0 || $class_ids === []) {
            return $out;
        }

        $daysQ = EduClassUserDays::query()
            ->where('user_id', $user_id)
            ->where('month', $month)
            ->where('class_year', $yearStr)
            ->whereIn('class_id', $class_ids);
        if ($role !== null && $role !== '') {
            $daysQ->where('role', $role);
        }
        $pickedByClass = [];
        foreach ($daysQ->orderBy('id')->get() as $m) {
            $cid = (int) $m->class_id;
            if (isset($pickedByClass[$cid])) {
                continue;
            }
            $pickedByClass[$cid] = $m->getAttributes();
        }

        $needFallback = [];
        foreach ($class_ids as $cid) {
            $record = $pickedByClass[$cid] ?? null;
            if ($record && ! empty($record['days']) && $record['days'] !== '[]') {
                $days = explode(',', $record['days']);
                if ($excludeOtherMonthDays) {
                    $days = $this->dateDayWeekService->exclude_other_month_days($days, $month);
                }
                $out[$cid] = array_values(array_unique($days));
            } else {
                $needFallback[] = $cid;
            }
        }

        if ($needFallback === []) {
            return $out;
        }

        $classesById = EduClass::query()
            ->whereIn('class_id', $needFallback)
            ->get()
            ->keyBy('class_id');

        $cuByClassId = [];
        foreach (
            EduClassUser::query()
                ->where('month', $month)
                ->where('class_year', $yearStr)
                ->whereIn('class_id', $needFallback)
                ->orderBy('class_id')
                ->orderBy('id')
                ->get() as $cuModel
        ) {
            $cid = (int) $cuModel->class_id;
            if (! isset($cuByClassId[$cid])) {
                $cuByClassId[$cid] = $cuModel;
            }
        }

        foreach ($needFallback as $cid) {
            $classModel = $classesById[$cid] ?? null;
            $class = $classModel ? $classModel->toArray() : null;

            $cuModel = $cuByClassId[$cid] ?? null;
            if ($cuModel) {
                $class_user = $cuModel->getAttributes();
                foreach (['student', 'student_makeup', 'student_transfer', 'student_order', 'order_id', 'teacher', 'class_exam'] as $col) {
                    if (array_key_exists($col, $class_user)) {
                        $class_user[$col] = $cuModel->getRawOriginal($col);
                    }
                }
            } else {
                $class_user = [];
            }

            if (empty($class)) {
                $out[$cid] = [];
                continue;
            }

            if (isset($class_user['student_transfer'])) {
                $student_transfer = $this->jsonService->decode_json($class_user['student_transfer']);
                if (is_array($student_transfer) && in_array($user_id, $student_transfer)) {
                    $out[$cid] = [];
                    continue;
                }
            }

            $days = $this->getClassUserDaysArray($class_user);
            if ($excludeOtherMonthDays) {
                $days = $this->dateDayWeekService->exclude_other_month_days($days, $month);
            }
            $out[$cid] = array_values(array_unique($days));
        }

        return $out;
    }

    /**
     * 取得使用者預設上堂日期
     * @param string|null $role student|student_makeup|student_transfer，null=不篩選 role
     */
    public function getUserDays($user_id, $class_id, $month, $year = null, $excludeOtherMonthDays = true, $role = null)
    {
        $year = $year ?: date('Y');
        $daysRecordQ = EduClassUserDays::query()
            ->where([
                'class_id' => $class_id,
                'month' => $month,
                'class_year' => $year,
                'user_id' => $user_id,
            ]);
        if ($role !== null && $role !== '') {
            $daysRecordQ->where('role', $role);
        }
        $daysRecordModel = $daysRecordQ->first();
        $record = $daysRecordModel ? $daysRecordModel->getAttributes() : [];

        if (!empty($record) && !empty($record['days']) && $record['days'] !== '[]') {
            $days = explode(',', $record['days']);
        } else {
            $classModel = EduClass::query()->where('class_id', $class_id)->first();
            $class = $classModel ? $classModel->toArray() : null;

            $cuModel = EduClassUser::query()
                ->where([
                    'class_id' => $class_id,
                    'month' => $month,
                    'class_year' => $year,
                ])
                ->first();
            if ($cuModel) {
                $class_user = $cuModel->getAttributes();
                foreach (['student', 'student_makeup', 'student_transfer', 'student_order', 'order_id', 'teacher', 'class_exam'] as $col) {
                    if (array_key_exists($col, $class_user)) {
                        $class_user[$col] = $cuModel->getRawOriginal($col);
                    }
                }
            } else {
                $class_user = [];
            }

            if (empty($class)) {
                return [];
            }

            if (isset($class_user['student_transfer'])) {
                $student_transfer = $this->jsonService->decode_json($class_user['student_transfer']);
                if (in_array($user_id, $student_transfer)) {
                    return [];
                }
            }

            $days = $this->getClassUserDaysArray($class_user);
        }

        if ($excludeOtherMonthDays) {
            $days = $this->dateDayWeekService->exclude_other_month_days($days, $month);
        }

        return array_values(array_unique($days));
    }

    /**
     * Mirrors edu2 AttendanceModel::get_user_attend (month-scoped attendance rows).
     *
     * @return list<string>
     */
    public function getUserAttend($user_id, $class_id, $month, $class_year = null): array
    {
        $class_year = $class_year ?: date('Y');
        $days = $this->getUserDays($user_id, $class_id, $month, $class_year, true);
        if (! is_array($days)) {
            $days = [];
        }

        $attends = EduAttendance::query()
            ->where('class_id', $class_id)
            ->where('month', $month)
            ->where('class_year', $class_year)
            ->where('user_id', $user_id)
            ->get();

        foreach ($attends as $m) {
            $att = (string) $m->getRawOriginal('attendance');
            $date = (string) $m->getRawOriginal('date');
            if ($att === 'present') {
                $days[] = $date;
                $days = array_values(array_unique($days));
            } else {
                $days = array_values(array_diff($days, [$date]));
            }
        }

        return $this->dateDayWeekService->exclude_other_month_days($days, $month);
    }

    /**
     * Merges real attend days the same way as looping {@see getUserAttend} per edu_class_user row
     * (one batched plan-days load per distinct class_id + one {@see EduAttendance} query for all class_ids).
     *
     * @param  list<array<string, mixed>>  $classUserLegacyRows  rows in legacy shape (must include class_id)
     * @return list<string>
     */
    public function mergeUserAttendRealDaysForClassUserRows(int $user_id, string $month, string $class_year, array $classUserLegacyRows): array
    {
        if ($classUserLegacyRows === []) {
            return [];
        }

        $class_ids = [];
        foreach ($classUserLegacyRows as $_row) {
            $cid = (int) ($_row['class_id'] ?? 0);
            if ($cid > 0) {
                $class_ids[$cid] = true;
            }
        }
        $class_ids = array_keys($class_ids);
        if ($class_ids === []) {
            return [];
        }

        $daysByClassId = $this->getUserDaysForClassIdsBatch(
            $user_id,
            $class_ids,
            $month,
            $class_year,
            true
        );

        $attRows = EduAttendance::query()
            ->where('user_id', $user_id)
            ->where('month', $month)
            ->where('class_year', $class_year)
            ->whereIn('class_id', $class_ids)
            ->get();

        $attByClass = [];
        foreach ($attRows as $m) {
            $cid = (int) $m->class_id;
            if (! isset($attByClass[$cid])) {
                $attByClass[$cid] = [];
            }
            $attByClass[$cid][] = $m;
        }

        $now_month_attend_days_real = [];
        foreach ($classUserLegacyRows as $_row) {
            $cid = (int) ($_row['class_id'] ?? 0);
            $days = $daysByClassId[$cid] ?? [];
            if (! is_array($days)) {
                $days = [];
            }
            foreach ($attByClass[$cid] ?? [] as $m) {
                $att = (string) $m->getRawOriginal('attendance');
                $date = (string) $m->getRawOriginal('date');
                if ($att === 'present') {
                    $days[] = $date;
                    $days = array_values(array_unique($days));
                } else {
                    $days = array_values(array_diff($days, [$date]));
                }
            }
            $days = $this->dateDayWeekService->exclude_other_month_days($days, $month);
            $now_month_attend_days_real = array_merge($now_month_attend_days_real, $days);
        }

        return $now_month_attend_days_real;
    }

    /**
     * 批量取得多個班級的每日人數統計摘要（合併 class/month/year 維度的查詢，避免外層迴圈 N+1）
     *
     * @param  list<array>  $class_users_list  與 {@see buildClassDailySummary} 相同結構，索引 0..n-1 對應回傳
     * @return list<array>
     */
    public function buildClassDailySummariesBatch(array $class_users_list): array
    {
        $n = count($class_users_list);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[$i] = [];
        }

        $activeIndices = [];
        for ($i = 0; $i < $n; $i++) {
            if (! empty($class_users_list[$i])) {
                $activeIndices[] = $i;
            }
        }

        if ($activeIndices === []) {
            return $out;
        }

        $groups = [];
        $rowMeta = [];
        foreach ($activeIndices as $i) {
            $cu = $class_users_list[$i];
            $class_id = (int) ($cu['class_id'] ?? 0);
            $month = $cu['month'] ?? '';
            $year = $cu['class_year'] ?? null;
            $yearNorm = $year ?: date('Y');

            $students = $this->decodeJsonList($cu['student'] ?? '[]');
            $studentTransfer = $this->decodeJsonList($cu['student_transfer'] ?? '[]');
            $studentAll = array_merge($students, $studentTransfer);

            $uniqueUids = [];
            foreach ($studentAll as $rawUid) {
                $uidPart = (int) $rawUid;
                if ($uidPart > 0) {
                    $uniqueUids[$uidPart] = true;
                }
            }
            $uniqueList = array_keys($uniqueUids);

            $gkey = $class_id . "\0" . (string) $month . "\0" . (string) $yearNorm;
            if (! isset($groups[$gkey])) {
                $groups[$gkey] = [
                    'class_id' => $class_id,
                    'month' => $month,
                    'year' => $yearNorm,
                    'uids' => [],
                ];
            }
            foreach ($uniqueList as $u) {
                $groups[$gkey]['uids'][$u] = true;
            }

            $rowMeta[$i] = [
                'gkey' => $gkey,
                'studentAll' => $studentAll,
                'class_id' => $class_id,
                'month' => $month,
                'year' => $year,
            ];
        }

        $classIdsForPreload = [];
        foreach ($activeIndices as $i) {
            $id = $class_users_list[$i]['class_id'] ?? null;
            if ($id !== null && $id !== '') {
                $classIdsForPreload[] = $id;
            }
        }
        $this->preloadEduClassesByIds($classIdsForPreload);

        $planByGroup = [];
        $attByGroupUser = [];
        foreach ($groups as $gkey => $g) {
            $uidList = array_keys($g['uids']);
            $planByGroup[$gkey] = $uidList !== []
                ? $this->getUsersDaysBatch($uidList, $g['class_id'], $g['month'], $g['year'], null)
                : [];
            $attByGroupUser[$gkey] = [];
        }

        $hasAttendanceScope = false;
        foreach ($groups as $g) {
            if (array_keys($g['uids']) !== []) {
                $hasAttendanceScope = true;
                break;
            }
        }

        if ($hasAttendanceScope) {
            $rows = EduAttendance::query()
                ->where(function ($outer) use ($groups) {
                    foreach ($groups as $g) {
                        $uidList = array_keys($g['uids']);
                        if ($uidList === []) {
                            continue;
                        }
                        $outer->orWhere(function ($w) use ($g, $uidList) {
                            $w->where('class_id', $g['class_id'])
                                ->where('month', $g['month'])
                                ->where('class_year', $g['year'])
                                ->whereIn('user_id', $uidList);
                        });
                    }
                })
                ->get();

            foreach ($rows as $m) {
                $cid = (int) $m->class_id;
                $monthDb = (string) ($m->getRawOriginal('month') ?? '');
                $yearDb = (string) ($m->getRawOriginal('class_year') ?? '');
                foreach ($groups as $gkey => $g) {
                    if ((int) $g['class_id'] !== $cid) {
                        continue;
                    }
                    if ((string) $g['month'] !== $monthDb) {
                        continue;
                    }
                    if ((string) $g['year'] !== $yearDb) {
                        continue;
                    }
                    $u = (int) $m->user_id;
                    if (! isset($g['uids'][$u])) {
                        continue;
                    }
                    $attByGroupUser[$gkey][$u][] = [
                        'attendance' => (string) ($m->getRawOriginal('attendance') ?? ''),
                        'date' => (string) ($m->getRawOriginal('date') ?? ''),
                    ];
                    break;
                }
            }
        }

        foreach ($activeIndices as $i) {
            $cu = $class_users_list[$i];
            $meta = $rowMeta[$i];
            $gkey = $meta['gkey'];
            $planByUser = $planByGroup[$gkey];
            $attByUser = $attByGroupUser[$gkey];

            $baseDays = $this->getClassUserDaysArray($cu);
            $dailyCounter = [];
            foreach ($baseDays as $day) {
                $dailyCounter[$day] = 0;
            }

            $class_id = $meta['class_id'];
            $month = $meta['month'];
            $year = $meta['year'];

            foreach ($meta['studentAll'] as $user_id) {
                $uid = (int) $user_id;
                if ($uid <= 0) {
                    $days = $this->calculateUserAttendDays($user_id, $class_id, $month, $year);
                } else {
                    $days = $planByUser[$uid] ?? [];
                    if (! is_array($days)) {
                        $days = [];
                    }
                    foreach ($attByUser[$uid] ?? [] as $row) {
                        if ($row['attendance'] === 'present') {
                            $days[] = $row['date'];
                        } else {
                            $days = array_diff($days, [$row['date']]);
                        }
                    }
                    $days = array_values(array_unique($days));
                    $days = $this->dateDayWeekService->exclude_other_month_days($days, $month);
                }
                foreach ($days as $day) {
                    if (! isset($dailyCounter[$day])) {
                        $dailyCounter[$day] = 1;
                    } else {
                        $dailyCounter[$day]++;
                    }
                }
            }

            $summary = [];
            foreach ($dailyCounter as $date => $count) {
                $summary[] = [
                    'date' => $date,
                    'count' => $count,
                    'sort' => strtotime($date),
                ];
            }

            $out[$i] = $this->arrayServiceCommon->arrlist_sort($summary, ['sort' => 1]);
        }

        return $out;
    }

    /**
     * 取得班級的每日人數統計摘要
     */
    public function buildClassDailySummary(array $class_user)
    {
        if (empty($class_user)) {
            return [];
        }

        return $this->buildClassDailySummariesBatch([$class_user])[0] ?? [];
    }

    /**
     * 計算單一學生於指定月份的實際上堂日期
     */
    public function calculateUserAttendDays($user_id, $class_id, $month, $year = null)
    {
        $year = $year ?: date('Y');
        $days = $this->getUserDays($user_id, $class_id, $month, $year);
        $attendance = EduAttendance::query()
            ->where([
                'class_id' => $class_id,
                'month' => $month,
                'class_year' => $year,
                'user_id' => $user_id,
            ])
            ->get()
            ->map(function (EduAttendance $m) {
                return [
                    'id' => $m->id,
                    'class_id' => (int) $m->class_id,
                    'month' => (string) ($m->getRawOriginal('month') ?? ''),
                    'user_id' => (int) $m->user_id,
                    'date' => (string) ($m->getRawOriginal('date') ?? ''),
                    'attendance' => (string) ($m->getRawOriginal('attendance') ?? ''),
                    'class_year' => (string) ($m->getRawOriginal('class_year') ?? ''),
                ];
            })->all();

        if ($attendance) {
            foreach ($attendance as $row) {
                if ($row['attendance'] === 'present') {
                    $days[] = $row['date'];
                } else {
                    $days = array_diff($days, [$row['date']]);
                }
            }
        }

        $days = array_values(array_unique($days));
        return $this->dateDayWeekService->exclude_other_month_days($days, $month);
    }

    /**
     * 將 attendance array 轉換為統計文字
     */
    public function formatAttendSummary(array $attends)
    {
        return $this->arrayServiceCommon->attend_array_text($attends);
    }

    /**
     * 取得班級排程日期
     */
    public function getClassUserDaysArray($class_user)
    {
        if (empty($class_user)) {
            return [];
        }

        $class_name = $this->getEduClass($class_user['class_id'], 'class_name');
        $week = $this->dateDayWeekService->get_array_week($class_name);
        $month = $this->dateDayWeekService->get_array_month($class_user['month']);

        if (!empty($class_user['days'])) {
            $days = explode(',', $class_user['days']);
        } else {
            $days = $this->dateDayWeekService->get_array_days($month, $week, $class_user['class_year']);
        }

        return $this->dateDayWeekService->exclude_other_month_days($days, $month);
    }

    /**
     * 一次載入多筆 edu_class 填入 {@see $classesCache}（避免 buildClassDailySummariesBatch / getClassUserDaysArray 逐班查詢）
     *
     * @param  list<int|string|null>  $class_ids
     */
    public function preloadEduClassesByIds(array $class_ids): void
    {
        $pendingKeys = [];
        foreach ($class_ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $ck = $this->resolveEduClassCacheKey($id);
            if ($ck === null) {
                continue;
            }
            if (! isset($this->classesCache[$ck])) {
                $pendingKeys[$ck] = $id;
            }
        }
        if ($pendingKeys === []) {
            return;
        }

        $idsForQuery = array_values(array_unique(array_values($pendingKeys)));
        $rows = EduClass::query()->whereIn('class_id', $idsForQuery)->get();
        foreach ($rows as $ec) {
            $ck = $this->resolveEduClassCacheKey($ec->class_id);
            $this->classesCache[$ck] = $ec->toArray();
        }
        foreach (array_keys($pendingKeys) as $ck) {
            if (! isset($this->classesCache[$ck])) {
                $this->classesCache[$ck] = [];
            }
        }
    }

    /**
     * 快取班別資料
     */
    public function getEduClass($class_id = '', $field = '')
    {
        $ck = ($class_id === '' || $class_id === null) ? null : $this->resolveEduClassCacheKey($class_id);

        if ($class_id) {
            if (! isset($this->classesCache[$ck])) {
                $ec = EduClass::query()->where('class_id', $class_id)->first();
                $this->classesCache[$ck] = $ec ? $ec->toArray() : [];
            }
        }

        if ($field) {
            if (! $class_id || $ck === null) {
                return '';
            }

            return isset($this->classesCache[$ck][$field]) ? $this->classesCache[$ck][$field] : '';
        }

        if ($class_id) {
            return $this->classesCache[$ck];
        }

        return $this->classesCache;
    }

    /**
     * @return ($class_id is numeric ? int : string)|null
     */
    private function resolveEduClassCacheKey($class_id): int|string|null
    {
        if ($class_id === null || $class_id === '') {
            return null;
        }
        if (is_numeric($class_id)) {
            return (int) $class_id;
        }

        return (string) $class_id;
    }

    private function decodeJsonList($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $list = $this->jsonService->decode_json($value);
        if (! is_array($list)) {
            $list = [];
        }

        return $list;
    }
}
