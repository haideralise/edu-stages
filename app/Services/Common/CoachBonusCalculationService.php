<?php

namespace App\Services\Common;

class CoachBonusCalculationService
{
    /**
     * Public entry point for coach bonus calculations.
     *
     * The three methods below are the supported API surface. They delegate to
     * CoachBonusSupportService, which may also be used directly in contexts that
     * require pure unit testing (no framework bootstrap needed).
     */
    public function support(): CoachBonusSupportService
    {
        return new CoachBonusSupportService();
    }

    public function bonusRateByCount(int $renewalCount): float
    {
        return $this->support()->bonusRateByCount($renewalCount);
    }

    public function isMonthMatchedForClassMonth(string $orderMonth, string $classMonth): bool
    {
        return $this->support()->isMonthMatchedForClassMonth($orderMonth, $classMonth);
    }

    public function getRefundFilterCondition(string $alias = 'o'): string
    {
        return $this->support()->getRefundFilterCondition($alias);
    }
}
