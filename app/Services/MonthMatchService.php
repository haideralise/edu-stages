<?php

namespace App\Services;

class MonthMatchService
{
    public static function isMonthMatched(string $orderMonth, string $classMonth): bool
    {
        if (empty($orderMonth) || empty($classMonth)) {
            return false;
        }

        $orderMonth = trim($orderMonth);
        $classMonth = trim($classMonth);

        if ($orderMonth === $classMonth) {
            return true;
        }

        $orderMonths = self::extractMonthNumbers($orderMonth);
        $classMonths = self::extractMonthNumbers($classMonth);

        if (empty($orderMonths) || empty($classMonths)) {
            return false;
        }

        return count(array_intersect($orderMonths, $classMonths)) > 0;
    }

    private static function extractMonthNumbers(string $month): array
    {
        preg_match_all('/(\d+)月/', $month, $matches);
        if (empty($matches[1])) {
            return [];
        }

        $nums = array_map('intval', $matches[1]);

        if (count($nums) === 2 && $nums[0] < $nums[1]) {
            return range($nums[0], $nums[1]);
        }

        return $nums;
    }
}
