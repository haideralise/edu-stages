<?php

namespace App\Services\Common;

use InvalidArgumentException;

class CoachBonusSupportService
{
    /**
     * Pure, stateless helper methods for coach bonus calculations.
     *
     * ## Approach
     * This service is intentionally framework-free so that every method can be
     * exercised in unit tests without a database or application bootstrap. The
     * three public methods each correspond to a distinct step in the salary
     * pipeline described in docs/13coach-salary-calculation-logic.md:
     *
     *  - bonusRateByCount() maps a renewal count to the applicable bonus rate tier.
     *    A coach who achieves 10 or more renewals in the target period earns the
     *    maximum 20 % rate; the tiers below that are enumerated explicitly in the
     *    lookup map. This feeds directly into the gross bonus amount computed by
     *    the salary aggregation query (see §3.1 of the task document).
     *
     *  - isMonthMatchedForClassMonth() mirrors DebugClassService::isMonthMatchedForClassMonth:
     *    split on "-", compare endpoints only (no numeric “interior month” overlap).
     *    Thus e.g. class "5月-7月" vs order "6月" is false; "12月-1月" matches "12月"
     *    or "1月" via endpoint equality. Descending textual order (e.g. "6月-5月") is
     *    treated like any other range—same as legacy.
     *
     *  - getRefundFilterCondition() emits the SQL fragment that excludes refunded
     *    orders from bonus-eligible revenue, per the refund exclusion rules in
     *    docs/13coach-salary-calculation-logic.md §4.
     *
     * ## Edge cases
     *  - Negative or zero renewal count → rate 0.0 (coach earned no bonus).
     *  - Non-integer count (e.g. float passed by a legacy caller) → PHP coerces to
     *    int at the call site via the typed signature; callers must not rely on this.
     *  - Empty or whitespace-only month strings → return false (not an exception),
     *    matching the reference implementation behaviour.
     *  - Unrecognised month text → false (same as legacy string comparisons).
     *  - Empty alias for getRefundFilterCondition → InvalidArgumentException.
     */
    public function bonusRateByCount(int $count): float
    {
        $map = [
            1 => 0.10,
            2 => 0.12,
            3 => 0.13,
            4 => 0.14,
            5 => 0.15,
            6 => 0.16,
            7 => 0.17,
            8 => 0.18,
            9 => 0.19
        ];
        return match (true) {
            $count <= 0 => 0.0,
            isset($map[$count]) => $map[$count],
            $count >= 10 => 0.20,
        };
    }

    /**
     * Whether order month matches class month (legacy endpoint semantics).
     *
     * Behaviour matches edu2 DebugClassService::isMonthMatchedForClassMonth.
     */
    public function isMonthMatchedForClassMonth(string $orderMonth, string $classMonth): bool
    {
        $normalizedOrderMonth = $this->normalizeMonthInput($orderMonth);
        $normalizedClassMonth = $this->normalizeMonthInput($classMonth);

        if ($normalizedOrderMonth === '' || $normalizedClassMonth === '') {
            return false;
        }

        if ($normalizedOrderMonth === $normalizedClassMonth) {
            return true;
        }

        if (str_contains($normalizedClassMonth, '-')) {
            $classMonths = explode('-', $normalizedClassMonth);
            $classStart = trim($classMonths[0]);
            $classEnd = trim((string) end($classMonths));

            if (! str_contains($normalizedOrderMonth, '-')) {
                return $normalizedOrderMonth === $classStart || $normalizedOrderMonth === $classEnd;
            }

            $orderMonths = explode('-', $normalizedOrderMonth);
            $orderStart = trim($orderMonths[0]);
            $orderEnd = trim((string) end($orderMonths));

            return $orderStart === $classStart || $orderStart === $classEnd
                || $orderEnd === $classStart || $orderEnd === $classEnd;
        }

        if (str_contains($normalizedOrderMonth, '-')) {
            $orderMonths = explode('-', $normalizedOrderMonth);

            return in_array($normalizedClassMonth, array_map('trim', $orderMonths), true);
        }

        return $normalizedOrderMonth === $normalizedClassMonth;
    }

    /**
     * Build the refund exclusion condition used by higher-level query services.
     *
     * Alias assumptions intentionally default to legacy-style order aliases
     * (`o.refund_fee` / `o.refund_date`) and are locked by unit tests.
     */
    public function getRefundFilterCondition(string $alias = 'o'): string
    {
        $alias = trim($alias);

        if ($alias === '') {
            throw new InvalidArgumentException('Alias must not be empty.');
        }

        return sprintf(
            '((%1$s.refund_fee IS NULL OR %1$s.refund_fee <= 0) AND (%1$s.refund_date IS NULL OR %1$s.refund_date = \'\' OR %1$s.refund_date = \'0\'))',
            $alias,
        );
    }

    private function normalizeMonthInput(string $month): string
    {
        return preg_replace('/\s+/u', '', trim($month)) ?? '';
    }
}
