<?php

namespace App\Http\Controllers;

use App\Enums\EduAttendanceStatus;
use App\Models\EduAttendance;
use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Services\AttendanceQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StudentAttendanceController extends Controller
{
    public function index(Request $request, AttendanceQueryService $attendanceService): View
    {
        $user = $request->user();
        $userId = $user->ID;

        $classMonths = EduClassUser::whereAnyRoleJsonLike((string) $userId)
            ->get()
            ->map(fn ($cu) => [
                'user_id' => $userId,
                'class_id' => (int) $cu->class_id,
                'month' => $cu->getRawOriginal('month'),
                'year' => $cu->class_year,
            ])->all();

        $attendData = $attendanceService->fetchUsersMonthAttendByClassMonths(
            [$userId], $classMonths
        );

        $classIds = array_unique(array_column($classMonths, 'class_id'));
        $classes = EduClass::whereIn('class_id', $classIds)->pluck('class_name', 'class_id');

        $records = $attendData[$userId] ?? [];

        return view('account.attend', compact('records', 'classes'));
    }

    public function leaveRequest(Request $request): JsonResponse
    {
        $request->validate([
            'attendance_id' => 'required|integer|exists:edu_attendance,id',
            'status' => 'required|in:leave,present',
        ]);

        $attendance = EduAttendance::findOrFail($request->attendance_id);

        if ((int) $attendance->user_id !== $request->user()->ID) {
            return response()->json([
                'message' => 'Unauthorized',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        $date = Carbon::parse($attendance->getRawOriginal('date'));
        if ($date->isBefore(today())) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['date' => ['Cannot modify past attendance records']],
            ], 422);
        }

        $attendance->attendance = EduAttendanceStatus::from($request->status);
        $attendance->save();

        return response()->json([
            'data' => [
                'id' => $attendance->id,
                'status' => $attendance->attendance->value,
                'date' => $attendance->getRawOriginal('date'),
            ],
            'message' => 'success',
        ]);
    }
}
