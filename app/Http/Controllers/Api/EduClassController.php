<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EduClassUser;
use App\Services\ClassService;
use App\Services\DateDayWeekService;
use App\Services\JsonService;
use Illuminate\Http\Request;

class EduClassController extends Controller
{
    public function __construct(
        private ClassService $classService,
        private DateDayWeekService $dateDayWeekService,
        private JsonService $jsonService,
    ) {}

    public function index(Request $request)
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        // 03eng §III: coaches cannot read the class list in Laravel
        if (!$isAdmin) {
            abort(403);
        }

        $districtId = $request->integer('district_id', 0);
        $districtId2 = $request->integer('district_id2', 0);
        $lv3 = $request->get('lv3', '');
        $month = $request->get('m', date('Y-m')); // JS uses ?m= param

        $filters = [
            'district_id' => $districtId,
            'district_id2' => $districtId2,
            'lv3' => $lv3,
            'month' => $month,
        ];

        // ClassMonthFacade::fetchMonthlyClasses (10eng §1)
        $data = app(\App\Services\ClassMonthFacade::class)
            ->fetchMonthlyClasses($filters, ['with_attendance_level' => 'summary']);

        // Prev/next month navigation for month-selector component
        $preNextMonth = app(\App\Services\ClassService::class)->getPreAndNextMonth();

        // Build district lists from classes_lv3 for district-menu component
        $district = [];
        $district2 = [];
        foreach ($data['classes_lv3'] as $c) {
            if (!empty($c['district_id']) && !isset($district[$c['district_id']])) {
                $district[$c['district_id']] = $c['district_name'] ?? $c['district_id'];
            }
        }

