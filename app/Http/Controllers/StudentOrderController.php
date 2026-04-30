<?php

namespace App\Http\Controllers;

use App\Models\EduClass;
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

        $classIds = array_unique(array_filter(array_column($whatsapp_orders, 'class_id')));
        $classes = $classIds !== []
            ? EduClass::whereIn('class_id', $classIds)->get()
                ->keyBy('class_id')->map(fn ($m) => $m->getAttributes())->all()
            : [];

        $waOrders = $paymentService->prepareWhatappOrders(
            $whatsapp_orders, $order_all, $userId, $classes, []
        );

        $allOrders = array_merge($displayOrders, $waOrders);

        return view('account.myorder', compact('allOrders'));
    }
}
