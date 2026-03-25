<?php

namespace App\Support;

use DateTimeImmutable;

class BmiForAge
{
    /**
     * Calculate age in months between a birthdate and a record date (unix timestamp).
     */
    public static function calculateAgeMonths(string $birthdate, int $recordDate): int
    {
        $birth = new DateTimeImmutable($birthdate);
        $record = (new DateTimeImmutable)->setTimestamp($recordDate);

        if ($record < $birth) {
            return 0;
        }

        $diff = $birth->diff($record);

        return ($diff->y * 12) + $diff->m;
    }

    /**
     * Look up CDC percentile thresholds for a given age (months) and gender.
     *
     * Rounds to the nearest 6-month interval. Returns null if age is outside
     * the 24–240 month range or gender is not recognised.
     */
    public static function getThresholds(int $ageMonths, string $gender): ?array
    {
        if ($ageMonths < 24 || $ageMonths > 240) {
            return null;
        }

        $gender = strtolower($gender);
        $table = config('bmi.percentiles.' . $gender);

        if (! $table) {
            return null;
        }

        // Round to nearest 6-month interval
        $key = (int) round($ageMonths / 6) * 6;
        $key = max(24, min(240, $key));

        return $table[$key] ?? null;
    }

    /**
     * Categorize a BMI value using age-aware thresholds when possible,
     * falling back to adult WHO thresholds otherwise.
     */
    public static function categorize(float $bmi, ?string $birthdate, ?string $gender, ?int $recordDate): string
    {
        if ($birthdate && $gender && $recordDate) {
            $ageMonths = self::calculateAgeMonths($birthdate, $recordDate);
            $thresholds = self::getThresholds($ageMonths, $gender);

            if ($thresholds) {
                if ($bmi < $thresholds['p5']) {
                    return 'underweight';
                }
                if ($bmi < $thresholds['p85']) {
                    return 'normal';
                }
                if ($bmi < $thresholds['p95']) {
                    return 'overweight';
                }

                return 'obese';
            }
        }

        // Adult WHO fallback
        if ($bmi < 18.5) {
            return 'underweight';
        }
        if ($bmi < 25) {
            return 'normal';
        }
        if ($bmi < 30) {
            return 'overweight';
        }

        return 'obese';
    }
}
