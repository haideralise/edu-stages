<?php

/**
 * Port of edu2 PrivateClassService — 私人班預約與薪酬計算.
 * CRUD uses Query Builder (batched insert + single CASE UPDATE) to avoid per-row queries and enum cast issues.
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrivateClassService
{
    private const INSERT_CHUNK = 100;

    /**
     * @param  array<int, array<string, mixed>>  $bookings
     * @return array{code: int, msg: string}
     */
    public function saveBookings(int $coach_id, array $bookings): array
    {
        $coach_id = (int) $coach_id;
        if ($coach_id <= 0) {
            return ['code' => 0, 'msg' => '無效的教練 ID'];
        }

        $today = now()->format('Y-m-d');
        $now = now()->toDateTimeString();

        try {
            $existing = DB::table('edu_private')
                ->where('coach_id', $coach_id)
                ->get(['id', 'status', 'payment_date', 'refund_date'])
                ->map(fn ($r) => (array) $r)
                ->all();

            $existingById = [];
            $refundedIds = [];
            foreach ($existing as $r) {
                $id = (int) ($r['id'] ?? 0);
                $existingById[$id] = $r;
                if ($this->isRefundStatus((string) ($r['status'] ?? ''))) {
                    $refundedIds[$id] = true;
                }
            }

            $payloadIds = [];
            foreach ($bookings as $b) {
                $id = (int) ($b['id'] ?? 0);
                if ($id > 0) {
                    $payloadIds[$id] = true;
                }
            }

            $nextEnrollmentId = $this->getNextEnrollmentId();
            $pendingBatchId = null;
            foreach ($bookings as &$b) {
                $b = $this->normalizeBookingPayload($b);
                if (! empty($b['enrollment_id']) && (int) $b['enrollment_id'] > 0) {
                    $pendingBatchId = null;
                } else {
                    if ($pendingBatchId === null) {
                        $pendingBatchId = $nextEnrollmentId++;
                    }
                    $b['enrollment_id'] = $pendingBatchId;
                }
            }
            unset($b);

            $slots = [];
            foreach ($bookings as $b) {
                $parsed = $this->parseBookingDateTime($b);
                $start_min = $this->timeToMinutes($parsed['time']);
                $end_min = $parsed['end_time'] !== '' ? $this->timeToMinutes($parsed['end_time']) : ($start_min + 60);
                if ($end_min <= $start_min) {
                    $end_min = $start_min + 60;
                }
                $slots[] = ['date' => $parsed['date'], 'start' => $start_min, 'end' => $end_min];
            }
            for ($i = 0, $c = count($slots); $i < $c; $i++) {
                for ($j = $i + 1; $j < $c; $j++) {
                    if ($slots[$i]['date'] === $slots[$j]['date']
                        && $slots[$i]['start'] < $slots[$j]['end']
                        && $slots[$i]['end'] > $slots[$j]['start']) {
                        return ['code' => 0, 'msg' => '時段重疊：' . $slots[$i]['date'] . ' 的預約與其他時段衝突，請調整時間'];
                    }
                }
            }

            $insertRows = [];
            $updateRows = [];

            foreach ($bookings as $b) {
                $id = (int) ($b['id'] ?? 0);
                if ($id > 0 && isset($refundedIds[$id])) {
                    continue;
                }

                $parsed = $this->parseBookingDateTime($b);
                $date = $parsed['date'] !== '' ? $parsed['date'] : $today;
                $time = $parsed['time'] !== '' ? $parsed['time'] : '00:00';
                $end_time = '';
                if ($parsed['end_time'] !== '' && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $parsed['end_time'])) {
                    $end_time = substr($parsed['end_time'], 0, 10);
                }
                $status = isset($b['status']) ? substr((string) $b['status'], 0, 20) : '預約中';
                $prev = ($id > 0 && isset($existingById[$id])) ? $existingById[$id] : null;
                $prevStatus = $prev ? trim((string) ($prev['status'] ?? '')) : '';
                $payment_date = $prev ? $this->normalizeDateValue($prev['payment_date'] ?? null) : null;
                $refund_date = $prev ? $this->normalizeDateValue($prev['refund_date'] ?? null) : null;

                if ($this->isPaidStatus($status)) {
                    if (! $this->isPaidStatus($prevStatus)) {
                        $payment_date = $today;
                    }
                } elseif ($this->isRefundStatus($status)) {
                    if (! $this->isRefundStatus($prevStatus)) {
                        $refund_date = $today;
                    }
                }

                $row = [
                    'coach_id' => $coach_id,
                    'enrollment_id' => (int) ($b['enrollment_id'] ?? $nextEnrollmentId),
                    'student_name' => isset($b['studentName']) ? substr((string) $b['studentName'], 0, 100) : '',
                    'student_phone' => isset($b['studentPhone']) ? substr((string) $b['studentPhone'], 0, 50) : '',
                    'district' => isset($b['district']) ? substr((string) $b['district'], 0, 50) : '',
                    'pool' => isset($b['pool']) ? substr((string) $b['pool'], 0, 100) : '',
                    'other_location' => isset($b['otherLocation']) ? substr((string) $b['otherLocation'], 0, 200) : '',
                    'class_date' => $date,
                    'class_time' => isset($b['time']) ? substr((string) $b['time'], 0, 10) : $time,
                    'class_end_time' => $end_time,
                    'ratio' => isset($b['ratio']) ? substr((string) $b['ratio'], 0, 10) : '1:1',
                    'type' => isset($b['type']) ? substr((string) $b['type'], 0, 50) : '',
                    'fee' => isset($b['fee']) ? (float) $b['fee'] : 0,
                    'status' => $status,
                    'payment_date' => $payment_date,
                    'refund_date' => $refund_date,
                    'attendance' => isset($b['attendance']) ? substr((string) $b['attendance'], 0, 20) : '出席',
                    'remark' => isset($b['remark']) ? (string) $b['remark'] : '',
                ];

                if ($id > 0 && isset($existingById[$id])) {
                    $row['id'] = $id;
                    $row['updated_at'] = $now;
                    $updateRows[] = $row;
                } else {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                    $insertRows[] = $row;
                }
            }

            $idsToDelete = [];
            foreach ($existingById as $eid => $r) {
                if (isset($payloadIds[$eid])) {
                    continue;
                }
                if ($this->isRefundStatus((string) ($r['status'] ?? ''))) {
                    continue;
                }
                $idsToDelete[] = (int) $eid;
            }

            DB::transaction(function () use ($insertRows, $updateRows, $coach_id, $idsToDelete) {
                foreach (array_chunk($insertRows, self::INSERT_CHUNK) as $chunk) {
                    if ($chunk !== []) {
                        DB::table('edu_private')->insert($chunk);
                    }
                }
                $this->bulkUpdatePrivateRows($coach_id, $updateRows);
                if ($idsToDelete !== []) {
                    DB::table('edu_private')
                        ->where('coach_id', $coach_id)
                        ->whereIn('id', $idsToDelete)
                        ->delete();
                }
            });

            return ['code' => 1, 'msg' => '儲存成功'];
        } catch (Throwable $e) {
            Log::error('PrivateClassService::saveBookings failed', [
                'coach_id' => $coach_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['code' => 0, 'msg' => '儲存失敗：' . $e->getMessage()];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows  Each must include id and the columns to set (no cumulative_override).
     */
    private function bulkUpdatePrivateRows(int $coachId, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columnsToSet = [
            'coach_id', 'enrollment_id', 'student_name', 'student_phone', 'district', 'pool', 'other_location',
            'class_date', 'class_time', 'class_end_time', 'ratio', 'type', 'fee', 'status',
            'payment_date', 'refund_date', 'attendance', 'remark', 'updated_at',
        ];

        $ids = array_values(array_unique(array_map(fn ($r) => (int) $r['id'], $rows)));
        $bindings = [];
        $setParts = [];

        foreach ($columnsToSet as $col) {
            $fragments = [];
            foreach ($rows as $r) {
                $fragments[] = 'WHEN ? THEN ?';
                $bindings[] = (int) $r['id'];
                $bindings[] = $this->bindingValueForColumn($col, $r[$col] ?? null);
            }
            $setParts[] = '`' . $col . '` = (CASE `id` ' . implode(' ', $fragments) . ' ELSE `' . $col . '` END)';
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach ($ids as $id) {
            $bindings[] = $id;
        }
        $bindings[] = $coachId;

        $sql = 'UPDATE `edu_private` SET ' . implode(', ', $setParts)
            . ' WHERE `id` IN (' . $placeholders . ') AND `coach_id` = ?';

        DB::update($sql, $bindings);
    }

    private function bindingValueForColumn(string $column, mixed $value): mixed
    {
        if (in_array($column, ['payment_date', 'refund_date', 'class_date'], true)) {
            if ($value === null || $value === '') {
                return null;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            return (string) $value;
        }

        if ($column === 'fee') {
            return (float) $value;
        }
        if ($column === 'enrollment_id' || $column === 'coach_id') {
            return (int) $value;
        }

        return $value;
    }

    private function normalizeDateValue(mixed $v): ?string
    {
        if ($v === null || $v === '' || $v === '0') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return (string) $v;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeBookingPayload(array $b): array
    {
        if (! isset($b['studentName']) && isset($b['student_name'])) {
            $b['studentName'] = $b['student_name'];
        }
        if (! isset($b['studentPhone']) && isset($b['student_phone'])) {
            $b['studentPhone'] = $b['student_phone'];
        }
        if (! isset($b['otherLocation']) && isset($b['other_location'])) {
            $b['otherLocation'] = $b['other_location'];
        }
        if (! isset($b['date']) && isset($b['class_date'])) {
            $b['date'] = $b['class_date'];
        }
        if (! isset($b['time']) && isset($b['class_time'])) {
            $b['time'] = $b['class_time'];
        }
        if (! isset($b['endTime']) && isset($b['class_end_time'])) {
            $b['endTime'] = $b['class_end_time'];
        }

        return $b;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBookings(int $coach_id): array
    {
        $coach_id = (int) $coach_id;
        if ($coach_id <= 0) {
            return [];
        }

        $rows = DB::table('edu_private')
            ->where('coach_id', $coach_id)
            ->orderBy('class_date')
            ->orderBy('class_time')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $date = $this->normalizeDateValue($r['class_date'] ?? null) ?? '';
            $time = (string) ($r['class_time'] ?? '');
            $endTime = trim((string) ($r['class_end_time'] ?? ''));
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'enrollment_id' => (int) ($r['enrollment_id'] ?? 0),
                'studentName' => $r['student_name'] ?? '',
                'studentPhone' => $r['student_phone'] ?? '',
                'district' => $r['district'] ?? '',
                'pool' => $r['pool'] ?? '',
                'otherLocation' => $r['other_location'] ?? '',
                'date' => $date,
                'time' => $time,
                'endTime' => $endTime,
                'display' => $date . ' ' . $time,
                'type' => $r['type'] ?? '',
                'ratio' => $r['ratio'] ?? '1:1',
                'fee' => $r['fee'] ?? '',
                'status' => $r['status'] ?? '預約中',
                'payment_date' => ! empty($r['payment_date']) ? $this->normalizeDateValue($r['payment_date']) : '',
                'refund_date' => ! empty($r['refund_date']) ? $this->normalizeDateValue($r['refund_date']) : '',
                'attendance' => $r['attendance'] ?? '出席',
                'remark' => $r['remark'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $b
     * @return array{date: string, time: string, end_time: string}
     */
    private function parseBookingDateTime(array $b): array
    {
        $date = isset($b['date']) ? trim((string) $b['date']) : (isset($b['display']) ? (explode(' ', (string) $b['display'])[0] ?? '') : '');
        $time = isset($b['time']) ? trim((string) $b['time']) : (isset($b['display']) ? (explode(' ', (string) $b['display'])[1] ?? '00:00') : '00:00');
        $end_time = ! empty($b['endTime']) ? trim((string) $b['endTime']) : '';

        return ['date' => $date, 'time' => $time, 'end_time' => $end_time];
    }

    private function getNextEnrollmentId(): int
    {
        $v = DB::table('edu_private')->selectRaw('COALESCE(MAX(enrollment_id), 0) + 1 AS next_id')->value('next_id');

        return (int) ($v ?? 1);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total_fee: float, students: list<array<string, mixed>>}
     */
    public function getPrivateClassSalaryForMonth(int $coach_id, int $year, int $month): array
    {
        $coach_id = (int) $coach_id;
        if ($coach_id <= 0) {
            return ['items' => [], 'total_fee' => 0, 'students' => []];
        }

        $ym = sprintf('%04d-%02d', $year, $month);
        $monthStart = $ym . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $rows = DB::table('edu_private')
            ->where('coach_id', $coach_id)
            ->whereIn('status', ['已付款', 'Paid'])
            ->whereDate('class_date', '>=', $monthStart)
            ->whereDate('class_date', '<=', $monthEnd)
            ->orderBy('class_date')
            ->orderBy('class_time')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        if ($rows === []) {
            return ['items' => [], 'total_fee' => 0, 'students' => []];
        }

        $all_rows = DB::table('edu_private')
            ->where('coach_id', $coach_id)
            ->whereIn('status', ['已付款', 'Paid'])
            ->orderBy('class_date')
            ->orderBy('class_time')
            ->get(['enrollment_id', 'student_name', 'student_phone', 'class_date', 'class_time', 'class_end_time', 'attendance', 'fee', 'status'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $cumulative_overrides = [];
        $override_rows = DB::table('edu_private')
            ->where('coach_id', $coach_id)
            ->whereNotNull('cumulative_override')
            ->get(['enrollment_id', 'cumulative_override'])
            ->map(fn ($r) => (array) $r)
            ->all();

        foreach ($override_rows as $r) {
            $eid = (int) ($r['enrollment_id'] ?? 0);
            $ov = $r['cumulative_override'] ?? null;
            if ($eid > 0 && $ov !== null && $ov !== '' && $ov !== false) {
                $cumulative_overrides[$eid] = (int) $ov;
            }
        }

        $enrollments = [];
        foreach ($rows as $r) {
            $eid = (int) ($r['enrollment_id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            if (! isset($enrollments[$eid])) {
                $enrollments[$eid] = [
                    'enrollment_id' => $eid,
                    'student_name' => $r['student_name'] ?? '',
                    'student_phone' => $r['student_phone'] ?? '',
                    'fee_per_session' => (float) ($r['fee'] ?? 0),
                    'attended' => 0,
                    'total' => 0,
                    'first_date' => $this->normalizeDateValue($r['class_date'] ?? '') ?? '',
                    'sessions' => [],
                    'student_ratio_total' => 0,
                    'student_ratio_late' => 0,
                ];
            }
            $att = trim((string) ($r['attendance'] ?? ''));
            $duration = $this->calcDurationHours((string) ($r['class_time'] ?? ''), (string) ($r['class_end_time'] ?? ''));
            $ratio_parts = explode(':', (string) ($r['ratio'] ?? '1:1'));
            $ratio_num = max(1, (int) ($ratio_parts[1] ?? 1));
            if ($this->isPresentAttendance($att)) {
                $enrollments[$eid]['attended']++;
                $enrollments[$eid]['total_hours'] = ($enrollments[$eid]['total_hours'] ?? 0) + $duration;
                $enrollments[$eid]['tuition_sum'] = ($enrollments[$eid]['tuition_sum'] ?? 0) + $duration * (float) ($r['fee'] ?? 0);
            }
            if ($this->isLeaveAttendance($att)) {
                $enrollments[$eid]['student_ratio_late'] += $ratio_num;
            }
            $enrollments[$eid]['student_ratio_total'] += $ratio_num;
            $enrollments[$eid]['sessions'][] = [
                'date' => $this->normalizeDateValue($r['class_date'] ?? '') ?? '',
                'time' => $r['class_time'] ?? '',
                'end_time' => trim((string) ($r['class_end_time'] ?? '')),
                'attendance' => $att,
                'duration' => $duration,
                'fee' => (float) ($r['fee'] ?? 0),
            ];
            if (($enrollments[$eid]['first_date'] ?? '') === '' || ($this->normalizeDateValue($r['class_date'] ?? '') ?? '') < ($this->normalizeDateValue($enrollments[$eid]['first_date'] ?? '') ?? '')) {
                $enrollments[$eid]['first_date'] = $this->normalizeDateValue($r['class_date'] ?? '') ?? '';
            }
        }

        $student_enrollment_order = [];
        foreach ($all_rows as $r) {
            $sk = $this->studentKey((string) ($r['student_name'] ?? ''), (string) ($r['student_phone'] ?? ''));
            if (! isset($student_enrollment_order[$sk])) {
                $student_enrollment_order[$sk] = [];
            }
            $eid = (int) ($r['enrollment_id'] ?? 0);
            if ($eid > 0 && ! in_array($eid, $student_enrollment_order[$sk], true)) {
                $student_enrollment_order[$sk][] = $eid;
            }
        }

        $enrollment_first_date = [];
        foreach ($all_rows as $r) {
            $eid = (int) ($r['enrollment_id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $d = $this->normalizeDateValue($r['class_date'] ?? '') ?? '';
            if (! isset($enrollment_first_date[$eid]) || $d < $enrollment_first_date[$eid]) {
                $enrollment_first_date[$eid] = $d;
            }
        }
        foreach ($student_enrollment_order as $sk => $eids) {
            usort($eids, function ($a, $b) use ($enrollment_first_date) {
                $da = $enrollment_first_date[$a] ?? '';
                $db = $enrollment_first_date[$b] ?? '';

                return strcmp($da, $db);
            });
            $student_enrollment_order[$sk] = $eids;
        }

        $student_attended_by_enrollment = [];
        foreach ($all_rows as $r) {
            $sk = $this->studentKey((string) ($r['student_name'] ?? ''), (string) ($r['student_phone'] ?? ''));
            $eid = (int) ($r['enrollment_id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            if (! isset($student_attended_by_enrollment[$sk])) {
                $student_attended_by_enrollment[$sk] = [];
            }
            if (! isset($student_attended_by_enrollment[$sk][$eid])) {
                $student_attended_by_enrollment[$sk][$eid] = 0;
            }
            if ($this->isPresentAttendance(trim((string) ($r['attendance'] ?? '')))) {
                $student_attended_by_enrollment[$sk][$eid]++;
            }
        }

        $items = [];
        $total_fee = 0;
        $students = [];

        foreach ($enrollments as $eid => $enr) {
            $sk = $this->studentKey($enr['student_name'], $enr['student_phone']);
            $order = $student_enrollment_order[$sk] ?? [];
            $idx = array_search($eid, $order, true);
            $is_first = ($idx === false || $idx === 0);
            $override = $cumulative_overrides[$eid] ?? null;
            if ($override !== null) {
                $cumulative = $override;
            } else {
                $cumulative = 0;
                for ($i = 0; $i < (int) $idx; $i++) {
                    $prev_eid = $order[$i];
                    $cumulative += (int) ($student_attended_by_enrollment[$sk][$prev_eid] ?? 0);
                }
            }

            $attended = $enr['attended'];
            $cumulative_total = $cumulative + $attended;
            $tuition = (float) ($enr['tuition_sum'] ?? ($attended * $enr['fee_per_session']));
            $rate = $this->privateClassBonusRate($override !== null ? false : $is_first, $cumulative);
            $coach_fee = round($tuition * $rate, 2);

            $items[] = [
                'enrollment_id' => $eid,
                'student_name' => $enr['student_name'],
                'student_phone' => $enr['student_phone'],
                'attended' => $attended,
                'fee_per_session' => (float) ($enr['fee_per_session'] ?? 0),
                'tuition' => $tuition,
                'bonus_rate' => $rate,
                'coach_fee' => $coach_fee,
                'sessions_display' => $this->formatSessionsDisplay($enr['sessions']),
            ];
            $total_fee += $coach_fee;

            $s_late_total = (int) ($enr['student_ratio_late'] ?? 0);
            $s_attend_total = (int) ($enr['student_ratio_total'] ?? 0);
            $s_late_pct = ($s_attend_total > 0) ? (int) round($s_late_total / $s_attend_total * 100) : 0;
            $session_count = count($enr['sessions']);
            $avg_ratio = ($session_count > 0) ? ($s_attend_total / $session_count) : 1;
            $s_late_per_person = ($avg_ratio > 0) ? round($s_late_total / $avg_ratio, 2) : 0;

            $students[] = [
                'student_name' => $enr['student_name'],
                'cumulative_total' => $cumulative_total,
                'cumulative_display' => ($override !== null) ? $cumulative : $cumulative_total,
                'days_distribution' => $this->formatSessionsDisplay($enr['sessions']),
                'fee' => $tuition,
                'bonus_rate' => $rate,
                'bonus_amount' => $coach_fee,
                'is_renewal' => ! $is_first,
                'student_late_total' => $s_late_total,
                'student_attend_total' => $s_attend_total,
                'student_late_pct' => $s_late_pct,
                'student_late_per_person' => $s_late_per_person,
            ];
        }

        return ['items' => $items, 'total_fee' => round($total_fee, 2), 'students' => $students];
    }

    private function studentKey(string $name, string $phone): string
    {
        $p = trim($phone);
        $n = trim($name);

        return ($p !== '') ? $p : (($n !== '') ? $n : 'unknown');
    }

    private function privateClassBonusRate(bool $is_first, int $cumulative): float
    {
        if ($is_first) {
            return 0.6;
        }
        if ($cumulative >= 25) {
            return 0.8;
        }
        if ($cumulative >= 20) {
            return 0.75;
        }
        if ($cumulative >= 15) {
            return 0.7;
        }
        if ($cumulative >= 10) {
            return 0.65;
        }
        if ($cumulative >= 5) {
            return 0.6;
        }

        return 0.6;
    }

    private function timeToMinutes(string $time): int
    {
        $time = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        return 0;
    }

    private function calcDurationHours(string $start_time, string $end_time): float
    {
        $end_time = trim($end_time);
        if ($end_time === '' || ! preg_match('/^(\d{1,2}):(\d{2})/', $end_time, $em)) {
            return 1.0;
        }
        $start_time = trim($start_time);
        if ($start_time === '' || ! preg_match('/^(\d{1,2}):(\d{2})/', $start_time, $sm)) {
            return 1.0;
        }
        $start_min = (int) $sm[1] * 60 + (int) $sm[2];
        $end_min = (int) $em[1] * 60 + (int) $em[2];
        if ($end_min <= $start_min) {
            return 1.0;
        }

        return round(($end_min - $start_min) / 60, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     */
    private function formatSessionsDisplay(array $sessions): string
    {
        $lines = [];
        foreach ($sessions as $s) {
            if (! $this->isPresentAttendance(trim((string) ($s['attendance'] ?? '')))) {
                continue;
            }
            $d = $this->formatDateShort((string) ($s['date'] ?? ''));
            $t = $this->timeTo12h((string) ($s['time'] ?? ''));
            $lines[] = $d . ' ' . $t;
        }

        return implode("\n", $lines);
    }

    private function formatDateShort(string $date): string
    {
        $date = trim($date);
        $ts = strtotime($date);
        if (! $ts) {
            return $date;
        }
        if (class_exists(\IntlDateFormatter::class)) {
            $fmt = new \IntlDateFormatter('zh_TW', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'M月d日(E)');
            $out = $fmt->format($ts);
            if ($out !== false && $out !== '') {
                return str_replace(['週一', '週二', '週三', '週四', '週五', '週六', '週日'], ['一', '二', '三', '四', '五', '六', '日'], $out);
            }
        }

        return date('n月j日(', $ts) . ['日', '一', '二', '三', '四', '五', '六'][(int) date('w', $ts)] . ')';
    }

    private function timeTo12h(string $time): string
    {
        $time = trim($time);
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return $time;
        }
        $h = (int) $m[1];
        $min = $m[2];
        $ampm = $h >= 12 ? 'pm' : 'am';
        $h12 = $h % 12 ?: 12;

        return sprintf('%02d:%s%s', $h12, $min, $ampm);
    }

    private function isPaidStatus(string $status): bool
    {
        $s = trim($status);

        return $s === '已付款' || $s === 'Paid';
    }

    private function isRefundStatus(string $status): bool
    {
        $s = trim($status);

        return $s === '已退款' || $s === 'Refunded';
    }

    private function isPresentAttendance(string $att): bool
    {
        $a = trim($att);

        return $a === '出席' || strcasecmp($a, 'present') === 0;
    }

    private function isLeaveAttendance(string $att): bool
    {
        $a = trim($att);

        return $a === '請假' || strcasecmp($a, 'leave') === 0;
    }
}
