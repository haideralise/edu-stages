<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudentPaymentServiceCommon;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request, StudentPaymentServiceCommon $paymentService): JsonResponse
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

        $payments = $paymentService->getStudentAllPayments($userId);
        $order_list = $payments['woocommerce'];
        $whatsapp_orders = $payments['whatsapp'];
        $order_all = array_merge($order_list, $whatsapp_orders);

        $displayOrders = $paymentService->displayOrders(
            $order_list, $order_all, $userId, []
        );

        $waOrders = $paymentService->prepareWhatappOrders(
            $whatsapp_orders, $order_all, $userId, [], []
        );

        $allOrders = array_merge($displayOrders, $waOrders);

        return $this->success($allOrders);
    }
}
