<?php

/**
 * Port of edu2 CoachBonusDebugService — 教練獎金報表／計算服務.
 * 續報判斷沿用 ClassStudentList 資料；獎金比例：CoachBonusCalculationService::bonusRateByCount.
 */

namespace App\Services;

use App\Services\Common\AttendanceQueryService;
use App\Services\Common\CoachBonusCalculationService;

class CoachBonusReportService
{
    /**
     * @var array<string, array<int, mixed>>  Cached getClassUserDaysArray results per report run
     */
    private array $classUserDaysArrayCache = [];

    public function __construct(
        private readonly CoachBonusCalculationService $bonusCalc,
        private readonly AttendanceQueryService $attendanceQueryService
    ) {
    }

    /**
     * 計算每位學員的獎金資訊（獎金百分比、獎金總數、分月獎金）
     *
     * @param  array<int, array<string, mixed>>  $current_classes
     * @param  array<string, bool>  $student_renewal_map
     * @param  array<string, array{amount?: float}>  $student_order_map
     * @param  array<int, array<int, array<string, mixed>>>  $student_days_map
     * @param  array<int, array<int, array<int, array{late?: int, absent?: int}>>>  $student_attendance_map
     * @return array<string, array{bonus_rate: float, bonus_total: float, bonus_monthly: float}>
     */
    public function getBonusMap(
        array $current_classes,
        array $student_renewal_map,
        array $student_order_map,
        array $student_days_map,
        int $year,
        int $month,
        array $student_attendance_map = []
    ): array {
        $this->resetCaches();
        $this->preloadEduClassesFromRows($current_classes);

        $result = [];
        $student_attendance_map = $student_attendance_map ?? [];

        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            $class_month = $class_row['class_month'] ?? '';
            $class_days_str = $class_row['class_days'] ?? '';
            $normal_ids = array_filter(
                array_map('intval', json_decode($class_row['student'] ?? '[]', true) ?: []),
                fn ($id) => $id > 0
            );

            $renewal_count = 0;
            foreach ($normal_ids as $sid) {
                $key = "{$sid}_{$class_id}";
                if (! empty($student_renewal_map[$key])) {
                    $renewal_count++;
                }
            }

            $bonus_rate = $this->bonusCalc->bonusRateByCount($renewal_count);

            foreach ($normal_ids as $sid) {
                $key = "{$sid}_{$class_id}";
                $result[$key] = [
                    'bonus_rate' => 0.0,
                    'bonus_total' => 0.0,
                    'bonus_monthly' => 0.0,
                ];

                if (empty($student_renewal_map[$key])) {
                    continue;
                }

                $fee = isset($student_order_map[$key]) ? (float) ($student_order_map[$key]['amount'] ?? 0) : 0;
                if ($fee <= 0) {
                    continue;
                }

                $result[$key]['bonus_rate'] = $bonus_rate;

                $class_months = (strpos($class_month, '-') !== false)
                    ? array_map('trim', explode('-', $class_month))
                    : [trim($class_month)];
                $bonus_total_sum = 0.0;
                foreach ($class_months as $m_text) {
                    $m_num = (int) str_replace('月', '', trim($m_text));
                    $fee_for_m = $this->getFeeForSelectedMonth($fee, $class_month, $class_id, $sid, $student_days_map, $year, $m_num);
                    if ($fee_for_m <= 0) {
                        continue;
                    }
                    $ratio_for_m = $this->getAttendanceRatioForMonth(
                        $sid,
                        $class_id,
                        $class_month,
                        $class_days_str,
                        $current_classes,
                        $student_days_map,
                        $student_attendance_map,
                        $year,
                        $m_num
                    );
                    $bonus_total_sum += round($fee_for_m * $bonus_rate * $ratio_for_m, 2);
                }
                $result[$key]['bonus_total'] = round($bonus_total_sum, 2);

                $fee_for_month = $this->getFeeForSelectedMonth($fee, $class_month, $class_id, $sid, $student_days_map, $year, $month);
                $ratio_for_month = $this->getAttendanceRatioForMonth(
                    $sid,
                    $class_id,
                    $class_month,
                    $class_days_str,
                    $current_classes,
                    $student_days_map,
                    $student_attendance_map,
                    $year,
                    $month
                );
                $result[$key]['bonus_monthly'] = round($fee_for_month * $bonus_rate * $ratio_for_month, 2);
            }
        }

