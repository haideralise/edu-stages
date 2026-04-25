<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Services\AttendanceQueryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function index(Request $request, AttendanceQueryService $attendanceService): JsonResponse
    {
        $user = $request->user();
        $userId = $user->ID;
        $role = $user->resolveRole();

        // Student scope: own records only
        if ($role === 'student') {
            if ($request->filled('user_id') && $request->integer('user_id') !== $userId) {
                return $this->error('Forbidden', 'FORBIDDEN', 403);
            }
        } elseif ($role === 'admin') {
            if ($request->filled('user_id')) {
                $userId = $request->integer('user_id');
            }
        }

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
        $classes = $classIds !== []
            ? EduClass::whereIn('class_id', $classIds)->pluck('class_name', 'class_id')->all()
            : [];

        $records = $attendData[$userId] ?? [];

        // Flatten into API-friendly structure
        $data = [];
        foreach ($records as $classId => $months) {
            foreach ($months as $month => $dates) {
                $data[] = [
                    'class_id' => $classId,
                    'class_name' => $classes[$classId] ?? null,
                    'month' => $month,
                    'dates' => collect($dates)->map(fn ($status, $date) => [
                        'date' => $date,
                        'status' => $status,
                    ])->values()->all(),
                ];
            }
        }

        return $this->success($data);
    }
}
