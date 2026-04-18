<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EduAttendanceController extends Controller
{
    use ApiResponse;

    private const CLASS_OPTIONS = [
        '幼兒游泳班',
        '兒童游泳班',
        '成人游泳班',
        '暑期幼兒游泳班',
        '暑期兒童游泳班',
        '成人四式改良班',
        '女子成人游泳班',
    ];

    public function index(Request $request)
    {
        $tStart = microtime(true);

        $isAdmin = auth()->user()?->isAdmin() ?? false;

        // wait for p1
        // $attendanceData = app(AttendanceQueryService::class)
        //     ->fetchUsersMonthAttendByClassMonths([...]);

        $attendanceData = [
            'months' => [],
            'district_id' => (int)$request->get('district_id', 0),
            'district_id2' => (int)$request->get('district_id2', 0),
            'lv3' => $request->get('lv3', ''),
            'district' => [],
            'district2' => [],
            'classes_lv3' => [],
            'class' => null,
            'classes' => [],
            'classes_select' => [],
            'class_id' => (int)$request->get('class_id', 0),
            'class_user' => [],
        ];

        if (!empty($attendanceData['classes_lv3'])) {
            $attendanceData['classes_lv3'] = collect($attendanceData['classes_lv3'])
                ->unique('lv3')
                ->values()
                ->toArray();
        }

        $pageLoadMs = null;
        if ($isAdmin && auth()->user()?->user_login === 'mssc') {
            $pageLoadMs = round((microtime(true) - $tStart) * 1000, 2);
        }

        $view = $isAdmin
            ? 'edu.attendance.attendance_admin'
            : 'edu.attendance.attendance_coach';

        return view($view, [
            'option_arr' => self::CLASS_OPTIONS,
            'months' => $attendanceData['months'],
            'district_id' => $attendanceData['district_id'],
            'district_id2' => $attendanceData['district_id2'],
            'lv3' => $attendanceData['lv3'],
            'district' => $attendanceData['district'],
            'district2' => $attendanceData['district2'],
            'classes_lv3' => $attendanceData['classes_lv3'],
            'class' => $attendanceData['class'],
            'classes' => $attendanceData['classes'],
            'classes_select' => $attendanceData['classes_select'],
            'is_admin' => $isAdmin,
            'class_id' => $attendanceData['class_id'],
            'class_user' => $attendanceData['class_user'],
            'pageLoadMs' => $pageLoadMs,
        ]);
    }

    public function ajaxUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'month' => ['required', 'string'],
            'attendance' => ['required', 'string', 'in:present,leave,cancelled,clear'],
        ]);

        $classId = (int)$request->input('class_id');
        $userId = (int)$request->input('user_id');
        $date = $request->input('date');
        $month = $request->input('month');
        $attendance = $request->input('attendance');

        if ($attendance === 'clear') {
            DB::table('edu_attendance')
                ->where('class_id', $classId)
                ->where('user_id', $userId)
                ->where('date', $date)
                ->delete();
        } else {
            DB::table('edu_attendance')
                ->updateOrInsert(
                    [
                        'class_id' => $classId,
                        'user_id' => $userId,
                        'date' => $date,
                    ],
                    [
                        'month' => $month,
                        'attendance' => $attendance,
                        'class_year' => substr($date, 0, 4),
                    ]
                );
        }

        return $this->success(['message' => 'Success']);
    }

    public function ajaxDelete(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        DB::table('edu_attendance')
            ->where('class_id', (int)$request->input('class_id'))
            ->where('date', $request->input('date'))
            ->delete();

        return $this->success(['message' => 'Success']);
    }
}