<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\StoreOrderRenewRequest;
use App\Http\Requests\StoreOrderRefundRequest;
use App\Models\EduClassUser;
use App\Models\EduUser;
use App\Services\EduOrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EduOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EduOrderService $ordersService)
    {
    }

    public function searchClasses(Request $request): JsonResponse
    {
        $result = $this->ordersService->getClassList(
            (int)$request->get('page', 1),
            (string)$request->get('kw', '')
        );

        return response()->json($result);
    }

    public function searchMonths(Request $request): JsonResponse
    {
        $result = $this->ordersService->getMonths(
            (int)$request->get('page', 1),
            (string)$request->get('kw', ''),
            (int)$request->get('class_id', 0),
            $request->get('class_year')
        );

        return response()->json($result);
    }

    public function searchRenewMonths(Request $request): JsonResponse
    {
        $result = $this->ordersService->getMonthsForRenew(
            (int)$request->get('page', 1),
            (string)$request->get('kw', ''),
            (int)$request->get('class_id', 0),
            $request->get('class_year')
        );

        return response()->json($result);
    }

    public function showAdd(Request $request)
    {
        $orderId = $request->get('order_id');
        $studentId = (int)$request->get('student_id', 0);

        $data = $orderId
            ? $this->ordersService->getOrderById((int)$orderId)
            : [];

        $lastMonthClass = $this->ordersService->getLastMonthAndLastClass($studentId);

        return view('edu.order.order_add', [
            'data' => $data ?? [],
            'last_month' => $lastMonthClass['last_month'],
            'last_class' => $lastMonthClass['last_class'],
        ]);
    }

    public function showList(Request $request)
    {
        $summary = $this->ordersService->getOrderSummary(
            $request->get('daterange')
        );

        return view('edu.order.order_list', [
            'list' => $summary['list'],
            'amount' => $summary['amount'],
            'refund' => $summary['refund'],
            'users' => $summary['users'],
        ]);
    }

    public function showRefund(Request $request)
    {
        $order = $this->ordersService->getOrderWithClassName(
            (int)$request->get('order_id', 0)
        );

        if (!$order) {
            abort(404, '訂單不存在');
        }

        return view('edu.order.order_refund', [
            'order' => $order,
        ]);
    }

    public function showRenew(Request $request)
    {
        [$order, $nextMonth, $nextYear] = $this->ordersService->getOrderAndNextMonth(
            $request->filled('order_id') ? (int)$request->get('order_id') : null,
            $request->filled('class_id') ? (int)$request->get('class_id') : null,
            $request->get('month'),
            $request->get('class_year')
        );

        return view('edu.order.order_renew', [
            'order' => $order,
            'next_month' => $nextMonth,
            'next_year' => $nextYear,
            'data' => ['order_date' => date('Y-m-d')],
            'student_id' => (int)$request->get('student_id', 0),
        ]);
    }

    public function renewList(Request $request)
    {
        $month = $request->get('month', date('Y-m'));
        $renewType = $request->get('type', 'all');

        // Month navigation — pass ?month= explicitly so getPreAndNextMonth reads correct param
        // ClassService::getPreAndNextMonth reads ?m= internally, so override via request merge
        $request->merge(['m' => $month]);
        $prevNext = app(\App\Services\ClassService::class)->getPreAndNextMonth();

        // Parse year and month number from Y-m string
        [$classYear, $classMonthPadded] = explode('-', $month);
        $classMonth = (int)ltrim($classMonthPadded, '0'); // "03" → 3

        // ── Step 1: Load class_user rows for this month ───────────────────────
        $classUsers = app(\App\Services\EduAdminService::class)
            ->getClassUserForMonthYear($classYear, (string)$classMonth);

        if (empty($classUsers)) {
            return view('edu.admin.renew_list', [
                'month' => $month,
                'prevNext' => $prevNext,
                'filteredStudents' => [],
                'ordersByUser' => [],
                'renewType' => $renewType,
            ]);
        }

        // ── Step 2: Collect all student IDs + class sort values ───────────────
        $allStudentIds = [];
        $classSorts = [];

        foreach ($classUsers as $cu) {
            $students = is_array($cu['student']) ? $cu['student'] : (json_decode($cu['student'] ?? '[]', true) ?? []);
            $transfers = is_array($cu['student_transfer']) ? $cu['student_transfer'] : (json_decode($cu['student_transfer'] ?? '[]', true) ?? []);
            $allStudentIds = array_merge($allStudentIds, $students, $transfers);

            if (!empty($cu['class_id']) && !empty($cu['sort'])) {
                $classSorts[] = [
                    'class_id' => (int)$cu['class_id'],
                    'sort' => (int)$cu['sort'],
                ];
            }
        }

        $allStudentIds = array_values(array_unique(array_filter(array_map('intval', $allStudentIds))));

        if (empty($allStudentIds)) {
            return view('edu.admin.renew_list', [
                'month' => $month,
                'prevNext' => $prevNext,
                'filteredStudents' => [],
                'ordersByUser' => [],
                'renewType' => $renewType,
            ]);
        }

        // ── Step 3: Batch next periods — ClassStudentQueryService (10eng §7) ──
        $adjacent = app(\App\Services\ClassStudentQueryService::class)
            ->getAdjacentPeriodsBatch($classSorts);
        $nextPeriods = $adjacent['next'] ?? [];

        $nextPeriodIndex = [];
        foreach ($classSorts as $cs) {
            $key = "{$cs['class_id']}_{$cs['sort']}";
            if (isset($nextPeriods[$key])) {
                $nextPeriodIndex[$cs['class_id']] = [
                    'month' => $nextPeriods[$key]['month'],
                    'class_year' => $nextPeriods[$key]['class_year'],
                ];
            }
        }

        // ── Step 4: Batch payments — StudentPaymentServiceCommon (10eng §8) ──
        // Canonical batch method — loads only orders for students in scope
        $paymentsBatch = app(\App\Services\StudentPaymentServiceCommon::class)
            ->getStudentPaymentsBatch($allStudentIds);
        // Returns [student_id => ['woocommerce' => [...], 'whatsapp' => [...]]]

        // Build orders_by_user keyed by user_id — grouped before rendering (no per-student queries)
        $ordersByUser = [];
        foreach ($allStudentIds as $sid) {
            $payments = $paymentsBatch[$sid] ?? ['woocommerce' => [], 'whatsapp' => []];
            foreach (array_merge($payments['woocommerce'], $payments['whatsapp']) as $order) {
                $order = (array)$order;
                $ordersByUser[$sid][] = [
                    'class_name' => $order['class_name'] ?? $order['woo_class_name'] ?? '',
                    'month' => $order['month'] ?? $order['pa_month'] ?? '',
                    'class_year' => $order['class_year'] ?? '',
                    'class_id' => $order['class_id'] ?? null,
                ];
            }
        }

        // ── Step 5: Batch load WP users — EduStudentService::getUsersWithMeta ─
        $wpUsers = app(\App\Services\EduStudentService::class)
            ->getUsersWithMeta($allStudentIds);

        // ── Step 6: Determine paid status per student ─────────────────────────
        $students = [];
        foreach ($allStudentIds as $sid) {
            $user = $wpUsers[$sid] ?? null;
            if (!$user) continue;

            $paid = 0;
            $userOrders = $ordersByUser[$sid] ?? [];

            foreach ($userOrders as $order) {
                $orderClassId = (int)($order['class_id'] ?? 0);
                $orderMonth = $order['month'] ?? '';
                $orderClassYear = (string)($order['class_year'] ?? '');
                $nextForClass = $nextPeriodIndex[$orderClassId] ?? null;

                if (
                    $nextForClass &&
                    $orderMonth === $nextForClass['month'] &&
                    $orderClassYear === (string)$nextForClass['class_year']
                ) {
                    $paid = 1;
                    break;
                }
            }

            $students[$sid] = [
                'ID' => $sid,
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'billing_phone' => $user['billing_phone'] ?? '',
                'paid' => $paid,
            ];
        }

        // Sort by paid ascending (未續費 first)
        uasort($students, fn($a, $b) => $a['paid'] <=> $b['paid']);

        // ── Step 7: Filter by renew_type ──────────────────────────────────────
        $filteredStudents = match ((string)$renewType) {
            '0' => array_filter($students, fn($s) => $s['paid'] === 0),
            '1' => array_filter($students, fn($s) => $s['paid'] === 1),
            '2' => array_filter($students, fn($s) => $s['paid'] === 2),
            default => $students,
        };

        return view('edu.admin.renew_list', [
            'month' => $month,
            'prevNext' => $prevNext,
            'filteredStudents' => $filteredStudents,
            'ordersByUser' => $ordersByUser,
            'renewType' => $renewType,
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->ordersService->addOrUpdateOrder(
            null,
            (int)$request->input('student_id'),
            $request->validated()
        );

        return $this->success(['order_id' => $order->id]);
    }

    public function update(StoreOrderRequest $request, int $id): JsonResponse
    {
        $order = $this->ordersService->addOrUpdateOrder(
            $id,
            (int)$request->input('student_id'),
            $request->validated()
        );

        return $this->success(['order_id' => $order->id]);
    }

    public function storeRenew(StoreOrderRenewRequest $request): JsonResponse
    {
        $result = $this->ordersService->orderAddForRenew(
            $request->validated(),
            (int)$request->input('student_id')
        );

        if (!$result['status']) {
            return $this->error($result['message'], 'DUPLICATE_RENEW', 422);
        }

        return $this->success(['message' => 'Success']);
    }

    public function storeRefund(StoreOrderRefundRequest $request): JsonResponse
    {
        $result = $this->ordersService->processOrderRefund(
            (int)$request->input('order_id'),
            $request->validated()
        );

        if (!$result['status']) {
            return $this->error($result['message'], 'REFUND_ERROR', 422);
        }

        return $this->success(['message' => 'Success']);
    }
}