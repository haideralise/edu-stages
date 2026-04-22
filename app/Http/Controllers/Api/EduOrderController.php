<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\StoreOrderRenewRequest;
use App\Http\Requests\StoreOrderRefundRequest;
use App\Services\EduOrdersService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EduOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EduOrdersService $ordersService){}

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