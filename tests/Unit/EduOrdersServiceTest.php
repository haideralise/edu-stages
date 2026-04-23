<?php

namespace Tests\Unit;

use App\Models\EduClass;
use App\Models\EduOrder;
use App\Services\EduOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EduOrdersServiceTest extends TestCase
{
    use RefreshDatabase;

    private EduOrderService $service;

    private array $validData = [
        'class_id'   => 1,
        'month'      => '3月-4月',
        'amount'     => 1200.00,
        'order_date' => '2026-03-01',
        'class_year' => '2026',
        'gateway'    => '轉數快',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EduOrderService();

        EduClass::create([
            'class_id'    => 1,
            'class_name'  => '測試游泳班',
            'district_id' => 1,
            'class_year'  => '2026',
            'lv3'         => '鑽石山',
        ]);
    }

    #[Test]
    public function it_inserts_a_new_order_with_correct_fields(): void
    {
        $studentId = 42;

        $order = $this->service->addOrUpdateOrder(null, $studentId, $this->validData);

        $this->assertInstanceOf(EduOrder::class, $order);

        $this->assertDatabaseHas('edu_order', [
            'id'         => $order->id,
            'user_id'    => $studentId,
            'class_id'   => 1,
            'month'      => '3月-4月',
            'class_year' => '2026',
            'gateway'    => '轉數快',
            'order_source' => 'manual',
        ]);
    }

    #[Test]
    public function it_converts_order_date_to_unix_timestamp_on_insert(): void
    {
        $order = $this->service->addOrUpdateOrder(null, 42, $this->validData);

        $record = EduOrder::find($order->id);

        $this->assertEquals(
            strtotime('2026-03-01'),
            $record->order_date
        );
    }

    #[Test]
    public function it_sets_order_source_to_manual_on_insert(): void
    {
        $order = $this->service->addOrUpdateOrder(null, 42, $this->validData);

        $this->assertEquals('manual', EduOrder::find($order->id)->order_source);
    }

    #[Test]
    public function it_sets_amount_correctly_on_insert(): void
    {
        $order = $this->service->addOrUpdateOrder(null, 42, $this->validData);

        $this->assertEquals(1200.00, EduOrder::find($order->id)->amount);
    }

    #[Test]
    public function it_updates_an_existing_order(): void
    {
        $order = $this->service->addOrUpdateOrder(null, 42, $this->validData);

        $updatedData = array_merge($this->validData, [
            'amount'  => 1500.00,
            'gateway' => 'PayMe',
        ]);

        $this->service->addOrUpdateOrder($order->id, 42, $updatedData);

        $this->assertDatabaseHas('edu_order', [
            'id'      => $order->id,
            'amount'  => 1500.00,
            'gateway' => 'PayMe',
        ]);
    }

    #[Test]
    public function it_keeps_order_source_manual_on_update(): void
    {
        $order = $this->service->addOrUpdateOrder(null, 42, $this->validData);

        $this->service->addOrUpdateOrder($order->id, 42, array_merge($this->validData, [
            'amount' => 999.00,
        ]));

        $this->assertEquals('manual', EduOrder::find($order->id)->order_source);
    }

    #[Test]
    public function it_returns_correct_order_by_id(): void
    {
        $order = $this->service->addOrUpdateOrder(null, 42, $this->validData);

        $result = $this->service->getOrderById($order->id);

        $this->assertNotNull($result);
        $this->assertEquals($order->id, $result['id']);
        $this->assertEquals('3月-4月', $result['month']);
        $this->assertEquals('2026', $result['class_year']);
        $this->assertEquals(1200.00, $result['amount']);
    }

    #[Test]
    public function it_returns_null_for_nonexistent_order_id(): void
    {
        $result = $this->service->getOrderById(99999);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_array_not_model_instance(): void
    {
        $order  = $this->service->addOrUpdateOrder(null, 42, $this->validData);
        $result = $this->service->getOrderById($order->id);

        $this->assertIsArray($result);
    }
}