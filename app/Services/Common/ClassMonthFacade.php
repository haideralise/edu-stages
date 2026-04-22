<?php

namespace App\Services\Common;

use App\Models\WpUser;
use App\Services\ClassService;

/**
 * Ported from edu2/services/Common/ClassMonthFacade.php (10eng §1).
 * Admin/coach monthly class payload; with_attendance_level=summary uses AttendanceSummaryService
 * (batch attendance via AttendanceSummaryService::attachMonthSummariesForMultipleClasses → fetchUsersMonthAttendByClassMonths).
 */
class ClassMonthFacade
{
    use RequestCacheTrait;

    public function __construct(
        private ClassService $classService,
        private AttendanceSummaryService $attendanceSummaryService,
    ) {}

    public function fetchMonthlyClasses(array $filters, array $options = []): array
    {
        $cacheKey = md5(serialize($filters).serialize($options));

        return $this->remember("monthly_classes:{$cacheKey}", function () use ($filters, $options) {
            $monthYm = $this->normalizeMonth($filters['month'] ?? date('Y-m'));
            $district_id = (int) ($filters['district_id'] ?? 0);
            $district_id2 = (int) ($filters['district_id2'] ?? 0);
            $lv3 = $filters['lv3'] ?? '';
            $searchUserId = $filters['search_user_id'] ?? null;
            $classIds = $filters['class_ids'] ?? [];
            $withAttendanceLevel = $options['with_attendance_level'] ?? 'summary';

            if (isset($filters['class_ids']) && empty($classIds)) {
                return [
                    'month' => $monthYm,
                    'classes_lv3' => [],
                    'classes' => [],
                    'classes_user' => [],
                    'classes_all' => [],
                    'processed_classes' => [],
                    'no_teacher_num' => 0,
                    'no_days_num' => 0,
                    'with_attendance_level' => $withAttendanceLevel,
                ];
            }

            [$classes_lv3, $classesMap] = $this->classService->getClasses(
                $district_id,
                $district_id2,
                $lv3
            );
            $classes_lv3 = $this->classService->getUniqueLv3Classes($classes_lv3);

            if (! empty($classIds)) {
                $classesMap = array_filter($classesMap, function ($class) use ($classIds) {
                    return in_array($class['class_id'], $classIds);
                });
                $classes_lv3 = array_filter($classes_lv3, function ($class) use ($classIds) {
                    return in_array($class['class_id'], $classIds);
                });
            }

            [$classesWithMonth, $classes_user] = $this->classService->getClassesUserData(
                $classesMap,
                $searchUserId
            );

            [$classesAll, $no_teacher_num, $no_days_num] = $this->classService->processAllClasses(
                $classesWithMonth,
                $classes_user,
                $monthYm
            );

            $classesForDisplay = $classesAll;
            $processedClasses = $this->classService->processClasses($classesForDisplay, $monthYm);

            if ($withAttendanceLevel !== 'none') {
                $this->hydrateClassMembers($classesAll, $monthYm, $withAttendanceLevel);
            }

            return [
                'month' => $monthYm,
                'classes_lv3' => array_values($classes_lv3),
                'classes' => $classesWithMonth,
                'classes_user' => $classes_user,
                'classes_all' => $classesAll,
                'processed_classes' => $processedClasses,
                'no_teacher_num' => $no_teacher_num,
                'no_days_num' => $no_days_num,
                'with_attendance_level' => $withAttendanceLevel,
            ];
        });
    }

    private function hydrateClassMembers(array &$classes, string $monthYm, string $level): void
    {
        $all_user_ids = [];
        foreach ($classes as $class) {
            $all_user_ids = array_merge(
                $all_user_ids,
                $class['teacher'] ?? [],
                $class['student'] ?? [],
                $class['student_transfer'] ?? []
            );
        }
        $all_user_ids = array_values(array_unique(array_filter($all_user_ids)));

        $users_by_id = $this->loadWpUsersMergedByIds($all_user_ids);

        $timestamp = strtotime($monthYm.'-01');
        $fallbackMonth = (int) date('n', $timestamp).'月';
        $fallbackYear = (int) date('Y', $timestamp);

        $batchRows = [];
        foreach ($classes as $key => $class) {
            $teachers = $this->getUsersFromCache($class['teacher'] ?? [], $users_by_id);
            $students = $this->getUsersFromCache($class['student'] ?? [], $users_by_id);
            $temporaryStudents = $this->getUsersFromCache($class['student_transfer'] ?? [], $users_by_id);

            $monthStr = $class['month'] ?? $fallbackMonth;
            $year = (int) ($class['class_year'] ?? $fallbackYear);
            if ($monthStr === 'x') {
                $monthStr = $fallbackMonth;
            }

            if ($level !== 'none') {
                $batchRows[] = [
                    'key' => $key,
                    'teachers' => $teachers,
                    'students' => $students,
                    'temporaryStudents' => $temporaryStudents,
                    'class_id' => $class['class_id'],
                    'month' => $monthStr,
                    'year' => $year,
                ];
            } else {
                $classes[$key]['teacher'] = $teachers;
                $classes[$key]['student'] = $students;
                $classes[$key]['student_transfer'] = $temporaryStudents;
            }
        }

        if ($level !== 'none' && $batchRows !== []) {
            $this->attendanceSummaryService->attachMonthSummariesForMultipleClasses($batchRows);
            foreach ($batchRows as $row) {
                $k = $row['key'];
                $classes[$k]['teacher'] = $row['teachers'];
                $classes[$k]['student'] = $row['students'];
                $classes[$k]['student_transfer'] = $row['temporaryStudents'];
            }
        }
    }

    /**
     * @param  list<int|string>  $user_ids
     * @return array<int, array<string, mixed>>
     */
    private function loadWpUsersMergedByIds(array $user_ids): array
    {
        if ($user_ids === []) {
            return [];
        }

        $users = WpUser::query()
            ->whereIn('ID', $user_ids)
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
     * @param  list<int|string>       $ids
     * @param  array<int, array>  $users_by_id
     * @return list<array<string, mixed>>
     */
    private function getUsersFromCache(array $user_ids, array $users_by_id): array
    {
        if ($user_ids === []) {
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

    private function normalizeMonth(string $month): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $month;
        }
        $timestamp = strtotime($month);
        if ($timestamp === false) {
            $timestamp = strtotime(date('Y-m'));
        }

        return date('Y-m', $timestamp);
    }
}
