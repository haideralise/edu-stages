<?php

namespace Tests\Feature;

use App\Models\WpUser;
use App\Services\StudentPaymentServiceCommon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentOrderTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(string $login = 'student_a'): WpUser
    {
        return WpUser::create([
            'user_login' => $login,
            'user_pass' => bcrypt('password'),
            'user_email' => "{$login}@edu.test",
            'display_name' => ucfirst($login),
        ]);
    }

    // ── Auth ─────────────────────────────────────────────────────

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/edu/account/myorder')->assertRedirect('/wp-login.php');
    }

    // ── Index ────────────────────────────────────────────────────

    public function test_student_can_view_orders(): void
    {
        $student = $this->createStudent();

        $this->mock(StudentPaymentServiceCommon::class, function ($mock) use ($student) {
            $mock->shouldReceive('getStudentAllPayments')
                ->with($student->ID)
                ->once()
                ->andReturn([
                    'woocommerce' => [
                        100 => [
                            'order_id' => 100,
                            'order_item_name' => 'Swimming - 1月,Mon 10:00',
                            'post_date' => '2026-01-05 10:00:00',
                            'post_status' => 'wc-completed',
                            '_order_total' => '500.00',
                            'class_name' => 'Test Class',
                            'class_id' => 1,
                            'month' => '1月',
                            'class_year' => 2026,
                        ],
                    ],
                    'whatsapp' => [],
                ]);

            $mock->shouldReceive('displayOrders')
                ->once()
                ->andReturn([
                    [
                        'order' => [
                            'order_id' => 100,
                            '_order_total' => '500.00',
                            'post_date' => '2026-01-05 10:00:00',
                        ],
                        'class_name' => 'Test Class',
                        'pa_month' => '1月',
                        'renew_button_text' => '待續費',
                        'renew_current' => 1,
                        'renew_status' => 0,
                        'renew_stop_status' => 0,
                    ],
                ]);

            $mock->shouldReceive('prepareWhatappOrders')
                ->once()
                ->andReturn([]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/myorder');

        $response->assertOk();
        $response->assertSee('Payment History');
        $response->assertSee('Test Class');
        $response->assertSee('500.00');
    }

    public function test_student_sees_own_orders_only(): void
    {
        $student = $this->createStudent();

        $this->mock(StudentPaymentServiceCommon::class, function ($mock) use ($student) {
            $mock->shouldReceive('getStudentAllPayments')
                ->with($student->ID)
                ->once()
                ->andReturn(['woocommerce' => [], 'whatsapp' => []]);

            $mock->shouldReceive('displayOrders')
                ->once()
                ->andReturn([]);

            $mock->shouldReceive('prepareWhatappOrders')
                ->once()
                ->andReturn([]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/myorder');

        $response->assertOk();
        // The service was called with only this student's ID
    }

    public function test_empty_orders_page_renders(): void
    {
        $student = $this->createStudent();

        $this->mock(StudentPaymentServiceCommon::class, function ($mock) {
            $mock->shouldReceive('getStudentAllPayments')
                ->once()
                ->andReturn(['woocommerce' => [], 'whatsapp' => []]);

            $mock->shouldReceive('displayOrders')
                ->once()
                ->andReturn([]);

            $mock->shouldReceive('prepareWhatappOrders')
                ->once()
                ->andReturn([]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/myorder');

        $response->assertOk();
        $response->assertSee('No payment records found');
    }

    public function test_order_source_displayed(): void
    {
        $student = $this->createStudent();

        $this->mock(StudentPaymentServiceCommon::class, function ($mock) {
            $mock->shouldReceive('getStudentAllPayments')
                ->once()
                ->andReturn(['woocommerce' => [], 'whatsapp' => []]);

            $mock->shouldReceive('displayOrders')
                ->once()
                ->andReturn([
                    [
                        'order' => [
                            '_order_total' => '500.00',
                            'post_date' => '2026-01-05',
                        ],
                        'class_name' => 'WooCommerce Class',
                        'pa_month' => '1月',
                        'renew_button_text' => '已續費',
                        'renew_current' => 0,
                    ],
                ]);

            $mock->shouldReceive('prepareWhatappOrders')
                ->once()
                ->andReturn([
                    [
                        'order' => [
                            'order_source' => 'manual',
                            'amount' => '300.00',
                            'order_date' => '1738000000',
                        ],
                        'class_name' => 'WhatsApp Class',
                        'month' => '2月',
                        'amount' => '300.00',
                        'renew_button_text' => '待續費',
                        'renew_current' => 0,
                    ],
                ]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/myorder');

        $response->assertOk();
        $response->assertSee('WooCommerce');
        $response->assertSee('WhatsApp');
    }

    public function test_renewal_status_labels_displayed(): void
    {
        $student = $this->createStudent();

        $this->mock(StudentPaymentServiceCommon::class, function ($mock) {
            $mock->shouldReceive('getStudentAllPayments')
                ->once()
                ->andReturn(['woocommerce' => [], 'whatsapp' => []]);

            $mock->shouldReceive('displayOrders')
                ->once()
                ->andReturn([
                    [
                        'order' => ['_order_total' => '500.00', 'post_date' => '2026-01-05'],
                        'class_name' => 'Renewed Class',
                        'pa_month' => '1月',
                        'renew_button_text' => '已續費',
                        'renew_current' => 0,
                    ],
                    [
                        'order' => ['_order_total' => '500.00', 'post_date' => '2026-02-05'],
                        'class_name' => 'Pending Class',
                        'pa_month' => '2月',
                        'renew_button_text' => '待續費',
                        'renew_current' => 1,
                    ],
                ]);

            $mock->shouldReceive('prepareWhatappOrders')
                ->once()
                ->andReturn([]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/myorder');

        $response->assertOk();
        $response->assertSee('已續費');
        $response->assertSee('待續費');
    }
}
