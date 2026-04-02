<?php

namespace App\Console\Commands;

use App\Models\EduAttendance;
use App\Services\EduService;
use App\ValueObjects\EduMonthWindow;
use Illuminate\Console\Command;

class AttendanceVerifyCommand extends Command
{
    protected $signature = 'attendance:verify {class_id} {month_window} {year}';

    protected $description = 'Verifies that attendance records for a given class and month window can be retrieved correctly.';

    public function handle(): void
    {
        $attendances = app(EduService::class)
            ->getClassAttendancesInMonthWindow(
                $this->argument('class_id'),
                EduMonthWindow::fromLegacyString($this->argument('month_window')),
                $this->argument('year')
            );

        $this->table(
            ['id', 'class_id', 'month', 'user_id', 'date', 'attendance', 'class_year'],
            $attendances
                ->map(static fn (EduAttendance $entry) => [
                    $entry->id,
                    $entry->class_id,
                    $entry->month,
                    $entry->user_id,
                    $entry->date,
                    $entry->attendance->value,
                    $entry->class_year,
                ]),
        );

        $this->newLine();
        $this->comment("{$attendances->count()} records found.");
    }
}
