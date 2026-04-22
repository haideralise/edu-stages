<?php

namespace Tests\Feature;

use App\Services\StudentOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\EduOrderSeeder;
use Tests\TestCase;

class StudentOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EduOrderSeeder::class);
        $this->service = new StudentOrderService();
    }

    /** Case 1: Direct class match */
    public function test_returns_fee_for_direct_class_match(): void
    {
        $fee = $this->service->getFeeForStudentInClass(101, 10, '5月', 2024);
        $this->assertEquals(800.00, $fee);
    }

    /** Case 2: Transfer — different class_id, same month */
    public function test_returns_fee_for_transfer_student(): void
    {
        // student 102 paid for class 99 (transfer), querying class 10
        $fee = $this->service->getFeeForStudentInClass(102, 10, '5月', 2024);
        $this->assertEquals(750.00, $fee);
    }

    /** Case 3: Refunded order excluded — returns null */
    public function test_excludes_refunded_order(): void
    {
        $fee = $this->service->getFeeForStudentInClass(103, 10, '5月', 2024);
        $this->assertNull($fee);
    }

    /** Case 4: No matching order — returns null */
    public function test_returns_null_when_no_order(): void
    {
        $fee = $this->service->getFeeForStudentInClass(999, 10, '5月', 2024);
        $this->assertNull($fee);
    }

    /** Case 5: Range month match "5月-6月" matches query "5月" */
    public function test_range_month_matches_single_month(): void
    {
        $fee = $this->service->getFeeForStudentInClass(104, 10, '5月', 2024);
        $this->assertEquals(1500.00, $fee);
    }

    /** Case 6: Batch map returns correct keys and amounts */
    public function test_batch_order_map_returns_correct_structure(): void
    {
        $classes = [
            ['class_id' => 10, 'class_month' => '5月', 'class_year' => 2024],
        ];
        $students = [101, 104];

        $map = $this->service->getOrderMapForClasses($classes, $students);

        $this->assertArrayHasKey('101_10', $map);
        $this->assertArrayHasKey('104_10', $map);
        $this->assertEquals(800.00, $map['101_10']['amount']);
        $this->assertEquals(1500.00, $map['104_10']['amount']);
    }
}
