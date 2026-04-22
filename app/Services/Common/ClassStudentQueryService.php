<?php

namespace App\Services\Common;

use App\Models\EduClassUser;
use App\Models\WpUser;

/**
 * Ported from edu2/services/Common/ClassStudentQueryService.php (10eng §7).
 * Prev/next by month continuity; adjacent batch by edu_class_user.sort (not month order).
 */
class ClassStudentQueryService
{
    /**
     * @return array{month: string, class_year: int}|null
     */
    public function getClassPreviousPeriod(int $class_id, int|string $current_month, ?int $current_year = null): ?array
    {
        $class_id = (int) $class_id;
        $norm = $this->loadNormalizedPeriodRowsForClass($class_id);
        if ($norm === []) {
            return null;
        }

        $sorted = $this->sortRowsForPreviousScan($norm);

        return $this->resolvePreviousPeriodFromNormRows($norm, $sorted, $current_month, $current_year);
    }

    /**
     * @return array{month: string, class_year: int, student: string, student_transfer: string}|null
     */
    public function getClassNextPeriod(int $class_id, int|string $current_month, ?int $current_year = null): ?array
    {
        $class_id = (int) $class_id;
        $norm = $this->loadNormalizedPeriodRowsForClass($class_id);
        if ($norm === []) {
            return null;
        }

        $sorted = $this->sortRowsForNextScan($norm);

        return $this->resolveNextPeriodFromNormRows($norm, $sorted, $current_month, $current_year);
    }

