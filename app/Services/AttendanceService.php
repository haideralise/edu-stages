<?php

namespace App\Services;

use App\Services\AttendanceQueryService;
use App\Services\AttendanceSummaryService;
use App\Services\ClassMonthFacade;

/**
 * Slim Laravel counterpart to edu2 AttendanceService (10eng §3).
 *
 * Legacy `getAttendanceData()` is deprecated in edu2; use {@see ClassMonthFacade::fetchMonthlyClasses()}
 * with `with_attendance_level => 'summary'` for admin/coach class-month payloads (attendance via
 * {@see AttendanceQueryService} / {@see AttendanceSummaryService}).
 */
class AttendanceService
{
    public function __construct(
        private ClassService $classService,
    ) {}

    /**
     * Prev/next month labels (same contract as edu2 ClassService::getPreAndNextMonth).
     *
     * @return list<string>
     */
    public function getPreAndNextMonth(): array
    {
        return $this->classService->getPreAndNextMonth();
    }
}
