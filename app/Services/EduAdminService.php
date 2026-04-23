<?php

namespace App\Services;

use App\Models\EduClassUser;

class EduAdminService
{
    public function getClassUserForMonthYear(string $classYear, string $classMonth): array
    {
        $m = (int) $classMonth;

        $monthVariants = [
            $m . '月',
            ($m - 1) . '月-' . $m . '月',
            $m . '月-' . ($m + 1) . '月',
        ];

        return EduClassUser::where('class_year', $classYear)
            ->whereIn('month', $monthVariants)
            ->get()
            ->map(fn($cu) => $cu->toArray())
            ->toArray();
    }
}