        return $result;
    }

    /**
     * 單一學員獎金計算完整流程（供報表／調試面板顯示）
     *
     * @param  array<int, array<string, mixed>>  $current_classes
     * @return array<string, mixed>
     */
    public function getBonusDebugForStudent(
        int $user_id,
        int $class_id,
        array $current_classes,
        array $student_renewal_map,
        array $student_order_map,
        array $student_days_map,
        array $student_attendance_map,
        int $year,
        int $month
    ): array {
        $this->resetCaches();
        $this->preloadEduClassesFromRows($current_classes);

        $key = "{$user_id}_{$class_id}";
        $is_renewal = ! empty($student_renewal_map[$key]);
        $result = [
            'is_renewal' => $is_renewal,
            'renewal_count' => 0,
            'bonus_rate' => 0.0,
            'fee' => 0.0,
            'enrolled_days' => 0,
            'late' => 0,
            'absent' => 0,
            'main_attended' => 0,
            'makeup_days' => 0,
            'actual_days' => 0,
            'attendance_ratio' => 1.0,
            'fee_for_month' => 0.0,
            'bonus_total' => 0.0,
            'bonus_monthly' => 0.0,
            'formula' => '',
        ];

        $class_row = null;
        foreach ($current_classes as $row) {
            if ((int) $row['class_id'] === (int) $class_id) {
                $class_row = $row;
                break;
            }
        }
        if (! $class_row) {
            return $result;
        }

        $class_month = $class_row['class_month'] ?? '';
        $class_days_str = $class_row['class_days'] ?? '';
        $normal_ids = array_filter(
            array_map('intval', json_decode($class_row['student'] ?? '[]', true) ?: []),
            fn ($id) => $id > 0
        );

        $renewal_count = 0;
        foreach ($normal_ids as $sid) {
            $k = "{$sid}_{$class_id}";
            if (! empty($student_renewal_map[$k])) {
                $renewal_count++;
            }
        }
        $result['renewal_count'] = $renewal_count;
        $result['bonus_rate'] = $this->bonusCalc->bonusRateByCount($renewal_count);

        if (! $is_renewal) {
            $result['formula'] = '非續報，不計算獎金';

            return $result;
        }

        $fee = isset($student_order_map[$key]) ? (float) ($student_order_map[$key]['amount'] ?? 0) : 0;
        $result['fee'] = $fee;
        if ($fee <= 0) {
            $result['formula'] = '當期學費為 0，不計算獎金';

            return $result;
        }

        $class_months = (strpos($class_month, '-') !== false)
            ? array_map('trim', explode('-', $class_month))
            : [trim($class_month)];
        $days_for_user = $student_days_map[$class_id][$user_id] ?? [];
        $enrolled_days = $this->getEnrolledDaysCount($days_for_user, $class_months, $year, $class_id, $class_month, $class_days_str, $user_id);
        $result['enrolled_days'] = $enrolled_days;

        $att_for_class = $student_attendance_map[$class_id] ?? [];
        $late = 0;
        $absent = 0;
        foreach ($class_months as $m_text) {
            $m_num = (int) str_replace('月', '', trim($m_text));
            $late += (int) ($att_for_class[$user_id][$m_num]['late'] ?? 0);
            $absent += (int) ($att_for_class[$user_id][$m_num]['absent'] ?? 0);
        }
        $result['late'] = $late;
        $result['absent'] = $absent;
        $main_attended = max(0, $enrolled_days - $late - $absent);
        $result['main_attended'] = $main_attended;
        $makeup_days = $this->getMakeupDaysCount($user_id, $class_id, $current_classes, $student_days_map, $year);
        $result['makeup_days'] = $makeup_days;
        $actual_days = $main_attended + $makeup_days;
        $result['actual_days'] = $actual_days;
        $ratio_whole = $enrolled_days > 0 ? min(1.0, $actual_days / $enrolled_days) : 1.0;
        $result['attendance_ratio'] = $ratio_whole;

        $fee_for_month = $this->getFeeForSelectedMonth($fee, $class_month, $class_id, $user_id, $student_days_map, $year, $month);
        $result['fee_for_month'] = $fee_for_month;

        $bonus_rate = $result['bonus_rate'];

        $bonus_total_sum = 0.0;
        foreach ($class_months as $m_text) {
            $m_num = (int) str_replace('月', '', trim($m_text));
            $fee_for_m = $this->getFeeForSelectedMonth($fee, $class_month, $class_id, $user_id, $student_days_map, $year, $m_num);
            if ($fee_for_m <= 0) {
                continue;
            }
            $ratio_for_m = $this->getAttendanceRatioForMonth(
                $user_id,
                $class_id,
                $class_month,
                $class_days_str,
                $current_classes,
                $student_days_map,
                $student_attendance_map,
                $year,
                $m_num
            );
            $bonus_total_sum += round($fee_for_m * $bonus_rate * $ratio_for_m, 2);
        }
        $result['bonus_total'] = round($bonus_total_sum, 2);

        $ratio_for_month = $this->getAttendanceRatioForMonth(
            $user_id,
            $class_id,
            $class_month,
            $class_days_str,
            $current_classes,
            $student_days_map,
            $student_attendance_map,
            $year,
            $month
        );
        $result['bonus_monthly'] = round($fee_for_month * $bonus_rate * $ratio_for_month, 2);

        $result['formula'] = sprintf(
            '獎金總數 = Σ各月(分攤學費×比例×該月出席比例) = %.2f | 分月獎金 = 分攤學費 %.2f × 比例 %.0f%% × 該月出席比例 %.2f = %.2f',
            $result['bonus_total'],
            $fee_for_month,
            $bonus_rate * 100,
            $ratio_for_month,
            $result['bonus_monthly']
        );

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $current_classes
     */
    private function preloadEduClassesFromRows(array $current_classes): void
    {
        $ids = [];
        foreach ($current_classes as $row) {
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

    private function resetCaches(): void
    {
        $this->classUserDaysArrayCache = [];
    }

    /**
     * @return array<int, mixed>
     */
    private function getClassUserDaysArrayCached(int $class_id, string $month, int $year, string $class_days_str): array
    {
        $month = trim($month);
        $cacheKey = $class_id . "\0" . $month . "\0" . $year . "\0" . $class_days_str;
        if (! array_key_exists($cacheKey, $this->classUserDaysArrayCache)) {
            $raw = $this->attendanceQueryService->getClassUserDaysArray([
                'class_id' => $class_id,
                'month' => $month,
                'class_year' => $year,
                'days' => $class_days_str,
            ]);
            $this->classUserDaysArrayCache[$cacheKey] = is_array($raw) ? $raw : [];
        }

        return $this->classUserDaysArrayCache[$cacheKey];
    }

    /**
     * 取得出席比例：實際上堂數 / 報名堂數（edu2 同檔；目前僅供邏輯對照保留）
     *
     * @param  array<int, array<string, mixed>>  $current_classes
     */
    private function getAttendanceRatio(
        int $user_id,
        int $main_class_id,
        string $class_month,
        string $class_days_str,
        array $current_classes,
        array $student_days_map,
        array $student_attendance_map,
        int $year
    ): float {
        $class_months = (strpos($class_month, '-') !== false)
            ? array_map('trim', explode('-', $class_month))
            : [trim($class_month)];

        $days_for_user = $student_days_map[$main_class_id][$user_id] ?? [];
        $enrolled_days = $this->getEnrolledDaysCount($days_for_user, $class_months, $year, $main_class_id, $class_month, $class_days_str, $user_id);

        if ($enrolled_days <= 0) {
            return 1.0;
        }

        $att_for_class = $student_attendance_map[$main_class_id] ?? [];
        $late = 0;
        $absent = 0;
        foreach ($class_months as $m_text) {
            $m_num = (int) str_replace('月', '', trim($m_text));
            $late += (int) ($att_for_class[$user_id][$m_num]['late'] ?? 0);
            $absent += (int) ($att_for_class[$user_id][$m_num]['absent'] ?? 0);
        }

        $main_attended = max(0, $enrolled_days - $late - $absent);

        $makeup_days = $this->getMakeupDaysCount($user_id, $main_class_id, $current_classes, $student_days_map, $year);

        $actual_days = $main_attended + $makeup_days;
        $ratio = min(1.0, $actual_days / $enrolled_days);

        return $ratio;
    }

    /**
     * @param  array<int, array<string, mixed>>  $current_classes
     */
    private function getAttendanceRatioForMonth(
        int $user_id,
        int $main_class_id,
        string $class_month,
        string $class_days_str,
        array $current_classes,
        array $student_days_map,
        array $student_attendance_map,
        int $year,
        int $month
    ): float {
        $monthText = $month . '月';
        $class_months = (strpos($class_month, '-') !== false)
            ? array_map('trim', explode('-', $class_month))
            : [trim($class_month)];

        if (! in_array($monthText, $class_months, true)) {
            return 1.0;
        }

        $days_for_user = $student_days_map[$main_class_id][$user_id] ?? [];
        $days_per_month = $this->countDaysPerMonth($days_for_user, $class_months, $year);
        $enrolled_days_in_month = $days_per_month[$monthText] ?? 0;

        if ($enrolled_days_in_month <= 0) {
            $baseDays = $this->getClassUserDaysArrayCached($main_class_id, $monthText, $year, $class_days_str);
            $enrolled_days_in_month = is_array($baseDays) ? count($baseDays) : 0;
        }

        if ($enrolled_days_in_month <= 0) {
            return 1.0;
        }

        $att_for_class = $student_attendance_map[$main_class_id] ?? [];
        $late_in_month = (int) ($att_for_class[$user_id][$month]['late'] ?? 0);
        $absent_in_month = (int) ($att_for_class[$user_id][$month]['absent'] ?? 0);
        $main_attended_in_month = max(0, $enrolled_days_in_month - $late_in_month - $absent_in_month);

        $makeup_days_in_month = $this->getMakeupDaysCountForMonth($user_id, $main_class_id, $current_classes, $student_days_map, $year, $month);
        $actual_days_in_month = $main_attended_in_month + $makeup_days_in_month;

        return min(1.0, $actual_days_in_month / $enrolled_days_in_month);
    }

    /**
     * @param  array<int, array<string, mixed>>  $current_classes
     */
    private function getMakeupDaysCountForMonth(
        int $user_id,
        int $main_class_id,
        array $current_classes,
        array $student_days_map,
        int $year,
        int $month
    ): int {
        $monthText = $month . '月';
        $total = 0;
        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            if ($class_id === $main_class_id) {
                continue;
            }
            $makeup_ids = array_filter(
                array_map('intval', json_decode($class_row['student_makeup'] ?? '[]', true) ?: []),
                fn ($id) => $id > 0
            );
            if (! in_array($user_id, $makeup_ids, true)) {
                continue;
            }
            $class_month = $class_row['class_month'] ?? '';
            $class_months = (strpos($class_month, '-') !== false)
                ? array_map('trim', explode('-', $class_month))
                : [trim($class_month)];
            $days_for_user = $student_days_map[$class_id][$user_id] ?? [];
            $days_per_month = $this->countDaysPerMonth($days_for_user, $class_months, $year);
            $total += $days_per_month[$monthText] ?? 0;
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $days_for_user
     * @param  array<int, string>  $class_months
     */
    private function getEnrolledDaysCount(
        array $days_for_user,
        array $class_months,
        int $year,
        int $class_id,
        string $class_month,
        string $class_days_str,
        int $user_id
    ): int {
        $days_per_month = $this->countDaysPerMonth($days_for_user, $class_months, $year);
        $total = array_sum($days_per_month);

        if ($total > 0) {
            return $total;
        }

        $total = 0;
        foreach ($class_months as $m_text) {
            $m_text = trim($m_text);
            $baseDays = $this->getClassUserDaysArrayCached($class_id, $m_text, $year, $class_days_str);
            $total += is_array($baseDays) ? count($baseDays) : 0;
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $current_classes
     */
    private function getMakeupDaysCount(
        int $user_id,
        int $main_class_id,
        array $current_classes,
        array $student_days_map,
        int $year
    ): int {
        $total = 0;
        foreach ($current_classes as $class_row) {
            $class_id = (int) $class_row['class_id'];
            if ($class_id === $main_class_id) {
                continue;
            }
            $makeup_ids = array_filter(
                array_map('intval', json_decode($class_row['student_makeup'] ?? '[]', true) ?: []),
                fn ($id) => $id > 0
            );
            if (! in_array($user_id, $makeup_ids, true)) {
                continue;
            }
            $class_month = $class_row['class_month'] ?? '';
            $class_months = (strpos($class_month, '-') !== false)
                ? array_map('trim', explode('-', $class_month))
                : [trim($class_month)];
            $days_for_user = $student_days_map[$class_id][$user_id] ?? [];
            $days_per_month = $this->countDaysPerMonth($days_for_user, $class_months, $year);
            $total += array_sum($days_per_month);
        }

        return $total;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $student_days_map
     */
    private function getFeeForSelectedMonth(
        float $total_fee,
        string $class_month,
        int $class_id,
        int $user_id,
        array $student_days_map,
        int $year,
        int $month
    ): float {
        $monthText = $month . '月';
        $class_months = (strpos($class_month, '-') !== false)
            ? array_map('trim', explode('-', $class_month))
            : [trim($class_month)];

        if (! in_array($monthText, $class_months, true)) {
            return 0.0;
        }

        $days_for_user = $student_days_map[$class_id][$user_id] ?? [];
        if (empty($days_for_user)) {
            return count($class_months) === 1 ? $total_fee : ($total_fee / count($class_months));
        }

        $days_per_month = $this->countDaysPerMonth($days_for_user, $class_months, $year);
        $total_days = array_sum($days_per_month);
        if ($total_days <= 0) {
            return count($class_months) === 1 ? $total_fee : ($total_fee / count($class_months));
        }

        $selected_days = $days_per_month[$monthText] ?? 0;

        return $total_fee * ($selected_days / $total_days);
    }

    /**
     * @param  array<string, mixed>  $days_for_user
     * @param  array<int, string>  $class_months
     * @return array<string, int>
     */
    private function countDaysPerMonth(array $days_for_user, array $class_months, int $year): array
    {
        $result = array_fill_keys(array_map('trim', $class_months), 0);

        foreach ($days_for_user as $days_str) {
            if (empty($days_str) || trim((string) $days_str) === '' || $days_str === '[]') {
                continue;
            }
            $dates = array_filter(array_map('trim', explode(',', (string) $days_str)));
            foreach ($dates as $d) {
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
                    if ((int) $m[1] !== $year) {
                        continue;
                    }
                    $m_num = (int) $m[2];
                    $m_text = $m_num . '月';
                    if (isset($result[$m_text])) {
                        $result[$m_text]++;
                    }
                }
            }
        }

        return $result;
    }
}
