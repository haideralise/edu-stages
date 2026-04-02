<?php

namespace App\Services;

use App\Models\EduAttendance;
use App\ValueObjects\EduMonthWindow;
use Illuminate\Support\Collection;

class LegacyEduService
{
    /**
     * Return attendance records keyed as [user_id => ['Y-m-d' => normalized_status, ...], ...].
     *
     * Matches the spec from task-01eng-attendance-migration.md §3.3.
     *
     * Duplicate rows: the shared DB (`wp_3x_edu_attendance`) has no unique constraint on
     * (class_id, user_id, date). Historical data can contain more than one row for the same
     * combination. {@see EduService::getClassAttendancesInMonthWindow} returns rows ordered by
     * `id` ascending; `mapWithKeys` keeps the last value per date, so the row with the
     * greatest `id` (typically the newest insert) wins for that day.
     */
    public function getAttendancesForClassMonth(int $class_id, string $month, int $year, ?array $user_ids = null): array
    {
        $attendances = app(EduService::class)
            ->getClassAttendancesInMonthWindow(
                $class_id,
                EduMonthWindow::fromLegacyString($month),
                $year,
                $user_ids,
            );

        return $attendances
            ->groupBy('user_id')
            ->map(
                static fn (Collection $userAttendances): array => $userAttendances
                    ->sortBy('id')
                    ->values()
                    ->mapWithKeys(
                        static fn (EduAttendance $a): array => [
                            $a->date->format('Y-m-d') => $a->attendance->value,
                        ]
                    )
                    ->all()
            )
            ->toArray();
    }
}
