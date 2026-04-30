<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds realistic attendance + order data for student_chan (ID=6)
 * so that web pages and API endpoints return visible data for screenshots.
 *
 * Run: php artisan db:seed --class=Stage34EvidenceSeeder
 * Rollback: php artisan db:seed --class=Stage34EvidenceSeeder -- --rollback
 */
class Stage34EvidenceSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 6; // student_chan

        // ── Attendance records ──────────────────────────────────────
        // student_chan is enrolled in 3 classes (class_id 1, 3, 5)
        $attendance = [
            // Class 1 — 鑽石山游泳初班 (Sat AM), month 1月-2月
            ['class_id' => 1, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-04', 'attendance' => 'present', 'class_year' => '2025'],
            ['class_id' => 1, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-11', 'attendance' => 'present', 'class_year' => '2025'],
            ['class_id' => 1, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-18', 'attendance' => 'leave',   'class_year' => '2025'],
            ['class_id' => 1, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-25', 'attendance' => 'present', 'class_year' => '2025'],

            // Class 3 — 黃大仙游泳初班 (Sun AM), month 1月-2月
            ['class_id' => 3, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-05', 'attendance' => 'present', 'class_year' => '2025'],
            ['class_id' => 3, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-12', 'attendance' => 'present', 'class_year' => '2025'],
            ['class_id' => 3, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-19', 'attendance' => 'cancelled', 'class_year' => '2025'],
            ['class_id' => 3, 'month' => '1月-2月', 'user_id' => $userId, 'date' => '2025-01-26', 'attendance' => 'present', 'class_year' => '2025'],

            // Class 5 — 沙田游泳初班 (Sat PM), month 5月-6月
            ['class_id' => 5, 'month' => '5月-6月', 'user_id' => $userId, 'date' => '2025-05-03', 'attendance' => 'present', 'class_year' => '2025'],
            ['class_id' => 5, 'month' => '5月-6月', 'user_id' => $userId, 'date' => '2025-05-10', 'attendance' => 'present', 'class_year' => '2025'],
            ['class_id' => 5, 'month' => '5月-6月', 'user_id' => $userId, 'date' => '2025-05-17', 'attendance' => 'present', 'class_year' => '2025'],
            ['class_id' => 5, 'month' => '5月-6月', 'user_id' => $userId, 'date' => '2025-05-24', 'attendance' => 'leave',   'class_year' => '2025'],
        ];

        DB::table('edu_attendance')->insert($attendance);
        $this->command->info('Inserted ' . count($attendance) . ' attendance records for student_chan (ID=6)');

        // ── Order records ───────────────────────────────────────────
        $now = now()->timestamp;
        $orders = [
            [
                'class_id' => 1, 'month' => '1月-2月', 'class_year' => '2025',
                'amount' => 1200.00, 'gateway' => 'transfer', 'order_date' => strtotime('2024-12-20'),
                'created' => $now, 'user_id' => $userId, 'order_source' => 'manual',
                'woo_class_name' => '鑽石山游泳初班 (Sat AM)', 'woo_status' => 'completed',
                'last_days' => '', 'avgfee' => '', 'refund_fee' => 0,
                'refund_reason' => '', 'refund_date' => '', 'type' => '', 'woo_order_id' => 0,
            ],
            [
                'class_id' => 3, 'month' => '1月-2月', 'class_year' => '2025',
                'amount' => 980.00, 'gateway' => 'cash', 'order_date' => strtotime('2024-12-22'),
                'created' => $now, 'user_id' => $userId, 'order_source' => 'manual',
                'woo_class_name' => '黃大仙游泳初班 (Sun AM)', 'woo_status' => 'completed',
                'last_days' => '', 'avgfee' => '', 'refund_fee' => 0,
                'refund_reason' => '', 'refund_date' => '', 'type' => '', 'woo_order_id' => 0,
            ],
            [
                'class_id' => 5, 'month' => '5月-6月', 'class_year' => '2025',
                'amount' => 1500.00, 'gateway' => 'transfer', 'order_date' => strtotime('2025-04-28'),
                'created' => $now, 'user_id' => $userId, 'order_source' => 'manual',
                'woo_class_name' => '沙田游泳初班 (Sat PM)', 'woo_status' => 'completed',
                'woo_order_id' => 0,
                'last_days' => '', 'avgfee' => '', 'refund_fee' => 0,
                'refund_reason' => '', 'refund_date' => '', 'type' => '',
            ],
        ];

        DB::table('edu_order')->insert($orders);
        $this->command->info('Inserted ' . count($orders) . ' order records for student_chan (ID=6)');
    }
}
