<?php

namespace App\Services;

use App\Models\EduAttendance;
use App\Models\EduClass;
use App\Models\EduUser;
use App\ValueObjects\EduMonthWindow;
use Illuminate\Support\Collection;

class EduService
{
    /**
     * Get attendances for a class within a period defined by a month window and year.
     *
     * Results are ordered by `id` ascending so that consumers (e.g. LegacyEduService) can apply a
     * deterministic rule when more than one row exists for the same user and calendar date.
     *
     * @param  array<EduUser|int>|Collection<EduUser|int>|null  $forUsers  Optional users to filter by.
     * @return Collection<EduAttendance>
     */
    public function getClassAttendancesInMonthWindow(
        EduClass|int $eduClass,
        EduMonthWindow $monthWindow,
        int $year,
        array|Collection|null $forUsers = null
    ): Collection {
        $query = EduAttendance::query()
            ->where('class_id', $eduClass instanceof EduClass ? $eduClass->getKey() : $eduClass)
            ->whereMonthWindow($monthWindow)
            ->where('class_year', $year)
            ->whereBetween('date', [
                $monthWindow->getStartDateForYear($year)->format('Y-m-d'),
                $monthWindow->getEndDateForYear($year)->format('Y-m-d'),
            ]);

        if (! is_null($forUsers)) {
            if ($forUsers instanceof Collection) {
                $forUsers = $forUsers->toArray();
            }

            $query->whereIn(
                'user_id',
                array_map(
                    static fn (EduUser|int $user): int => $user instanceof EduUser ? $user->getKey() : $user,
                    $forUsers
                )
            );
        }

        return $query->orderBy('id')->get();
    }
}
