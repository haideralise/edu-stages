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
     * Look up HK-2020 percentile thresholds for a given age (months) and gender.
     *
     * Finds the nearest available age key. Returns null if age is outside
     * the 24–219 month range or gender is not recognised.
     */
    public static function getThresholds(int $ageMonths, string $gender): ?array
    {
        if ($ageMonths < 24 || $ageMonths > 219) {
            return null;
        }

        $gender = strtolower($gender);
        $table = config('bmi.percentiles.' . $gender);

        if (! $table) {
            return null;
        }

        // Exact match
        if (isset($table[$ageMonths])) {
            return $table[$ageMonths];
        }

        // Find nearest available key
        $keys = array_keys($table);
        $closest = $keys[0];
        $minDiff = abs($ageMonths - $closest);

        foreach ($keys as $key) {
            $diff = abs($ageMonths - $key);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $key;
            }
        }

        return $table[$closest];
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
