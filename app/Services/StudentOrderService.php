<?php

namespace App\Services;

use App\Models\EduOrder;
use Illuminate\Support\Collection;

class StudentOrderService
{

    public function getFeeForStudentInClass(int $user_id, int $class_id, string $class_month, int $class_year): ?float
    {
        if (empty($class_month)) {
            return null;
        }

        $orders = EduOrder::validOnly()
            ->where('user_id', $user_id)
            ->where('class_year', (string) $class_year)
            ->orderByDesc('order_date')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $monthMatched = $orders->filter(
            fn($o) => MonthMatchService::isMonthMatched($o->month ?? '', $class_month)
        );

        if ($monthMatched->isEmpty()) {
            return null;
        }

        $direct = $monthMatched->where('class_id', $class_id)->first();
        if ($direct) {
            return (float) $direct->amount;
        }

        $transfer = $monthMatched->whereNotNull('class_id')
            ->where('class_id', '!=', $class_id)
            ->first();
        if ($transfer) {
            return (float) $transfer->amount;
        }

        $nullClass = $monthMatched->whereNull('class_id')->first();
        if ($nullClass) {
            return (float) $nullClass->amount;
        }

        return null;
    }

    public function getOrderMapForClasses(array $classes, array $student_ids): array
    {
        if (empty($classes) || empty($student_ids)) {
            return [];
        }

        $years = collect($classes)->pluck('class_year')->unique()->map(fn($y) => (string) $y)->toArray();

        $allOrders = EduOrder::validOnly()
            ->whereIn('user_id', $student_ids)
            ->whereIn('class_year', $years)
            ->orderByDesc('order_date')
            ->get()
            ->groupBy('user_id');

        $result = [];

        foreach ($classes as $class) {
            $classId    = (int) $class['class_id'];
            $classMonth = $class['class_month'] ?? '';
            $classYear  = (int) $class['class_year'];

            if (empty($classMonth)) {
                continue;
            }

            foreach ($student_ids as $userId) {
                $userOrders = $allOrders->get($userId, collect());

                $matched = $userOrders->filter(function ($o) use ($classYear, $classMonth) {
                    return (int) $o->class_year === $classYear
                        && MonthMatchService::isMonthMatched($o->month ?? '', $classMonth);
                });

                if ($matched->isEmpty()) {
                    continue;
                }

                $order = $matched->where('class_id', $classId)->first()
                    ?? $matched->whereNotNull('class_id')->where('class_id', '!=', $classId)->first()
                    ?? $matched->whereNull('class_id')->first();

                if ($order) {
                    $key = "{$userId}_{$classId}";
                    $result[$key] = [
                        'amount'     => (float) $order->amount,
                        'order_date' => (int) $order->order_date,
                    ];
                }
            }
        }

        return $result;
    }

    public function getStudentOrderMap(array $classes, array $student_ids): array
    {
        return $this->getOrderMapForClasses($classes, $student_ids);
    }
}
