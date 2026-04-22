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

    /**
     * TODO Stage 3: wire ClassMonthFacade once P1 delivers it.
     *   $isAdmin = auth()->user()?->isAdmin();
     *   $filters = $isAdmin
     *       ? ['district_id' => ..., 'district_id2' => ..., 'lv3' => ..., 'month' => ...]
     *       : ['class_ids' => auth()->user()->getCoachClassIds(), 'month' => ...];
     *   $data = app(\App\Services\ClassMonthFacade::class)
     *       ->fetchMonthlyClasses($filters, ['with_attendance_level' => 'summary']);
     *   // Returns: month, classes_lv3, processed_classes, no_teacher_num, no_days_num
     *   // See: 10eng-edu2-service-signatures.md §1
     */
    public function index(Request $request)
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        return view('filament.pages.classes', [
            // TODO: Stage 3: replace with ClassMonthFacade return values
            'processedClasses' => [],
            'district'         => [],
            'district2'        => [],
            'classes_lv3'      => [],
            'no_teacher_num'   => 0,
            'no_days_num'      => 0,
            'district_id'      => $request->integer('district_id', 0),
            'district_id2'     => $request->integer('district_id2', 0),
            'lv3'              => $request->get('lv3', ''),
            'is_admin'         => $isAdmin,
        ]);
    }

    /**
     * TODO Stage 3: replace stub data with ClassService calls once P1 delivers:
     *   $classData = app(\App\Services\ClassService::class)->loadClassData($classId);
     *   $classUser = app(\App\Services\ClassService::class)
     *       ->getClassUser($classId, $classData['class_month'], $classYear);
     *   $classExam = app(\App\Services\ClassService::class)->getClassExam($classUser);
     *   $exam      = app(\App\Services\ClassService::class)->get_class_exam_v2($classExam);
     *   $level     = app(\App\Services\ClassService::class)->getAllLevels();
     *   $levelByPid = app(\App\Services\ClassService::class)->sortLevelByPid($level);
     *   // See: 10eng-edu2-service-signatures.md §ClassService
     */
    public function showClass(Request $request, int $id)
    {
        $classYear = $request->get('year', date('Y'));
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        // Use ClassService methods
        $classData = $this->classService->loadClassData($id);
        $class = $classData['class'];
        $classUsers = $classData['class_users'];
        $classMonth = $classData['class_month'];

        $classUser = $this->classService->getClassUser($id, $classMonth, $classYear);
        $classExam = $this->classService->getClassExam($classUser);
        $exam = $this->classService->get_class_exam_v2($classExam);
        $level = $this->classService->getAllLevels();
        $levelByPid = $this->classService->sortLevelByPid($level);

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
        ]);
    }

    public function updateClass(Request $request, int $id)
    {
        // TODO Stage 3: implement update logic using ClassService once P1 delivers.
        //   Validate request data, then call appropriate ClassService method to update class info.
        //   Return success response or error messages as needed.
        return response()->json(['message' => 'Update class functionality not implemented yet.'], 501);
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

            return response()->json([
                'data' => [
                    'student' => $this->jsonService->decode_json($classUser['student']),
                    'student_transfer' => $this->jsonService->decode_json($classUser['student_transfer']),
                    'teacher' => $classUser['teacher'],
                    'class_exam' => $this->jsonService->decode_json($classUser['class_exam']),
                ],
                'message' => '成功'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => '月份不存在',
                'code' => 'NOT_FOUND'
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

        return response()->json(['message' => '更新成功']);
    }
}
