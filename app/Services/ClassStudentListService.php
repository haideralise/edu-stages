<?php

/**
 * Port of edu2 DebugClassService — 泳班學員列表查詢服務.
 * Reads edu_order, edu_class_user, edu_class_user_days directly (no StudentPaymentServiceCommon).
 */

namespace App\Services;

use App\Models\EduAttendance;
use App\Models\EduClassUser;
use App\Models\EduClassUserDays;
use App\Models\EduOrder;
use App\Models\WpUser;
use App\Services\Common\AttendanceQueryService;

class ClassStudentListService
{
    public function __construct(
        private readonly AttendanceQueryService $attendanceQueryService
    ) {
    }

    /**
     * 檢查訂單月份是否匹配班級月份（支持跨月格式）
     */
    public function isMonthMatchedForClassMonth($order_month, $class_month): bool
    {
        if (empty($order_month) || empty($class_month)) {
            return false;
        }
        if ($order_month === $class_month) {
            return true;
        }
        if (strpos($class_month, '-') !== false) {
            $class_months = explode('-', $class_month);
            $class_start = trim($class_months[0]);
            $class_end = trim(end($class_months));
            if (strpos($order_month, '-') === false) {
                return ($order_month === $class_start || $order_month === $class_end);
            }
            $order_months = explode('-', $order_month);
            $order_start = trim($order_months[0]);
            $order_end = trim(end($order_months));

            return $order_start === $class_start || $order_start === $class_end
                || $order_end === $class_start || $order_end === $class_end;
        }
        if (strpos($order_month, '-') !== false) {
            $order_months = explode('-', $order_month);

            return in_array($class_month, array_map('trim', $order_months));
        }

        return $order_month === $class_month;
    }

    /**
     * SQL (TRIM(month) = X OR month LIKE 'X-%' OR month LIKE '%-X') on a stored month value.
     */
    private function storedMonthMatchesPeriodClause(?string $storedMonth, string $periodMonth): bool
    {
        $periodMonth = trim($periodMonth);
        $storedMonth = trim((string) ($storedMonth ?? ''));
        if ($periodMonth === '') {
            return false;
        }

        return $storedMonth === $periodMonth
            || str_starts_with($storedMonth, $periodMonth . '-')
            || str_ends_with($storedMonth, '-' . $periodMonth);
    }

    private function scopeWhereMonthMatchesLabel($query, string $column, string $monthText): void
    {
        $monthText = trim($monthText);
        $query->where(function ($q) use ($column, $monthText) {
            $q->whereRaw('TRIM(' . $column . ') = ?', [$monthText])
                ->orWhere($column, 'like', $monthText . '-%')
                ->orWhere($column, 'like', '%-' . $monthText);
        });
    }