        return view('filament.pages.classes', [
            'processedClasses' => $data['processed_classes'],
            'classes_lv3' => $data['classes_lv3'],
            'no_teacher_num' => $data['no_teacher_num'],
            'no_days_num' => $data['no_days_num'],
            'month' => $data['month'],
            'preNextMonth' => $preNextMonth,
            'district' => $district,
            'district2' => $district2,
            'district_id' => $districtId,
            'district_id2' => $districtId2,
            'lv3' => $lv3,
            'current_user' => null, // populated when user_id param present
            'is_admin' => $isAdmin,
        ]);
    }

    public function showClass(Request $request, int $id)
    {
        $classYear = $request->get('year', date('Y'));
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        $classService = app(\App\Services\ClassService::class);

        // ClassService::loadClassData — loads class, class_users, class_month (10eng §4)
        // Returns: ['class' => [...], 'class_users' => [...], 'class_month' => '3月-4月']
        $classData = $classService->loadClassData($id);
        $class = $classData['class'];
        $classUsers = $classData['class_users'];
        $classMonth = $classData['class_month'];

        // ClassService::getClassUser — single class_user row for selected month/year
        $classUser = $classService->getClassUser($id, $classMonth, $classYear);

        // ClassService::getClassExam — decode class_exam JSON from class_user
        $classExam = $classService->getClassExam($classUser);

        // ClassService::get_class_exam_v2 — resolve exam IDs to full level detail
        $exam = $classService->get_class_exam_v2($classExam);

        // ClassService::getAllLevels + sortLevelByPid — assessment tree
        $level = $classService->getAllLevels();
        $levelByPid = $classService->sortLevelByPid($level);

        // StudentPaymentServiceCommon::getStudentPaymentsBatch (10eng §8)
        $students = is_array($classUser['student']) ? $classUser['student'] : (json_decode($classUser['student'] ?? '[]', true) ?? []);
        $transfers = is_array($classUser['student_transfer']) ? $classUser['student_transfer'] : (json_decode($classUser['student_transfer'] ?? '[]', true) ?? []);
        $studentIds = array_values(array_unique(array_merge($students, $transfers)));

        $paymentsBatch = !empty($studentIds)
            ? app(\App\Services\StudentPaymentServiceCommon::class)->getStudentPaymentsBatch($studentIds)
            : [];

        return view('edu.class.class', [
            'class_id' => $id,
            'class' => $class,
            'exam' => $exam,
            'class_users' => $classUsers,
            'class_year' => $classYear,
            'class_month' => $classMonth,
            'level' => $level,
            'levelByPid' => $levelByPid,
            'is_admin' => $isAdmin,
            'payments' => $paymentsBatch,
        ]);
    }

    /**
     * Handle 1: Get previous month data and merge with current
     * POST /edu/class/{id}/prev-data
     */
    public function getPrevData(Request $request, int $id)
    {
        try {
            // Get current class_user from request
            $classMonth = $request->post('class_month');
            $classYear = $request->post('class_year');

            $classUser = $this->classService->getClassUser($id, $classMonth, $classYear);

            // Get previous month
            $prevMonth = $this->dateDayWeekService->get_string_prev_month($classUser['month']);
            $prev = EduClassUser::where('class_id', $id)
                ->where('month', $prevMonth)
                ->orderByDesc('id')
                ->first();

            if (!$prev) {
                return response()->json([
                    'message' => '上月班級不存在',
                    'code' => 'NOT_FOUND'
                ], 404);
            }

            // Verify continuity
            $monthNext = $this->dateDayWeekService->get_string_next_month($prev->month);
            if ($monthNext != $classMonth) {
                return response()->json([
                    'message' => '上月班級不存在',
                    'code' => 'NOT_FOUND'
                ], 404);
            }

            // Merge student data
            $prevStudent = $this->jsonService->decode_json($prev->student) ?: [];
            $currStudent = $this->jsonService->decode_json($classUser['student']) ?: [];
            $prevTransfer = $this->jsonService->decode_json($prev->student_transfer) ?: [];
            $currTransfer = $this->jsonService->decode_json($classUser['student_transfer']) ?: [];
            $prevMakeup = $this->jsonService->decode_json($prev->student_makeup ?? '[]') ?: [];
            $currMakeup = $this->jsonService->decode_json($classUser['student_makeup'] ?? '[]') ?: [];

            $updateData = [
                'student' => json_encode(array_unique(array_merge($prevStudent, $currStudent))),
                'student_transfer' => json_encode(array_unique(array_merge($prevTransfer, $currTransfer))),
                'student_makeup' => json_encode(array_unique(array_merge($prevMakeup, $currMakeup))),
                'teacher' => $prev->teacher,
                'class_exam' => $prev->class_exam,
            ];

            EduClassUser::where('id', $classUser['id'])->update($updateData);

            return response()->json([
                'data' => null,
                'message' => '成功'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => '未找到對應月份的班級',
                'code' => 'NOT_FOUND'
            ], 404);
        }
    }

    /**
     * Handle 2: Add exam item
     * POST /edu/class/{id}/exam
     */
    public function addExam(Request $request, int $id)
    {
        try {
            $examId = $request->post('id');
            $classMonth = $request->post('month');
            $classYear = $request->post('year');

            $classUser = $this->classService->getClassUser($id, $classMonth, $classYear);
            $classExam = $this->classService->getClassExam($classUser);

            // Add exam ID
            $classExam[] = $examId;

            // Update
            EduClassUser::where('id', $classUser['id'])->update([
                'class_exam' => json_encode($classExam)
            ]);

            return response()->json([
                'data' => null,
                'message' => '成功'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => '未找到對應月份的班級',
                'code' => 'NOT_FOUND'
            ], 404);
        }
    }

    /**
     * Handle 3: Update user (student, teacher, exam)
     * PUT /edu/class/{id}/user  ← RENAMED (was /class/{id} to avoid conflict with updateClass)
     */
    public function updateUser(Request $request, int $id)
    {
        $classMonth = $request->post('month');
        $classYear = $request->post('year');

        $classUser = $this->classService->getClassUser($id, $classMonth, $classYear);

        $updateData = [];

        if ($request->has('student')) {
            $updateData['student'] = json_encode($request->post('student'));
        }

        if ($request->has('student_makeup')) {
            $updateData['student_makeup'] = json_encode($request->post('student_makeup'));
        }

        if ($request->has('student_transfer')) {
            $updateData['student_transfer'] = json_encode($request->post('student_transfer'));
        }

        if ($request->has('teacher')) {
            $updateData['teacher'] = $request->post('teacher');
        }

        if ($request->has('class_exam')) {
            $updateData['class_exam'] = json_encode($request->post('class_exam'));
        }

        if (!empty($updateData)) {
            EduClassUser::where('id', $classUser['id'])->update($updateData);
        }

        return response()->json([
            'data' => null,
            'message' => '成功'
        ], 200);
    }

    /**
     * Handle 4: Get month data
     * POST /edu/class/{id}/month-data
     */
    public function getMonthData(Request $request, int $id)
    {
        try {
            $targetMonth = $request->post('month');
            $classYear = $request->post('year');

            $classUser = $this->classService->getClassUser($id, $targetMonth, $classYear);

            // Decode all student lists
            $student = $this->jsonService->decode_json($classUser['student']) ?: [];
            $studentMakeup = $this->jsonService->decode_json($classUser['student_makeup'] ?? '[]') ?: [];
            $studentTransfer = $this->jsonService->decode_json($classUser['student_transfer']) ?: [];

            // Batch payment data for all students in this period (10eng §8)
            $allStudentIds = array_values(array_unique(array_filter(
                array_merge(
                    array_map('intval', $student),
                    array_map('intval', $studentMakeup),
                    array_map('intval', $studentTransfer)
                )
            )));

            $payments = !empty($allStudentIds)
                ? app(\App\Services\StudentPaymentServiceCommon::class)
                    ->getStudentPaymentsBatch($allStudentIds)
                : [];

            return response()->json([
                'data' => [
                    'student' => $student,
                    'student_makeup' => $studentMakeup,
                    'student_transfer' => $studentTransfer,
                    'teacher' => $classUser['teacher'],
                    'class_exam' => $this->jsonService->decode_json($classUser['class_exam']),
                    'analytisc_days' => $classUser['analytisc_days'] ?? '',
                    'date_time' => $classUser['days'] ?? '',
                    'payments' => $payments,
                ],
                'message' => '成功',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => '月份不存在',
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    /**
     * Handle 5: Get level 2 items
     * POST /edu/class/{id}/levels
     */
    public function getLevel2(Request $request, int $id)
    {
        $lv1Id = $request->post('lv1_id');

        $levels = $this->classService->getAllLevels();
        $lv2Levels = $this->classService->sortLevelByPid($levels);

        // Filter by pid
        $filtered = array_filter($levels, function ($level) use ($lv1Id) {
            return $level['pid'] == $lv1Id;
        });

        return response()->json([
            'message' => 'success',
            'data' => array_values($filtered)
        ]);
    }

    /**
     * Handle 6: Update exam (add/remove)
     * PUT /edu/class/{id}/exam
     */
    public function updateExam(Request $request, int $id)
    {
        $examId = $request->post('exam_id');
        $action = $request->post('action'); // 'add' or 'remove'
        $classMonth = $request->post('month');
        $classYear = $request->post('year');

        $classUser = $this->classService->getClassUser($id, $classMonth, $classYear);
        $classExam = $this->classService->getClassExam($classUser);

        if ($action == 'add') {
            if (!in_array($examId, $classExam)) {
                $classExam[] = $examId;
            }
        } elseif ($action == 'remove') {
            $classExam = array_filter($classExam, function ($id) use ($examId) {
                return $id != $examId;
            });
        }

        // Update
        EduClassUser::where('id', $classUser['id'])->update([
            'class_exam' => json_encode(array_values($classExam))
        ]);

        return response()->json(['data' => null, 'message' => '更新成功']);
    }
}