    /**
     * One {@see EduClassUser} load for all distinct class_ids; same prev/next rules as
     * {@see getClassPreviousPeriod} / {@see getClassNextPeriod} per item (order preserved).
     *
     * @param  list<array{class_id: int, month: int|string, year?: int, class_year?: int}>  $items
     * @return list<array{prev: ?array, next: ?array}>
     */
    public function getClassPreviousNextPeriodsBatch(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $classIds = [];
        foreach ($items as $it) {
            $cid = (int) ($it['class_id'] ?? 0);
            if ($cid > 0) {
                $classIds[$cid] = true;
            }
        }
        $classIdList = array_keys($classIds);

        $byClass = [];
        if ($classIdList !== []) {
            $rows = EduClassUser::query()
                ->whereIn('class_id', $classIdList)
                ->whereNotNull('month')
                ->where('month', '!=', '')
                ->get(['class_id', 'month', 'class_year', 'sort', 'student', 'student_transfer']);

            foreach ($rows as $row) {
                $cid = (int) $row->class_id;
                if (! isset($byClass[$cid])) {
                    $byClass[$cid] = [];
                }
                $byClass[$cid][] = $this->normalizePeriodRow($row);
            }
        }

        $sortedPrev = [];
        $sortedNext = [];
        foreach ($classIdList as $cid) {
            $norm = $byClass[$cid] ?? [];
            $sortedPrev[$cid] = $this->sortRowsForPreviousScan($norm);
            $sortedNext[$cid] = $this->sortRowsForNextScan($norm);
        }

        $out = [];
        foreach ($items as $it) {
            $cid = (int) ($it['class_id'] ?? 0);
            $month = $it['month'] ?? '';
            $year = $it['year'] ?? $it['class_year'] ?? null;
            $year = $year !== null ? (int) $year : null;

            if ($cid <= 0) {
                $out[] = ['prev' => null, 'next' => null];
                continue;
            }

            $norm = $byClass[$cid] ?? [];
            if ($norm === []) {
                $out[] = ['prev' => null, 'next' => null];
                continue;
            }

            $out[] = [
                'prev' => $this->resolvePreviousPeriodFromNormRows($norm, $sortedPrev[$cid], $month, $year),
                'next' => $this->resolveNextPeriodFromNormRows($norm, $sortedNext[$cid], $month, $year),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>
     */
    private function loadNormalizedPeriodRowsForClass(int $class_id): array
    {
        $rows = EduClassUser::query()
            ->where('class_id', $class_id)
            ->whereNotNull('month')
            ->where('month', '!=', '')
            ->get(['class_id', 'month', 'class_year', 'sort', 'student', 'student_transfer']);

        $norm = [];
        foreach ($rows as $row) {
            $norm[] = $this->normalizePeriodRow($row);
        }

        return $norm;
    }

    /**
     * @return array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}
     */
    private function normalizePeriodRow(EduClassUser $row): array
    {
        $sortRaw = $row->getRawOriginal('sort');

        return [
            'month' => (string) $row->month,
            'class_year' => (int) $row->class_year,
            'sort' => $sortRaw !== null && $sortRaw !== '' ? (int) $sortRaw : null,
            'student' => (string) ($row->getRawOriginal('student') ?? ''),
            'student_transfer' => (string) ($row->getRawOriginal('student_transfer') ?? ''),
        ];
    }

    /**
     * @param  list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>  $normRows
     * @return list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>
     */
    private function sortRowsForPreviousScan(array $normRows): array
    {
        $copy = $normRows;
        usort($copy, function (array $a, array $b) {
            if ($a['class_year'] !== $b['class_year']) {
                return $b['class_year'] <=> $a['class_year'];
            }

            return strcmp((string) $b['month'], (string) $a['month']);
        });

        return $copy;
    }

    /**
     * @param  list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>  $normRows
     * @return list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>
     */
    private function sortRowsForNextScan(array $normRows): array
    {
        $copy = $normRows;
        usort($copy, function (array $a, array $b) {
            if ($a['class_year'] !== $b['class_year']) {
                return $a['class_year'] <=> $b['class_year'];
            }

            return strcmp((string) $a['month'], (string) $b['month']);
        });

        return $copy;
    }

    /**
     * @param  list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>  $normRows
     * @return array{month: string, class_year: int}|null
     */
    private function resolveCurrentPeriodFromNumericOrLiteral(
        array $normRows,
        int|string $current_month,
        ?int $current_year
    ): ?array {
        if (is_numeric($current_month)) {
            $sort = (int) $current_month;
            foreach ($normRows as $r) {
                if ($r['sort'] !== null && (int) $r['sort'] === $sort) {
                    return [
                        'month' => (string) $r['month'],
                        'class_year' => (int) $r['class_year'],
                    ];
                }
            }

            return null;
        }

        if ($current_year === null) {
            return null;
        }

        return [
            'month' => (string) $current_month,
            'class_year' => $current_year,
        ];
    }

    /**
     * @param  list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>  $normRows
     * @param  list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>  $sortedDesc
     */
    private function resolvePreviousPeriodFromNormRows(
        array $normRows,
        array $sortedDesc,
        int|string $current_month,
        ?int $current_year
    ): ?array {
        $resolved = $this->resolveCurrentPeriodFromNumericOrLiteral($normRows, $current_month, $current_year);
        if ($resolved === null) {
            return null;
        }

        $current_month = $resolved['month'];
        $current_year = $resolved['class_year'];

        $current_start_month = $this->parseStartMonth($current_month);
        if ($current_start_month === null) {
            return null;
        }

        $expected_prev = $this->calculatePreviousMonth($current_year, $current_start_month);

        foreach ($sortedDesc as $row) {
            $row_year = (int) $row['class_year'];
            $row_month = (string) $row['month'];
            $row_end_month = $this->parseEndMonth($row_month);
            if ($row_end_month === null) {
                continue;
            }
            if ($this->isSameYearMonth($row_year, $row_end_month, $expected_prev['year'], $expected_prev['month'])) {
                return [
                    'month' => $row_month,
                    'class_year' => $row_year,
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>  $normRows
     * @param  list<array{month: string, class_year: int, sort: int|null, student: string, student_transfer: string}>  $sortedAsc
     * @return array{month: string, class_year: int, student: string, student_transfer: string}|null
     */
    private function resolveNextPeriodFromNormRows(
        array $normRows,
        array $sortedAsc,
        int|string $current_month,
        ?int $current_year
    ): ?array {
        $resolved = $this->resolveCurrentPeriodFromNumericOrLiteral($normRows, $current_month, $current_year);
        if ($resolved === null) {
            return null;
        }

        $current_month = $resolved['month'];
        $current_year = $resolved['class_year'];

        $current_end_month = $this->parseEndMonth($current_month);
        if ($current_end_month === null) {
            return null;
        }

        $expected_next = $this->calculateNextMonth($current_year, $current_end_month);

        foreach ($sortedAsc as $row) {
            $row_year = (int) $row['class_year'];
            $row_month = (string) $row['month'];
            $row_start_month = $this->parseStartMonth($row_month);
            if ($row_start_month === null) {
                continue;
            }
            if ($this->isSameYearMonth($row_year, $row_start_month, $expected_next['year'], $expected_next['month'])) {
                return [
                    'month' => $row_month,
                    'class_year' => $row_year,
                    'student' => (string) $row['student'],
                    'student_transfer' => (string) $row['student_transfer'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, 'student'|'student_transfer'>
     */
    public function getNextPeriodStudents(int $class_id, int $current_sort): array
    {
        $next_class = $this->getClassNextPeriod($class_id, $current_sort);
        if ($next_class === null) {
            return [];
        }

        $students = [];

        $normal_students = json_decode($next_class['student'] ?? '[]', true);
        if (is_array($normal_students)) {
            foreach ($normal_students as $sid) {
                if (! empty($sid) && is_numeric($sid)) {
                    $students[(int) $sid] = 'student';
                }
            }
        }

        $transfer_students = json_decode($next_class['student_transfer'] ?? '[]', true);
        if (is_array($transfer_students)) {
            foreach ($transfer_students as $sid) {
                if (! empty($sid) && is_numeric($sid)) {
                    $students[(int) $sid] = 'student_transfer';
                }
            }
        }

        return $students;
    }

    /**
     * @param  list<array{class_id: int, sort: int}>  $classSorts
     * @return array{prev: array<string, array{month: string, class_year: mixed}>, next: array<string, array{month: string, class_year: mixed, students: array<int, string>}>}
     */
    public function getAdjacentPeriodsBatch(array $classSorts): array
    {
        if ($classSorts === []) {
            return ['prev' => [], 'next' => []];
        }

        $prev_results = [];
        $next_results = [];

        $prevOr = [];
        foreach ($classSorts as $item) {
            $cid = (int) ($item['class_id'] ?? 0);
            $sort = (int) ($item['sort'] ?? 0);
            if ($cid > 0 && $sort > 0) {
                $prevOr[] = ['class_id' => $cid, 'sort' => $sort];
            }
        }

        if ($prevOr !== []) {
            $results = EduClassUser::query()
                ->whereNotNull('sort')
                ->where(function ($q) use ($prevOr) {
                    foreach ($prevOr as $i => $cond) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q->{$method}(function ($q2) use ($cond) {
                            $q2->where('class_id', $cond['class_id'])->where('sort', '<', $cond['sort']);
                        });
                    }
                })
                ->orderBy('class_id')
                ->orderByDesc('sort')
                ->get(['class_id', 'sort', 'month', 'class_year']);

            $temp = [];
            foreach ($results as $row) {
                $key = (int) $row->class_id;
                if (! isset($temp[$key]) || (int) $row->sort > (int) $temp[$key]['sort']) {
                    $temp[$key] = [
                        'sort' => (int) $row->sort,
                        'month' => (string) $row->month,
                        'class_year' => $row->class_year,
                    ];
                }
            }

            foreach ($classSorts as $item) {
                $class_id = (int) ($item['class_id'] ?? 0);
                $sort = (int) ($item['sort'] ?? 0);
                $key = "{$class_id}_{$sort}";
                if (isset($temp[$class_id])) {
                    $prev_results[$key] = [
                        'month' => $temp[$class_id]['month'],
                        'class_year' => $temp[$class_id]['class_year'],
                    ];
                }
            }
        }

        $nextOr = [];
        foreach ($classSorts as $item) {
            $cid = (int) ($item['class_id'] ?? 0);
            $sort = (int) ($item['sort'] ?? 0);
            if ($cid > 0 && $sort > 0) {
                $nextOr[] = ['class_id' => $cid, 'sort' => $sort];
            }
        }

        if ($nextOr !== []) {
            $results = EduClassUser::query()
                ->whereNotNull('sort')
                ->where(function ($q) use ($nextOr) {
                    foreach ($nextOr as $i => $cond) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q->{$method}(function ($q2) use ($cond) {
                            $q2->where('class_id', $cond['class_id'])->where('sort', '>', $cond['sort']);
                        });
                    }
                })
                ->orderBy('class_id')
                ->orderBy('sort')
                ->get(['class_id', 'sort', 'month', 'class_year', 'student', 'student_transfer']);

            $temp = [];
            foreach ($results as $row) {
                $key = (int) $row->class_id;
                if (! isset($temp[$key]) || (int) $row->sort < (int) $temp[$key]['sort']) {
                    $temp[$key] = [
                        'sort' => (int) $row->sort,
                        'month' => (string) $row->month,
                        'class_year' => $row->class_year,
                        'student' => $row->getRawOriginal('student'),
                        'student_transfer' => $row->getRawOriginal('student_transfer'),
                    ];
                }
            }

            foreach ($classSorts as $item) {
                $class_id = (int) ($item['class_id'] ?? 0);
                $sort = (int) ($item['sort'] ?? 0);
                $key = "{$class_id}_{$sort}";
                if (isset($temp[$class_id])) {
                    $row = $temp[$class_id];
                    $students = [];

                    $normal = json_decode($row['student'] ?? '[]', true);
                    if (is_array($normal)) {
                        foreach ($normal as $sid) {
                            if (! empty($sid) && is_numeric($sid)) {
                                $students[(int) $sid] = 'student';
                            }
                        }
                    }

                    $transfer = json_decode($row['student_transfer'] ?? '[]', true);
                    if (is_array($transfer)) {
                        foreach ($transfer as $sid) {
                            if (! empty($sid) && is_numeric($sid)) {
                                $students[(int) $sid] = 'student_transfer';
                            }
                        }
                    }

                    $next_results[$key] = [
                        'month' => $row['month'],
                        'class_year' => $row['class_year'],
                        'students' => $students,
                    ];
                }
            }
        }

        return ['prev' => $prev_results, 'next' => $next_results];
    }

    /**
     * @param  list<int|string>  $student_ids
     * @return array<int, string>
     */
    public function getStudentsBatch(array $student_ids): array
    {
        if ($student_ids === []) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));
        if ($ids === []) {
            return [];
        }

        $results = WpUser::query()
            ->whereIn('ID', $ids)
            ->get(['ID', 'display_name']);

        $student_info = [];
        foreach ($results as $row) {
            $student_info[(int) $row->ID] = (string) $row->display_name;
        }

        return $student_info;
    }

    private function parseStartMonth(string $month_range): ?int
    {
        if ($month_range === '') {
            return null;
        }

        if (str_contains($month_range, '-')) {
            $parts = explode('-', $month_range);
            $start_text = trim($parts[0]);
            $month_num = (int) str_replace('月', '', $start_text);

            return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
        }

        $month_num = (int) str_replace('月', '', trim($month_range));

        return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
    }

    private function parseEndMonth(string $month_range): ?int
    {
        if ($month_range === '') {
            return null;
        }

        if (str_contains($month_range, '-')) {
            $parts = explode('-', $month_range);
            $end_text = trim($parts[1]);
            $month_num = (int) str_replace('月', '', $end_text);

            return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
        }

        $month_num = (int) str_replace('月', '', trim($month_range));

        return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
    }

    /**
     * @return array{year: int, month: int}
     */
    private function calculatePreviousMonth(int $year, int $month): array
    {
        if ($month === 1) {
            return ['year' => $year - 1, 'month' => 12];
        }

        return ['year' => $year, 'month' => $month - 1];
    }

    /**
     * @return array{year: int, month: int}
     */
    private function calculateNextMonth(int $year, int $month): array
    {
        if ($month === 12) {
            return ['year' => $year + 1, 'month' => 1];
        }

        return ['year' => $year, 'month' => $month + 1];
    }

    private function isSameYearMonth(int $year_a, int $month_a, int $year_b, int $month_b): bool
    {
        return $year_a === $year_b && $month_a === $month_b;
    }
}
