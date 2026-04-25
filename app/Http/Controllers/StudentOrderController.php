<?php

namespace App\Http\Controllers;

use App\Services\StudentPaymentServiceCommon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentOrderController extends Controller
{
    public function index(Request $request, StudentPaymentServiceCommon $paymentService): View
    {
        $userId = $request->user()->ID;

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

        return view('account.myorder', compact('allOrders'));
    }
}
