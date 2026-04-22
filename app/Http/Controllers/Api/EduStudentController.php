<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EduStudentService;
use App\Models\EduClassUser;
use App\Services\Common\StudentPaymentServiceCommon;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EduStudentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EduStudentService $studentService,
        private readonly StudentPaymentServiceCommon $paymentService,
    ) {}

    public function show(Request $request)
    {
        $studentId = (int)$request->get('user_id', 0);

        if (!$studentId) {
            abort(404);
        }

        $profile = $this->studentService->getStudentProfileData($studentId);

        $isCoach = EduClassUser::forCoach($studentId)->exists();

        $classMons = $this->studentService->getStudentClasses($studentId);

        $allPayments = $this->paymentService->getStudentAllPayments($studentId);

        $orderList = $allPayments['woocommerce'];
        $whatsappOrders = $allPayments['whatsapp'];
        $orderAll = array_merge($orderList, $whatsappOrders);

        foreach ($orderAll as &$order) {
            if (isset($order['class_id'])) $order['class_id'] = (int)$order['class_id'];
            if (isset($order['class_year'])) $order['class_year'] = (int)$order['class_year'];
        }
        unset($order);

        $noMoreRenew = [];

        $displayOrders = app(StudentPaymentServiceCommon::class)
            ->displayOrders($orderList, $orderAll, $studentId, $noMoreRenew);

        $whatsappOrdersView = app(StudentPaymentServiceCommon::class)
            ->prepareWhatappOrders($whatsappOrders, $orderAll, $studentId, [], $noMoreRenew);

        //TODO: replace with AttendanceQueryService call in stage 3
        $userAttendMonths = [];

        $view = $isCoach
            ? 'edu.student.student_coach'
            : 'edu.student.student_admin';

        return view($view, [
            'student' => $profile['student'],
            'student_id' => $studentId,
            'user_id' => $studentId,
            'calStudentAge' => $profile['age'],
            'note' => $profile['note'],
            'is_user_teacher' => $isCoach,
            'class_months' => $classMons,
            'display_orders' => $displayOrders,
            'whatsapp_orders' => $whatsappOrdersView,
            'userAttendMonths' => $userAttendMonths,
        ]);
    }

    public function storeFee(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'class_id' => ['required', 'integer', 'min:1'],
            'class_fee' => ['required', 'numeric', 'min:0'],
        ]);

        $this->studentService->updateStudentFee($id, (float)$request->input('class_fee'));

        return $this->success(['message' => 'Success']);
    }

    public function downloadSalary(Request $request, int $id)
    {
        // TODO Stage 4: call CoachSalaryServiceCommon::downloadSalarySheet()
        abort(501, 'Salary download not yet implemented — Stage 4 scope.');
    }
}
