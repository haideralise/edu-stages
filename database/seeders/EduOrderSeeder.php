<?php

namespace Database\Seeders;

use App\Models\EduOrder;
use Illuminate\Database\Seeder;

class EduOrderSeeder extends Seeder
{
    public function run(): void
    {
        // Case 1: Direct class match — user 101, class 10, month 5月
        EduOrder::create([
            'user_id' => 101, 'class_id' => 10, 'month' => '5月',
            'class_year' => '2024', 'amount' => 800.00,
            'order_date' => now()->subDays(10)->timestamp, 'created' => now()->timestamp,
            'refund_fee' => 0, 'refund_date' => '', 'refund_reason' => '',
            'last_days' => '', 'gateway' => '', 'avgfee' => '', 'type' => '',
            'woo_status' => '', 'woo_class_name' => '', 'woo_order_id' => 0,
            'order_source' => EduOrder::SOURCE_MANUAL,
        ]);

        // Case 2: Transfer — user 102 paid for class 99, same month
        EduOrder::create([
            'user_id' => 102, 'class_id' => 99, 'month' => '5月',
            'class_year' => '2024', 'amount' => 750.00,
            'order_date' => now()->subDays(10)->timestamp, 'created' => now()->timestamp,
            'refund_fee' => 0, 'refund_date' => '', 'refund_reason' => '',
            'last_days' => '', 'gateway' => '', 'avgfee' => '', 'type' => '',
            'woo_status' => '', 'woo_class_name' => '', 'woo_order_id' => 0,
            'order_source' => EduOrder::SOURCE_MANUAL,
        ]);

        // Case 3: Refunded order — user 103, class 10
        EduOrder::create([
            'user_id' => 103, 'class_id' => 10, 'month' => '5月',
            'class_year' => '2024', 'amount' => 900.00,
            'order_date' => now()->subDays(10)->timestamp, 'created' => now()->timestamp,
            'refund_fee' => 900.00, 'refund_date' => '2024-05-15', 'refund_reason' => 'test refund',
            'last_days' => '', 'gateway' => '', 'avgfee' => '', 'type' => '',
            'woo_status' => '', 'woo_class_name' => '', 'woo_order_id' => 0,
            'order_source' => EduOrder::SOURCE_MANUAL,
        ]);

        // Case 5: Range month — user 104, class 10, month "5月-6月" matches query "5月"
        EduOrder::create([
            'user_id' => 104, 'class_id' => 10, 'month' => '5月-6月',
            'class_year' => '2024', 'amount' => 1500.00,
            'order_date' => now()->subDays(10)->timestamp, 'created' => now()->timestamp,
            'refund_fee' => 0, 'refund_date' => '', 'refund_reason' => '',
            'last_days' => '', 'gateway' => '', 'avgfee' => '', 'type' => '',
            'woo_status' => '', 'woo_class_name' => '', 'woo_order_id' => 0,
            'order_source' => EduOrder::SOURCE_MANUAL,
        ]);
    }
}
