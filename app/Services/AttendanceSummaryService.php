<?php

namespace App\Services;

use App\Services\AttendanceQueryService;
use Illuminate\Support\Facades\Log;

/**
 * Ported from edu2/services/Common/AttendanceSummaryService.php.
 * Uses AttendanceQueryService::fetchUsersMonthAttendByClassMonths for batch coach/class-month attendance.
 */
class AttendanceSummaryService
{
    public function __construct(
        private AttendanceQueryService $attendanceQueryService,
    ) {}

    public function attachMonthSummaryToClassUsers(
        &$students,
        &$temporaryStudents,
        $class_id,
        string $month,
        $year,
        $studentRole = null,
        $temporaryRole = null
    ): void {
        if (! empty($students)) {
            $student_ids = array_column($students, 'ID');
            $all_attends = $this->attendanceQueryService->fetchUsersMonthAttend(
                $student_ids,
                $class_id,
                $month,
                $year,
                $studentRole
            );
            foreach ($students as &$user) {
                $attends = $all_attends[$user['ID']] ?? [];
                $user['attendance_summary'] = $this->attendanceQueryService->formatAttendSummary($attends);
            }
            unset($user);
        }

        if (! empty($temporaryStudents)) {
            $temp_ids = array_column($temporaryStudents, 'ID');
            $all_attends = $this->attendanceQueryService->fetchUsersMonthAttend(
                $temp_ids,
                $class_id,
                $month,
                $year,
                $temporaryRole
            );
            foreach ($temporaryStudents as &$user) {
                $attends = $all_attends[$user['ID']] ?? [];
                $user['attendance_summary'] = $this->attendanceQueryService->formatAttendSummary($attends);
            }
            unset($user);
        }
    }

    /**
     * 多班一次補上 attendance_summary（對齊兩次 {@see attachMonthSummaryToClassUsers}：學生+轉班、老師），
     * 使用 {@see AttendanceQueryService::fetchUsersMonthAttendByClassMonths} 依年度分組，避免每班 N+1 查詢。
     *
     * @param  list<array{
     *     teachers: list<array>,
     *     students: list<array>,
     *     temporaryStudents: list<array>,
     *     class_id: int|string,
     *     month: string,
     *     year: int|string|null
     * }>  $rows  以參考傳入，會就地寫入各 user 的 attendance_summary
     */
    public function attachMonthSummariesForMultipleClasses(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $cmsStudent = [];
        $cmsTransfer = [];
        $cmsTeacher = [];
        foreach ($rows as $row) {
            $cid = $row['class_id'];
            $month = trim((string) ($row['month'] ?? ''));
            $year = $row['year'] ?? null;
            if ($month === '' || $cid === '' || $cid === null) {
                continue;
            }
            foreach ($row['students'] ?? [] as $u) {
                $cmsStudent[] = [
                    'user_id' => (int) ($u['ID'] ?? 0),
                    'class_id' => $cid,
                    'month' => $month,
                    'year' => $year,
                ];
            }
            foreach ($row['temporaryStudents'] ?? [] as $u) {
                $cmsTransfer[] = [
                    'user_id' => (int) ($u['ID'] ?? 0),
                    'class_id' => $cid,
                    'month' => $month,
                    'year' => $year,
                ];
            }
            foreach ($row['teachers'] ?? [] as $u) {
                $cmsTeacher[] = [
                    'user_id' => (int) ($u['ID'] ?? 0),
                    'class_id' => $cid,
                    'month' => $month,
                    'year' => $year,
                ];
            }
        }

        $attStudent = $this->fetchUsersMonthAttendMergedByYear($cmsStudent, 'student');
        $attTransfer = $this->fetchUsersMonthAttendMergedByYear($cmsTransfer, 'student_transfer');
        $attTeacher = $this->fetchUsersMonthAttendMergedByYear($cmsTeacher, null);

        foreach ($rows as &$row) {
            $cid = (int) $row['class_id'];
            $m = trim((string) ($row['month'] ?? ''));

            foreach ($row['students'] as &$user) {
                $uid = (int) ($user['ID'] ?? 0);
                $attends = $attStudent[$uid][$cid][$m] ?? [];
                $user['attendance_summary'] = $this->attendanceQueryService->formatAttendSummary($attends);
            }
            unset($user);

            foreach ($row['temporaryStudents'] as &$user) {
                $uid = (int) ($user['ID'] ?? 0);
                $attends = $attTransfer[$uid][$cid][$m] ?? [];
                $user['attendance_summary'] = $this->attendanceQueryService->formatAttendSummary($attends);
            }
            unset($user);

            foreach ($row['teachers'] as &$user) {
                $uid = (int) ($user['ID'] ?? 0);
                $attends = $attTeacher[$uid][$cid][$m] ?? [];
                $user['attendance_summary'] = $this->attendanceQueryService->formatAttendSummary($attends);
            }
            unset($user);
        }
        unset($row);
    }

    /**
     * @param  list<array{user_id: int, class_id: mixed, month: string, year: mixed}>  $classMonths
     * @return array<int, array<int, array<string, array<string, string>>>>
     */
    private function fetchUsersMonthAttendMergedByYear(array $classMonths, ?string $role): array
    {
        $classMonths = array_values(array_filter($classMonths, function ($cm) {
            return ! empty($cm['class_id'])
                && ! empty(trim((string) ($cm['month'] ?? '')))
                && (int) ($cm['user_id'] ?? 0) > 0;
        }));
        if ($classMonths === []) {
            return [];
        }

        $byYear = [];
        foreach ($classMonths as $cm) {
            $y = (int) ($cm['year'] ?? date('Y'));
            $byYear[$y][] = $cm;
        }

        $merged = [];
        foreach ($byYear as $year => $cms) {
            $uids = array_values(array_unique(array_column($cms, 'user_id')));
            $chunk = $this->attendanceQueryService->fetchUsersMonthAttendByClassMonths($uids, $cms, $role);
            foreach ($chunk as $uid => $byCid) {
                $uid = (int) $uid;
                foreach ($byCid as $cid => $byMonth) {
                    $cid = (int) $cid;
                    foreach ($byMonth as $monthKey => $attends) {
                        $merged[$uid][$cid][$monthKey] = $attends;
                    }
                }
            }
        }

        return $merged;
    }