    /**
     * Warm {@see AttendanceQueryService::$classesCache} for all class_ids in salary / list rows (avoids N+1 in buildDaysDistributionText).
     *
     * @param  array<int, array<string, mixed>>  $class_rows  Rows with class_id (e.g. getClassesByCoachYearMonth classes)
     */
    public function preloadEduClassesFromClassRows(array $class_rows): void
    {
        $ids = [];
        foreach ($class_rows as $row) {
            $cid = (int) ($row['class_id'] ?? 0);
            if ($cid > 0) {
                $ids[] = $cid;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids !== []) {
            $this->attendanceQueryService->preloadEduClassesByIds($ids);
        }
    }

    /**
     * @return array ['classes' => [...], 'student_info' => [...], ...]
     */
    public function getClassesByCoachYearMonth($coach_id, $year, $month): array
    {
        $coach_id = (int) $coach_id;
        $year = (int) $year;
        $month = (int) $month;
        $monthText = $month . '月';

        $needle = '%"' . $coach_id . '"%';

        $current_classes = EduClassUser::query()
            ->leftJoin('wp_3x_edu_class', 'wp_3x_edu_class.class_id', '=', 'wp_3x_edu_class_user.class_id')
            ->where('wp_3x_edu_class_user.teacher', 'like', $needle)
            ->where('wp_3x_edu_class_user.class_year', $year)
            ->where(function ($q) use ($monthText) {
                $this->scopeWhereMonthMatchesLabel($q, 'wp_3x_edu_class_user.month', $monthText);
            })
            ->orderBy('wp_3x_edu_class.class_name')
            ->select([
                'wp_3x_edu_class_user.*',
                'wp_3x_edu_class.class_name',
                'wp_3x_edu_class.date_time',
            ])
            ->get()
            ->map(fn (EduClassUser $cu) => $this->classUserRowToLegacyArray($cu))
            ->values()
            ->all();

        if ($current_classes === []) {
            return [
                'classes' => [],
                'student_info' => [],
                'student_days_map' => [],
                'student_attendance_map' => [],
                'student_order_map' => [],
                'student_prev_next_map' => [],
                'student_renewal_map' => [],
                'total_classes' => 0,
                'total_students' => 0,
            ];
        }

        $all_student_ids = [];
        foreach ($current_classes as $row) {
            $students = json_decode($row['student'] ?? '[]', true);
            $transfers = json_decode($row['student_transfer'] ?? '[]', true);
            $makeup = json_decode($row['student_makeup'] ?? '[]', true);
            foreach (array_merge(
                is_array($students) ? $students : [],
                is_array($transfers) ? $transfers : [],
                is_array($makeup) ? $makeup : []
            ) as $sid) {
                if (! empty($sid) && is_numeric($sid)) {
                    $all_student_ids[] = (int) $sid;
                }
            }
        }
        $all_student_ids = array_values(array_unique($all_student_ids));

        $student_info = $this->getStudentsBatch($all_student_ids);
        $class_ids = array_values(array_unique(array_map(fn ($r) => (int) $r['class_id'], $current_classes)));
        $student_days_map = $this->getStudentDaysMap($all_student_ids, $year, $class_ids);
        $student_attendance_map = $this->getStudentAttendanceMap($all_student_ids, $year, $class_ids);
        $student_order_map = $this->getStudentOrderMap($current_classes, $all_student_ids);

        $classPeriods = [];
        foreach ($current_classes as $row) {
            $classPeriods[] = [
                'class_id' => (int) $row['class_id'],
                'month' => $row['class_month'] ?? '',
                'year' => (int) ($row['class_year'] ?? $year),
            ];
        }
        $prevNextBatch = $this->getClassPrevNextPeriodBatch($classPeriods);
        foreach ($current_classes as $i => $row) {
            $cid = (int) $row['class_id'];
            $current_classes[$i]['prev_period'] = $prevNextBatch['prev'][$cid] ?? null;
            $current_classes[$i]['next_period'] = $prevNextBatch['next'][$cid] ?? null;
        }

        $student_prev_next_map = $this->getStudentPrevNextMap($current_classes);

        $renewal_result = $this->getRenewalStatusMap($coach_id, $current_classes, $student_order_map, false);
        $student_renewal_map = $renewal_result['map'];

        return [
            'classes' => $current_classes,
            'student_info' => $student_info,
            'student_days_map' => $student_days_map,
            'student_attendance_map' => $student_attendance_map,
            'student_order_map' => $student_order_map,
            'student_prev_next_map' => $student_prev_next_map,
            'student_renewal_map' => $student_renewal_map,
            'total_classes' => count($current_classes),
            'total_students' => count($all_student_ids),
        ];
    }

    private function classUserRowToLegacyArray(EduClassUser $cu): array
    {
        $enc = fn ($v) => json_encode(
            is_array($v) ? $v : (json_decode((string) $v, true) ?: []),
            JSON_UNESCAPED_UNICODE
        );

        return [
            'id' => (int) $cu->id,
            'class_id' => (int) $cu->class_id,
            'class_name' => (string) ($cu->getAttribute('class_name') ?? $cu->eduClass?->class_name ?? ''),
            'date_time' => (string) ($cu->getAttribute('date_time') ?? $cu->eduClass?->date_time ?? ''),
            'class_month' => (string) ($cu->getRawOriginal('month') ?? $cu->month ?? ''),
            'class_days' => (string) ($cu->getRawOriginal('days') ?? $cu->days ?? ''),
            'student' => $enc($cu->student),
            'student_transfer' => $enc($cu->student_transfer),
            'student_makeup' => $enc($cu->student_makeup),
            'class_year' => (int) $cu->class_year,
            'sort' => $cu->sort,
        ];
    }

    /**
     * @param  array<int>  $student_ids
     * @return array<int, string>
     */
    private function getStudentsBatch(array $student_ids): array
    {
        $result = [];
        $student_ids = array_values(array_filter(array_map('intval', $student_ids), fn ($id) => $id > 0));
        if ($student_ids === []) {
            return $result;
        }
        $rows = WpUser::query()->whereIn('ID', $student_ids)->get(['ID', 'display_name']);
        foreach ($rows as $r) {
            $result[(int) $r->ID] = $r->display_name ?? '未知';
        }

        return $result;
    }

    /**
     * @param  array<int>  $student_ids
     * @param  array<int>  $class_ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function getStudentDaysMap(array $student_ids, int $year, array $class_ids = []): array
    {
        $map = [];
        $student_ids = array_values(array_filter(array_map('intval', $student_ids), fn ($id) => $id > 0));
        if ($student_ids === []) {
            return $map;
        }
        $class_ids = array_values(array_filter(array_map('intval', $class_ids), fn ($id) => $id > 0));

        $q = EduClassUserDays::query()
            ->whereIn('user_id', $student_ids)
            ->where('class_year', $year);
        if ($class_ids !== []) {
            $q->whereIn('class_id', $class_ids);
        }
        foreach ($q->get(['class_id', 'user_id', 'days', 'month']) as $r) {
            $cid = (int) $r->class_id;
            $uid = (int) $r->user_id;
            $m = (string) ($r->getRawOriginal('month') ?? $r->month ?? '');
            if (! isset($map[$cid])) {
                $map[$cid] = [];
            }
            if (! isset($map[$cid][$uid])) {
                $map[$cid][$uid] = [];
            }
            $map[$cid][$uid][$m] = $r->getRawOriginal('days') ?? $r->days;
        }

        return $map;
    }

    /**
     * @param  array<int>  $student_ids
     * @param  array<int>  $class_ids
     * @return array<int, array<int, array<int, array{late: int, absent: int}>>>
     */
    private function getStudentAttendanceMap(array $student_ids, int $year, array $class_ids = []): array
    {
        $map = [];
        $student_ids = array_values(array_filter(array_map('intval', $student_ids), fn ($id) => $id > 0));
        if ($student_ids === []) {
            return $map;
        }
        $class_ids = array_values(array_filter(array_map('intval', $class_ids), fn ($id) => $id > 0));

        $q = EduAttendance::query()
            ->whereIn('user_id', $student_ids)
            ->where('class_year', $year)
            ->whereIn('attendance', ['late', 'absent']);
        if ($class_ids !== []) {
            $q->whereIn('class_id', $class_ids);
        }
        $rows = $q->get(['class_id', 'user_id', 'attendance', 'date']);
        foreach ($rows as $r) {
            $cid = (int) $r->class_id;
            $uid = (int) $r->user_id;
            $m = (int) $r->date->month;
            if ($m < 1 || $m > 12) {
                continue;
            }
            if (! isset($map[$cid])) {
                $map[$cid] = [];
            }
            if (! isset($map[$cid][$uid])) {
                $map[$cid][$uid] = [];
            }
            if (! isset($map[$cid][$uid][$m])) {
                $map[$cid][$uid][$m] = ['late' => 0, 'absent' => 0];
            }
            $raw = $r->getRawOriginal('attendance') ?? '';
            if ($raw === 'late') {
                $map[$cid][$uid][$m]['late']++;
            } elseif ($raw === 'absent') {
                $map[$cid][$uid][$m]['absent']++;
            }
        }

        return $map;
    }

    /**
     * Bulk load orders then apply the same per-(student, class) rules as edu2.
     *
     * @param  array<int, array<string, mixed>>  $current_classes
     * @param  array<int>  $all_student_ids
     * @return array<string, array{order_date: int, amount: float, is_transfer_order: bool}>
     */
    private function getStudentOrderMap(array $current_classes, array $all_student_ids): array
    {
        $map = [];
        $all_student_ids = array_values(array_unique(array_filter(array_map('intval', $all_student_ids), fn ($id) => $id > 0)));
        if ($all_student_ids === [] || $current_classes === []) {
            return $map;
        }

        $years = array_values(array_unique(array_map(fn ($r) => (int) ($r['class_year'] ?? 0), $current_classes)));
        $years = array_values(array_filter($years, fn ($y) => $y > 0));
        if ($years === []) {
            return $map;
        }

        /** @var Collection<int, EduOrder> $allOrders */
        $allOrders = EduOrder::query()
            ->validOnly()
            ->whereIn('user_id', $all_student_ids)
            ->whereIn('class_year', $years)
            ->orderByDesc('order_date')
            ->get();

        $byUserId = [];
        foreach ($allOrders as $o) {
            $byUserId[$o->user_id][] = $o;
        }

        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            $class_month = $class_row['class_month'];
            $class_year = (int) $class_row['class_year'];

            $students_in_class = array_merge(
                json_decode($class_row['student'] ?? '[]', true) ?: [],
                json_decode($class_row['student_transfer'] ?? '[]', true) ?: [],
                json_decode($class_row['student_makeup'] ?? '[]', true) ?: []
            );

            foreach ($students_in_class as $sid) {
                $sid = (int) $sid;
                if (! in_array($sid, $all_student_ids, true)) {
                    continue;
                }

                $key = "{$sid}_{$class_id}";
                $ordersForUser = $byUserId[$sid] ?? [];

                foreach ($ordersForUser as $order) {
                    if ((int) $order->class_year !== $class_year) {
                        continue;
                    }
                    if ((int) $order->class_id !== $class_id) {
                        continue;
                    }
                    $om = (string) ($order->getRawOriginal('month') ?? $order->month ?? '');
                    if (! $this->storedMonthMatchesPeriodClause($om, (string) $class_month)) {
                        continue;
                    }
                    if (! $this->isMonthMatchedForClassMonth($om, (string) $class_month)) {
                        continue;
                    }
                    $map[$key] = [
                        'order_date' => (int) ($order->order_date ?? 0),
                        'amount' => (float) ($order->amount ?? 0),
                        'is_transfer_order' => false,
                    ];
                    break;
                }

                if (! isset($map[$key])) {
                    foreach ($ordersForUser as $order) {
                        if ((int) $order->class_year !== $class_year) {
                            continue;
                        }
                        $cid = $order->class_id;
                        if ($cid !== null && (int) $cid === $class_id) {
                            continue;
                        }
                        $om = (string) ($order->getRawOriginal('month') ?? $order->month ?? '');
                        if (! $this->storedMonthMatchesPeriodClause($om, (string) $class_month)) {
                            continue;
                        }
                        if (! $this->isMonthMatchedForClassMonth($om, (string) $class_month)) {
                            continue;
                        }
                        $map[$key] = [
                            'order_date' => (int) ($order->order_date ?? 0),
                            'amount' => (float) ($order->amount ?? 0),
                            'is_transfer_order' => true,
                        ];
                        break;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>|int  $student_days_map
     */
    public function buildDaysDistributionText(
        $student_id,
        $class_month,
        $student_days_map,
        $year,
        $is_transfer = false,
        $class_id = null,
        $attendance_map = [],
        $class_days_str = ''
    ): string {
        $student_id = (int) $student_id;
        $year = (int) $year;
        $class_months = ($class_month && strpos($class_month, '-') !== false)
            ? array_map('trim', explode('-', $class_month))
            : [trim((string) $class_month)];
        $parts = [];

        foreach ($class_months as $m_text) {
            $m_text = trim($m_text);
            if ($m_text === '') {
                continue;
            }
            $target_month_num = (int) str_replace('月', '', $m_text);
            $days_str = $student_days_map[$student_id][$m_text] ?? $student_days_map[$student_id][$class_month] ?? null;
            $late = (int) ($attendance_map[$student_id][$target_month_num]['late'] ?? 0);
            $absent = (int) ($attendance_map[$student_id][$target_month_num]['absent'] ?? 0);
            $suffix = '';
            if ($late > 0) {
                $suffix .= ", {$late}堂(請假)";
            }
            if ($absent > 0) {
                $suffix .= ", {$absent}堂(取消)";
            }

            if (! empty($days_str) && trim((string) $days_str) !== '' && $days_str !== '[]') {
                $raw_dates = array_filter(array_map('trim', explode(',', (string) $days_str)));
                $filtered = [];
                foreach ($raw_dates as $d) {
                    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
                        if ((int) $m[1] === $year && (int) $m[2] === $target_month_num) {
                            $filtered[] = $d;
                        }
                    }
                }
                $count = count($filtered);
                $parts[] = $count > 0 ? "{$m_text}:{$count}堂{$suffix}" : "{$m_text}:-{$suffix}";
            } else {
                if ($is_transfer) {
                    $parts[] = "{$m_text}:-{$suffix}";
                } elseif ($class_id !== null) {
                    $classUserData = [
                        'class_id' => $class_id,
                        'month' => $m_text,
                        'class_year' => $year,
                        'days' => $class_days_str,
                    ];
                    $baseDays = $this->attendanceQueryService->getClassUserDaysArray($classUserData);
                    $enrollCount = is_array($baseDays) ? count($baseDays) : 0;
                    $parts[] = $enrollCount > 0 ? "{$m_text}:全期 ({$enrollCount}堂){$suffix}" : "{$m_text}:全期{$suffix}";
                } else {
                    $parts[] = "{$m_text}:全期{$suffix}";
                }
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseMonthToStartEnd($month_str): array
    {
        if (empty($month_str) || ! is_string($month_str)) {
            return [1, 1];
        }
        $month_str = trim($month_str);
        if (strpos($month_str, '-') !== false) {
            $parts = array_map('trim', explode('-', $month_str));
            $first = (int) str_replace('月', '', $parts[0] ?? '1');
            $last = (int) str_replace('月', '', end($parts));
            $first = max(1, min(12, $first));
            $last = max(1, min(12, $last));

            return [$first, $last];
        }
        $m = (int) str_replace('月', '', $month_str);
        $m = max(1, min(12, $m));

        return [$m, $m];
    }

    /**
     * @param  array<int, array{class_id: int, month: string, year: int}>  $classPeriods
     * @return array{prev: array<int, array{month: string, year: int}|null>, next: array<int, array{month: string, year: int}|null>}
     */
    private function getClassPrevNextPeriodBatch(array $classPeriods): array
    {
        $prev_map = [];
        $next_map = [];
        if ($classPeriods === []) {
            return ['prev' => $prev_map, 'next' => $next_map];
        }
        $class_ids = array_values(array_unique(array_filter(array_map(fn ($cp) => (int) ($cp['class_id'] ?? 0), $classPeriods))));
        if ($class_ids === []) {
            return ['prev' => $prev_map, 'next' => $next_map];
        }
        $years = array_values(array_unique(array_filter(array_map(fn ($cp) => (int) ($cp['year'] ?? 0), $classPeriods))));
        if ($years === []) {
            return ['prev' => $prev_map, 'next' => $next_map];
        }
        $year_min = min($years);
        $year_max = max($years);

        $all_rows = EduClassUser::query()
            ->whereIn('class_id', $class_ids)
            ->where('class_year', '>=', $year_min - 1)
            ->where('class_year', '<=', $year_max + 1)
            ->whereNotNull('month')
            ->where('month', '!=', '')
            ->orderBy('class_id')
            ->orderBy('class_year')
            ->orderBy('month')
            ->get(['id', 'class_id', 'month', 'class_year'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'class_id' => $r->class_id,
                'month' => $r->getRawOriginal('month') ?? $r->month,
                'class_year' => $r->class_year,
            ])
            ->all();

        $by_class = [];
        foreach ($all_rows as $r) {
            $cid = (int) $r['class_id'];
            if (! isset($by_class[$cid])) {
                $by_class[$cid] = [];
            }
            $by_class[$cid][] = $r;
        }
        foreach ($classPeriods as $cp) {
            $cid = (int) ($cp['class_id'] ?? 0);
            $month = trim((string) ($cp['month'] ?? ''));
            $cpy = (int) ($cp['year'] ?? 0);
            if ($cid <= 0 || $month === '') {
                continue;
            }
            $prev_map[$cid] = $this->findPrevFromRows($cid, $month, $cpy, $by_class[$cid] ?? []);
            $next_map[$cid] = $this->findNextFromRows($cid, $month, $cpy, $by_class[$cid] ?? []);
        }

        return ['prev' => $prev_map, 'next' => $next_map];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{month: string, year: int}|null
     */
    private function findPrevFromRows(int $class_id, string $current_month, int $current_year, array $rows): ?array
    {
        [$start_month,] = $this->parseMonthToStartEnd($current_month);
        if ($start_month === 1) {
            $prev_month = 12;
            $prev_year = $current_year - 1;
        } else {
            $prev_month = $start_month - 1;
            $prev_year = $current_year;
        }
        foreach (array_reverse($rows) as $r) {
            $r_year = (int) $r['class_year'];
            $r_month = trim((string) ($r['month'] ?? ''));
            [, $re] = $this->parseMonthToStartEnd($r_month);
            if ($r_year === $prev_year && $re === $prev_month) {
                return ['month' => $r_month, 'year' => $r_year];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{month: string, year: int}|null
     */
    private function findNextFromRows(int $class_id, string $current_month, int $current_year, array $rows): ?array
    {
        [$start_month, $end_month] = $this->parseMonthToStartEnd($current_month);
        $is_range = ($start_month !== $end_month);
        if ($end_month === 12) {
            $expected_starts = [1];
            $next_year = $current_year + 1;
        } elseif ($is_range) {
            $expected_starts = [$end_month, $end_month + 1];
            $next_year = $current_year;
        } else {
            $expected_starts = [$end_month + 1];
            $next_year = $current_year;
        }
        foreach ($rows as $r) {
            $r_year = (int) $r['class_year'];
            $r_month = trim((string) ($r['month'] ?? ''));
            [$rs,] = $this->parseMonthToStartEnd($r_month);
            if ($r_year === $next_year && in_array($rs, $expected_starts, true)) {
                return ['month' => $r_month, 'year' => $r_year];
            }
        }

        return null;
    }

    /**
     * @return array{month: string, year: int}|null
     */
    public function getClassPreviousPeriod($class_id, $current_month, $current_year): ?array
    {
        $class_id = (int) $class_id;
        $current_year = (int) $current_year;
        if ($class_id <= 0) {
            return null;
        }
        [$start_month,] = $this->parseMonthToStartEnd($current_month);
        if ($start_month === 1) {
            $prev_month = 12;
            $prev_year = $current_year - 1;
        } else {
            $prev_month = $start_month - 1;
            $prev_year = $current_year;
        }
        $prev_text = $prev_month . '月';

        $row = EduClassUser::query()
            ->where('class_id', $class_id)
            ->where('class_year', $prev_year)
            ->where(function ($q) use ($prev_text) {
                $q->whereRaw('TRIM(month) = ?', [trim($prev_text)])
                    ->orWhere('month', 'like', '%-' . $prev_text);
            })
            ->orderByDesc('id')
            ->first(['month', 'class_year']);

        if ($row === null) {
            return null;
        }

        return [
            'month' => trim((string) ($row->getRawOriginal('month') ?? $row->month ?? '')),
            'year' => (int) ($row->class_year ?? $prev_year),
        ];
    }

    /**
     * @return array{month: string, year: int}|null
     */
    public function getClassNextPeriod($class_id, $current_month, $current_year): ?array
    {
        $class_id = (int) $class_id;
        $current_year = (int) $current_year;
        if ($class_id <= 0) {
            return null;
        }
        [$start_month, $end_month] = $this->parseMonthToStartEnd($current_month);
        $is_range = ($start_month !== $end_month);
        if ($end_month === 12) {
            $next_months = [1];
            $next_year = $current_year + 1;
        } elseif ($is_range) {
            $next_months = [$end_month, $end_month + 1];
            $next_year = $current_year;
        } else {
            $next_months = [$end_month + 1];
            $next_year = $current_year;
        }

        $row = EduClassUser::query()
            ->where('class_id', $class_id)
            ->where('class_year', $next_year)
            ->where(function ($outer) use ($next_months) {
                foreach ($next_months as $m) {
                    $t = $m . '月';
                    $outer->orWhere(function ($q) use ($t) {
                        $q->whereRaw('TRIM(month) = ?', [trim($t)])
                            ->orWhere('month', 'like', $t . '-%');
                    });
                }
            })
            ->orderBy('id')
            ->first(['month', 'class_year']);

        if ($row === null) {
            return null;
        }

        return [
            'month' => trim((string) ($row->getRawOriginal('month') ?? $row->month ?? '')),
            'year' => (int) ($row->class_year ?? $next_year),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function getClassPeriodStudentIds(int $class_id, string $period_month, int $period_year): array
    {
        $class_id = (int) $class_id;
        $period_year = (int) $period_year;
        if ($class_id <= 0 || $period_month === '') {
            return [];
        }
        $row = EduClassUser::query()
            ->where('class_id', $class_id)
            ->where('class_year', $period_year)
            ->where(function ($q) use ($period_month) {
                $this->scopeWhereMonthMatchesLabel($q, 'month', trim($period_month));
            })
            ->orderBy('id')
            ->first(['student', 'student_transfer']);

        if ($row === null) {
            return [];
        }
        $students = is_array($row->student) ? $row->student : (json_decode((string) $row->getRawOriginal('student'), true) ?: []);
        $transfers = is_array($row->student_transfer) ? $row->student_transfer : (json_decode((string) $row->getRawOriginal('student_transfer'), true) ?: []);
        $ids = [];
        foreach (array_merge($students, $transfers) as $sid) {
            if (! empty($sid) && is_numeric($sid)) {
                $ids[] = (int) $sid;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Batch version: keys "class_id|period_month|period_year" => student id list (same as getClassPeriodStudentIds).
     *
     * @param  array<int, array{class_id: int, month: string, year: int}>  $periods
     * @return array<string, array<int, int>>
     */
    private function getClassPeriodStudentIdsBatch(array $periods): array
    {
        $out = [];
        $unique = [];
        foreach ($periods as $p) {
            $cid = (int) ($p['class_id'] ?? 0);
            $pm = trim((string) ($p['month'] ?? ''));
            $py = (int) ($p['year'] ?? 0);
            if ($cid <= 0 || $pm === '' || $py <= 0) {
                continue;
            }
            $k = $cid . '|' . $pm . '|' . $py;
            $unique[$k] = ['class_id' => $cid, 'month' => $pm, 'year' => $py];
        }
        if ($unique === []) {
            return $out;
        }

        $rows = EduClassUser::query()
            ->where(function ($outer) use ($unique) {
                foreach ($unique as $spec) {
                    $outer->orWhere(function ($q) use ($spec) {
                        $q->where('class_id', $spec['class_id'])
                            ->where('class_year', $spec['year']);
                        $this->scopeWhereMonthMatchesLabel($q, 'month', $spec['month']);
                    });
                }
            })
            ->orderBy('id')
            ->get(['class_id', 'class_year', 'month', 'student', 'student_transfer']);

        foreach ($unique as $k => $spec) {
            $out[$k] = [];
            foreach ($rows as $row) {
                if ((int) $row->class_id !== $spec['class_id']) {
                    continue;
                }
                if ((int) $row->class_year !== $spec['year']) {
                    continue;
                }
                $stored = trim((string) ($row->getRawOriginal('month') ?? $row->month ?? ''));
                if (! $this->storedMonthMatchesPeriodClause($stored, $spec['month'])) {
                    continue;
                }
                $students = is_array($row->student) ? $row->student : (json_decode((string) $row->getRawOriginal('student'), true) ?: []);
                $transfers = is_array($row->student_transfer) ? $row->student_transfer : (json_decode((string) $row->getRawOriginal('student_transfer'), true) ?: []);
                $ids = [];
                foreach (array_merge($students, $transfers) as $sid) {
                    if (! empty($sid) && is_numeric($sid)) {
                        $ids[] = (int) $sid;
                    }
                }
                $out[$k] = array_values(array_unique($ids));
                break;
            }
        }

        return $out;
    }

    /**
     * @return array{student: array<int, int>, transfer: array<int, int>}
     */
    public function getCoachPeriodStudentIds($coach_id, $period_month, $period_year): array
    {
        $coach_id = (int) $coach_id;
        $period_year = (int) $period_year;
        if ($coach_id <= 0 || $period_month === '') {
            return ['student' => [], 'transfer' => []];
        }
        $needle = '%"' . $coach_id . '"%';
        $rows = EduClassUser::query()
            ->where('teacher', 'like', $needle)
            ->where('class_year', $period_year)
            ->where(function ($q) use ($period_month) {
                $this->scopeWhereMonthMatchesLabel($q, 'month', trim($period_month));
            })
            ->get(['student', 'student_transfer']);

        $student_ids = [];
        $transfer_ids = [];
        foreach ($rows as $row) {
            $students = is_array($row->student) ? $row->student : (json_decode((string) $row->getRawOriginal('student'), true) ?: []);
            $transfers = is_array($row->student_transfer) ? $row->student_transfer : (json_decode((string) $row->getRawOriginal('student_transfer'), true) ?: []);
            foreach ($students as $sid) {
                if (! empty($sid) && is_numeric($sid)) {
                    $student_ids[] = (int) $sid;
                }
            }
            foreach ($transfers as $sid) {
                if (! empty($sid) && is_numeric($sid)) {
                    $transfer_ids[] = (int) $sid;
                }
            }
        }

        return [
            'student' => array_values(array_unique($student_ids)),
            'transfer' => array_values(array_unique($transfer_ids)),
        ];
    }

    /**
     * @param  array<int, array{period_month: string, period_year: int, student_ids: array<int>}>  $periods
     * @return array<string, array<int, int>>
     */
    private function getStudentPeriodPaidMapIgnoringClassId(array $periods): array
    {
        $result = [];
        $merged = [];
        foreach ($periods as $p) {
            $pm = $p['period_month'] ?? '';
            $py = (int) ($p['period_year'] ?? 0);
            $student_ids = array_values(array_unique(array_filter(array_map('intval', $p['student_ids'] ?? []), fn ($id) => $id > 0)));
            $pk = $pm . '_' . $py;
            if ($pm === '' || $student_ids === []) {
                $result[$pk] = [];
                continue;
            }
            if (! isset($merged[$pk])) {
                $merged[$pk] = [
                    'period_month' => $pm,
                    'period_year' => $py,
                    'student_ids' => [],
                ];
            }
            $merged[$pk]['student_ids'] = array_values(array_unique(array_merge(
                $merged[$pk]['student_ids'],
                $student_ids
            )));
        }

        $allUids = [];
        foreach ($merged as $m) {
            $allUids = array_merge($allUids, $m['student_ids']);
        }
        $allUids = array_values(array_unique(array_filter($allUids, fn ($id) => $id > 0)));
        if ($allUids === []) {
            foreach ($merged as $pk => $_) {
                if (! isset($result[$pk])) {
                    $result[$pk] = [];
                }
            }

            return $result;
        }

        $orders = EduOrder::query()
            ->validOnly()
            ->whereIn('user_id', $allUids)
            ->get(['user_id', 'class_year', 'month']);

        foreach ($merged as $pk => $spec) {
            $paid_ids = [];
            foreach ($orders as $o) {
                if ((int) $o->class_year !== $spec['period_year']) {
                    continue;
                }
                if (! in_array((int) $o->user_id, $spec['student_ids'], true)) {
                    continue;
                }
                $om = (string) ($o->getRawOriginal('month') ?? $o->month ?? '');
                if (! $this->storedMonthMatchesPeriodClause($om, $spec['period_month'])) {
                    continue;
                }
                $paid_ids[] = (int) $o->user_id;
            }
            $result[$pk] = array_values(array_unique($paid_ids));
        }

        foreach ($periods as $p) {
            $pm = $p['period_month'] ?? '';
            $py = (int) ($p['period_year'] ?? 0);
            $pk = $pm . '_' . $py;
            if (! isset($result[$pk])) {
                $result[$pk] = [];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{class_id: int, period_month: string, period_year: int, student_ids: array<int>, suffix: string}>  $periods_with_students
     * @return array<string, true>
     */
    private function getStudentPeriodPaidMap(array $periods_with_students): array
    {
        $paid_map = [];
        $groups = [];
        foreach ($periods_with_students as $p) {
            $cid = (int) ($p['class_id'] ?? 0);
            $pm = $p['period_month'] ?? '';
            $py = (int) ($p['period_year'] ?? 0);
            $student_ids = array_values(array_filter(array_map('intval', $p['student_ids'] ?? []), fn ($id) => $id > 0));
            $suffix = $p['suffix'] ?? '';
            if ($cid <= 0 || $pm === '' || $student_ids === []) {
                continue;
            }
            $gk = $cid . "\0" . $pm . "\0" . $py;
            if (! isset($groups[$gk])) {
                $groups[$gk] = [
                    'class_id' => $cid,
                    'period_month' => $pm,
                    'period_year' => $py,
                    'student_ids' => [],
                    'suffixes' => [],
                ];
            }
            $groups[$gk]['student_ids'] = array_values(array_unique(array_merge($groups[$gk]['student_ids'], $student_ids)));
            $groups[$gk]['suffixes'][$suffix] = true;
        }

        $allUids = [];
        foreach ($groups as $g) {
            $allUids = array_merge($allUids, $g['student_ids']);
        }
        $allUids = array_values(array_unique($allUids));
        if ($allUids === []) {
            return $paid_map;
        }

        $cids = array_values(array_unique(array_map(fn ($g) => $g['class_id'], $groups)));
        $years = array_values(array_unique(array_map(fn ($g) => $g['period_year'], $groups)));

        $orders = EduOrder::query()
            ->validOnly()
            ->whereIn('user_id', $allUids)
            ->whereIn('class_id', $cids)
            ->whereIn('class_year', $years)
            ->get(['user_id', 'class_id', 'class_year', 'month']);

        foreach ($groups as $g) {
            foreach ($orders as $o) {
                if ((int) $o->class_id !== $g['class_id']) {
                    continue;
                }
                if ((int) $o->class_year !== $g['period_year']) {
                    continue;
                }
                if (! in_array((int) $o->user_id, $g['student_ids'], true)) {
                    continue;
                }
                $om = (string) ($o->getRawOriginal('month') ?? $o->month ?? '');
                if (! $this->storedMonthMatchesPeriodClause($om, $g['period_month'])) {
                    continue;
                }
                $uid = (int) $o->user_id;
                foreach (array_keys($g['suffixes']) as $suffix) {
                    $paid_map["{$uid}_{$g['class_id']}_{$suffix}"] = true;
                }
            }
        }

        return $paid_map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $current_classes
     * @return array<string, array{prev: ?array, next: ?array}>
     */
    public function getStudentPrevNextMap(array $current_classes): array
    {
        $map = [];
        $periods_for_paid = [];
        $batchPeriodSpecs = [];

        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            $prev_period = $class_row['prev_period'] ?? null;
            $next_period = $class_row['next_period'] ?? null;

            if (! empty($prev_period['month'])) {
                $batchPeriodSpecs[] = [
                    'class_id' => $class_id,
                    'month' => $prev_period['month'],
                    'year' => (int) ($prev_period['year'] ?? 0),
                ];
            }
            if (! empty($next_period['month'])) {
                $batchPeriodSpecs[] = [
                    'class_id' => $class_id,
                    'month' => $next_period['month'],
                    'year' => (int) ($next_period['year'] ?? 0),
                ];
            }
        }

        $periodStudentsBatch = $this->getClassPeriodStudentIdsBatch($batchPeriodSpecs);

        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            $prev_period = $class_row['prev_period'] ?? null;
            $next_period = $class_row['next_period'] ?? null;

            $prevKey = $class_id . '|' . trim((string) ($prev_period['month'] ?? '')) . '|' . (int) ($prev_period['year'] ?? 0);
            $nextKey = $class_id . '|' . trim((string) ($next_period['month'] ?? '')) . '|' . (int) ($next_period['year'] ?? 0);

            $prev_student_ids = ! empty($prev_period['month'])
                ? ($periodStudentsBatch[$prevKey] ?? $this->getClassPeriodStudentIds(
                    $class_id,
                    $prev_period['month'],
                    (int) ($prev_period['year'] ?? 0)
                ))
                : [];
            $next_student_ids = ! empty($next_period['month'])
                ? ($periodStudentsBatch[$nextKey] ?? $this->getClassPeriodStudentIds(
                    $class_id,
                    $next_period['month'],
                    (int) ($next_period['year'] ?? 0)
                ))
                : [];

            $students_in_class = array_merge(
                json_decode($class_row['student'] ?? '[]', true) ?: [],
                json_decode($class_row['student_transfer'] ?? '[]', true) ?: [],
                json_decode($class_row['student_makeup'] ?? '[]', true) ?: []
            );

            foreach ($students_in_class as $sid) {
                $sid = (int) $sid;
                if ($sid <= 0) {
                    continue;
                }
                $key = "{$sid}_{$class_id}";
                $map[$key] = [
                    'prev' => null,
                    'next' => null,
                ];

                if (in_array($sid, $prev_student_ids, true) && ! empty($prev_period['month'])) {
                    $map[$key]['prev'] = [
                        'month' => $prev_period['month'],
                        'year' => (int) ($prev_period['year'] ?? 0),
                        'has_paid' => false,
                    ];
                }
                if (in_array($sid, $next_student_ids, true) && ! empty($next_period['month'])) {
                    $map[$key]['next'] = [
                        'month' => $next_period['month'],
                        'year' => (int) ($next_period['year'] ?? 0),
                        'has_paid' => false,
                    ];
                }
            }

            if ($prev_student_ids !== [] && ! empty($prev_period['month'])) {
                $periods_for_paid[] = [
                    'class_id' => $class_id,
                    'period_month' => $prev_period['month'],
                    'period_year' => $prev_period['year'] ?? 0,
                    'student_ids' => $prev_student_ids,
                    'suffix' => 'prev',
                ];
            }
            if ($next_student_ids !== [] && ! empty($next_period['month'])) {
                $periods_for_paid[] = [
                    'class_id' => $class_id,
                    'period_month' => $next_period['month'],
                    'period_year' => $next_period['year'] ?? 0,
                    'student_ids' => $next_student_ids,
                    'suffix' => 'next',
                ];
            }
        }

        $paid_map = $this->getStudentPeriodPaidMap($periods_for_paid);
        foreach ($map as $key => &$info) {
            if (! empty($info['prev']) && ! empty($paid_map["{$key}_prev"])) {
                $info['prev']['has_paid'] = true;
            }
            if (! empty($info['next']) && ! empty($paid_map["{$key}_next"])) {
                $info['next']['has_paid'] = true;
            }
        }
        unset($info);

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $current_classes
     * @param  array<string, array{order_date: int, amount: float, is_transfer_order: bool}>  $student_order_map
     * @return array{map: array<string, bool>, debug: array<string, mixed>}
     */
    public function getRenewalStatusMap($coach_id, array $current_classes, array $student_order_map, $with_debug = false): array
    {
        $map = [];
        $debug = [];
        $coach_id = (int) $coach_id;
        $coach_prev_cache = [];

        foreach ($current_classes as $class_row) {
            $prev_period = $class_row['prev_period'] ?? null;
            $period_key = $prev_period ? ($prev_period['month'] . '_' . ($prev_period['year'] ?? 0)) : '';

            if (! isset($coach_prev_cache[$period_key]) && ! empty($prev_period['month'])) {
                $coach_prev_cache[$period_key] = $this->getCoachPeriodStudentIds(
                    $coach_id,
                    $prev_period['month'],
                    $prev_period['year'] ?? 0
                );
            }
        }

        $periods_for_paid_merged = [];
        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            $prev_period = $class_row['prev_period'] ?? null;
            $period_key = $prev_period ? ($prev_period['month'] . '_' . ($prev_period['year'] ?? 0)) : '';
            $coach_prev = $coach_prev_cache[$period_key] ?? ['student' => [], 'transfer' => []];

            $students = json_decode($class_row['student'] ?? '[]', true) ?: [];
            $transfers = json_decode($class_row['student_transfer'] ?? '[]', true) ?: [];
            $makeup_ids = array_filter(array_map('intval', json_decode($class_row['student_makeup'] ?? '[]', true) ?: []), fn ($id) => $id > 0);
            $normal_ids = array_filter(array_map('intval', $students), fn ($id) => $id > 0);
            $transfer_ids = array_filter(array_map('intval', $transfers), fn ($id) => $id > 0);

            $candidates = [];
            foreach (array_merge($normal_ids, $transfer_ids, $makeup_ids) as $sid) {
                $key = "{$sid}_{$class_id}";
                $map[$key] = false;

                $in_current_student = in_array($sid, $normal_ids, true);
                $in_current_transfer = in_array($sid, $transfer_ids, true);
                $in_prev_student = in_array($sid, $coach_prev['student'], true);
                $in_prev_transfer = in_array($sid, $coach_prev['transfer'], true);

                if (! $in_current_student || ! $in_prev_student) {
                    continue;
                }
                if ($in_current_transfer || $in_prev_transfer) {
                    continue;
                }
                if (empty($prev_period['month'])) {
                    continue;
                }
                $candidates[] = $sid;
            }
            if ($candidates !== [] && ! empty($prev_period['month'])) {
                $pk = $prev_period['month'] . '_' . ($prev_period['year'] ?? 0);
                if (! isset($periods_for_paid_merged[$pk])) {
                    $periods_for_paid_merged[$pk] = [
                        'period_month' => $prev_period['month'],
                        'period_year' => $prev_period['year'] ?? 0,
                        'student_ids' => [],
                    ];
                }
                $periods_for_paid_merged[$pk]['student_ids'] = array_values(array_unique(array_merge(
                    $periods_for_paid_merged[$pk]['student_ids'],
                    $candidates
                )));
            }
        }

        $prev_paid_map = $this->getStudentPeriodPaidMapIgnoringClassId(array_values($periods_for_paid_merged));

        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            $prev_period = $class_row['prev_period'] ?? null;
            $paid_key = $prev_period ? ($prev_period['month'] . '_' . ($prev_period['year'] ?? 0)) : '';
            $prev_paid_ids = $prev_paid_map[$paid_key] ?? [];

            $period_key = $prev_period ? ($prev_period['month'] . '_' . ($prev_period['year'] ?? 0)) : '';
            $coach_prev = $coach_prev_cache[$period_key] ?? ['student' => [], 'transfer' => []];

            $students = json_decode($class_row['student'] ?? '[]', true) ?: [];
            $transfers = json_decode($class_row['student_transfer'] ?? '[]', true) ?: [];
            $normal_ids = array_filter(array_map('intval', $students), fn ($id) => $id > 0);
            $transfer_ids = array_filter(array_map('intval', $transfers), fn ($id) => $id > 0);

            foreach (array_merge($normal_ids, $transfer_ids) as $sid) {
                $key = "{$sid}_{$class_id}";

                $in_current_student = in_array($sid, $normal_ids, true);
                $in_current_transfer = in_array($sid, $transfer_ids, true);
                $in_prev_student = in_array($sid, $coach_prev['student'], true);
                $in_prev_transfer = in_array($sid, $coach_prev['transfer'], true);

                if (! $in_current_student || ! $in_prev_student) {
                    continue;
                }
                if ($in_current_transfer || $in_prev_transfer) {
                    continue;
                }
                if (empty($prev_period['month'])) {
                    continue;
                }

                $has_prev_paid = in_array($sid, $prev_paid_ids, true);
                $has_current_paid = isset($student_order_map[$key]);

                if ($has_prev_paid && $has_current_paid) {
                    $map[$key] = true;
                }
            }
        }
        if ($with_debug) {
            $debug['renewal_count'] = count(array_filter($map));
        }

        return ['map' => $map, 'debug' => $debug];
    }

    /**
     * @param  array<string, array{order_date: int, amount: float, is_transfer_order: bool}>  $student_order_map
     * @return array<string, mixed>
     */
    public function getRenewalFilterDebugForStudent($coach_id, $student_id, $class_id, $class_month, $class_year, array $student_order_map): array
    {
        $student_id = (int) $student_id;
        $class_id = (int) $class_id;
        $class_year = (int) $class_year;
        $coach_id = (int) $coach_id;

        $result = [
            'step1' => false,
            'step2' => false,
            'step3' => false,
            'step4' => false,
            'step1_msg' => '',
            'step2_msg' => '',
            'step3_msg' => '',
            'step4_msg' => '',
        ];

        $prev_period = $this->getClassPreviousPeriod($class_id, $class_month, $class_year);
        $coach_prev = ['student' => [], 'transfer' => []];
        if (! empty($prev_period['month'])) {
            $coach_prev = $this->getCoachPeriodStudentIds($coach_id, $prev_period['month'], $prev_period['year'] ?? 0);
        }

        $row = EduClassUser::query()
            ->where('class_id', $class_id)
            ->where('class_year', $class_year)
            ->where(function ($q) use ($class_month) {
                $this->scopeWhereMonthMatchesLabel($q, 'month', trim((string) $class_month));
            })
            ->orderBy('id')
            ->first(['student', 'student_transfer']);

        $in_current_student = false;
        $in_current_transfer = false;
        if ($row !== null) {
            $students = is_array($row->student) ? $row->student : (json_decode((string) $row->getRawOriginal('student'), true) ?: []);
            $transfers = is_array($row->student_transfer) ? $row->student_transfer : (json_decode((string) $row->getRawOriginal('student_transfer'), true) ?: []);
            $in_current_student = in_array($student_id, array_filter(array_map('intval', $students), fn ($id) => $id > 0), true);
            $in_current_transfer = in_array($student_id, array_filter(array_map('intval', $transfers), fn ($id) => $id > 0), true);
        }
        $in_prev_student = in_array($student_id, $coach_prev['student'], true);
        $in_prev_transfer = in_array($student_id, $coach_prev['transfer'], true);

        $result['step1'] = $in_current_student && $in_prev_student;
        $result['step1_msg'] = '教練維度：今期須在 student、上期須在教練任一班 student。今期=' . ($in_current_student ? '✓' : '✗') . ' 上期=' . ($in_prev_student ? '✓' : '✗');

        $result['step2'] = $result['step1'] && ! $in_current_transfer && ! $in_prev_transfer;
        $result['step2_msg'] = 'student_transfer 排除：今期或上期在 transfer 則排除。今期=' . ($in_current_transfer ? '✗在transfer' : '✓') . ' 上期=' . ($in_prev_transfer ? '✗在transfer' : '✓');

        $result['step3'] = $result['step2'] && ! empty($prev_period['month']);
        $result['step3_msg'] = '上期連續性：須有連續上期。' . (empty($prev_period['month']) ? '✗無上期' : '✓' . ($prev_period['month'] ?? '') . ' ' . ($prev_period['year'] ?? ''));

        $has_prev_paid = false;
        if ($result['step3'] && ! empty($prev_period['month'])) {
            $paid_map = $this->getStudentPeriodPaidMapIgnoringClassId([[
                'period_month' => $prev_period['month'],
                'period_year' => $prev_period['year'] ?? 0,
                'student_ids' => [$student_id],
            ]]);
            $pk = $prev_period['month'] . '_' . ($prev_period['year'] ?? 0);
            $has_prev_paid = ! empty($paid_map[$pk]) && in_array($student_id, $paid_map[$pk], true);
        }
        $key = "{$student_id}_{$class_id}";
        $has_current_paid = isset($student_order_map[$key]);

        $result['step4'] = $result['step3'] && $has_prev_paid && $has_current_paid;
        $result['step4_msg'] = '交費驗證：上期及今期須有有效訂單。上期=' . ($has_prev_paid ? '✓' : '✗') . ' 今期=' . ($has_current_paid ? '✓' : '✗');

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrdersByStudentId($user_id): array
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        return EduOrder::query()
            ->where('user_id', $user_id)
            ->orderByDesc('order_date')
            ->get()
            ->map(fn (EduOrder $o) => $o->getAttributes())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClassUserDaysByStudentId($user_id): array
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        return EduClassUserDays::query()
            ->where('user_id', $user_id)
            ->orderByDesc('class_year')
            ->orderBy('month')
            ->get()
            ->map(fn (EduClassUserDays $r) => $r->getAttributes())
            ->all();
    }
}