    public function attachMonthSummaryToMixedUsers(
        &$class_users,
        $class_id,
        string $month,
        $year,
        $post_date = null
    ): void {
        $all_user_ids = array_column($class_users, 'ID');
        if (empty($all_user_ids)) {
            return;
        }

        $all_attends = $this->attendanceQueryService->fetchUsersMonthAttend(
            $all_user_ids,
            $class_id,
            $month,
            $year
        );

        foreach ($class_users as &$user) {
            $attends = $all_attends[$user['ID']] ?? [];
            $user['attendance'] = $this->attendanceQueryService->formatAttendSummary($attends);
            if ($post_date !== null) {
                $user['postday'] = $attends[$post_date] ?? '';
            }
        }
        unset($user);
    }

    public function buildUserMonthSummary($userId, $classId, $month, $year = null, $filter_month_num = null, $role = null): array
    {
        if (empty($userId) || empty($classId) || empty($month)) {
            Log::warning('[buildUserMonthSummary] Invalid parameters', [
                'user_id' => $userId,
                'class_id' => $classId,
                'month' => $month,
                'year' => $year,
            ]);
            return ['attends' => [], 'attends_text' => []];
        }

        $month = $this->normalizeMonthFormat($month);
        $year = $year ?: date('Y');

        $attends = $this->attendanceQueryService->fetchUserMonthAttend($userId, $classId, $month, $year, $role);

        if ($filter_month_num !== null && $filter_month_num >= 1 && $filter_month_num <= 12) {
            $attends = array_filter($attends, function ($status, $date) use ($filter_month_num) {
                return (int) date('n', strtotime($date)) === (int) $filter_month_num;
            }, ARRAY_FILTER_USE_BOTH);
        }

        $attends_text = $this->attendanceQueryService->formatAttendSummary($attends);

        // 狀態分佈統計
        $stats = ['present' => 0, 'late' => 0, 'absent' => 0, 'clear' => 0];
        foreach ($attends as $date => $status) {
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }
        Log::debug('[buildUserMonthSummary]', [
            'user_id' => $userId,
            'class_id' => $classId,
            'month' => $month,
            'year' => $year,
            'attends_count' => count($attends),
            'stats' => $stats,
        ]);

        return ['attends' => $attends, 'attends_text' => $attends_text];
    }

    public function buildUserSummariesByClassMonths($userId, array $classMonths, array $classesRegistry, $role = null): array
    {
        if (count($classMonths) > 3) {
            $allCms = [];
            foreach ($classMonths as $cm) {
                if (empty($cm['class_id']) || empty($cm['month'])) {
                    continue;
                }
                $allCms[] = [
                    'user_id' => $userId,
                    'class_id' => $cm['class_id'],
                    'month' => $this->normalizeMonthFormat($cm['month']),
                    'year' => $cm['class_year'] ?? null,
                ];
            }
            $allAttends = $this->attendanceQueryService->fetchUsersMonthAttendByClassMonths([$userId], $allCms, $role);
            $prepared = [];
            foreach ($classMonths as $cm) {
                if (empty($cm['class_id']) || empty($cm['month'])) {
                    continue;
                }
                $normMonth = $this->normalizeMonthFormat($cm['month']);
                $attends = $allAttends[$userId][$cm['class_id']][$normMonth] ?? [];
                $filterMonthNum = $cm['filter_month_num'] ?? null;
                if ($filterMonthNum !== null && $filterMonthNum >= 1 && $filterMonthNum <= 12) {
                    $attends = array_filter($attends, function ($status, $date) use ($filterMonthNum) {
                        return (int) date('n', strtotime($date)) === (int) $filterMonthNum;
                    }, ARRAY_FILTER_USE_BOTH);
                }
                $attends_text = $this->attendanceQueryService->formatAttendSummary($attends);
                $prepared[] = [
                    'class_id' => $cm['class_id'],
                    'class_name' => $classesRegistry[$cm['class_id']]['class_name'] ?? '',
                    'month' => $cm['month'],
                    'attends_text' => $attends_text,
                ];
            }
            Log::debug('[buildUserSummariesByClassMonths.batch]', [
                'user_id' => $userId,
                'class_months_count' => count($classMonths),
                'prepared_count' => count($prepared),
            ]);
            return $prepared;
        }

        $prepared = [];
        foreach ($classMonths as $cm) {
            if (empty($cm['class_id']) || empty($cm['month'])) {
                continue;
            }
            $filterMonthNum = $cm['filter_month_num'] ?? null;
            $summary = $this->buildUserMonthSummary($userId, $cm['class_id'], $cm['month'], $cm['class_year'] ?? null, $filterMonthNum, $role);
            $prepared[] = [
                'class_id' => $cm['class_id'],
                'class_name' => $classesRegistry[$cm['class_id']]['class_name'] ?? '',
                'month' => $cm['month'],
                'attends_text' => $summary['attends_text'],
            ];
        }
        Log::debug('[buildUserSummariesByClassMonths]', [
            'user_id' => $userId,
            'class_months_count' => count($classMonths),
            'prepared_count' => count($prepared),
        ]);
        return $prepared;
    }

    private function normalizeMonthFormat($month): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            return date('n月', strtotime($month . '-01'));
        }

        return $month;
    }
}
