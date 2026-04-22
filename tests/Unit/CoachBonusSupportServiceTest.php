<?php

namespace Tests\Unit;

use App\Services\CoachBonusSupportService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoachBonusSupportServiceTest extends TestCase
{
    private CoachBonusSupportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CoachBonusSupportService();
    }

    #[Test]
    public function it_returns_the_expected_bonus_rate_for_each_count_band(): void
    {
        $this->assertSame(0.0, $this->service->bonusRateByCount(0));
        $this->assertSame(0.10, $this->service->bonusRateByCount(1));
        $this->assertSame(0.0, $this->service->bonusRateByCount(-3));
        $this->assertSame(0.10, $this->service->bonusRateByCount(1.1));
        $this->assertSame(0.12, $this->service->bonusRateByCount(2));
        $this->assertSame(0.19, $this->service->bonusRateByCount(9));
        $this->assertSame(0.20, $this->service->bonusRateByCount(10));
        $this->assertSame(0.20, $this->service->bonusRateByCount(11));
    }

    #[Test]
    public function it_matches_single_months_and_inclusive_month_ranges(): void
    {
        $this->assertTrue($this->service->isMonthMatchedForClassMonth('5月-6月', '5月-6月'));
        $this->assertTrue($this->service->isMonthMatchedForClassMonth('5月-6月', '5月'));
        $this->assertTrue($this->service->isMonthMatchedForClassMonth('6月', '5月 - 6月'));
        $this->assertFalse($this->service->isMonthMatchedForClassMonth('7月', '5月-6月'));
        $this->assertFalse($this->service->isMonthMatchedForClassMonth('4月', '5月'));
        $this->assertFalse($this->service->isMonthMatchedForClassMonth('', '5月'));
        $this->assertFalse($this->service->isMonthMatchedForClassMonth('5月', ''));
    }

    #[Test]
    public function it_matches_legacy_endpoint_semantics_including_cross_year_ranges(): void
    {
        // Same as DebugClassService: "6月-5月" → endpoints 6月 and 5月; single "5月" hits end.
        $this->assertTrue($this->service->isMonthMatchedForClassMonth('5月', '6月-5月'));

        // Cross-year style range: order month equals first or last segment.
        $this->assertTrue($this->service->isMonthMatchedForClassMonth('12月', '12月-1月'));
        $this->assertTrue($this->service->isMonthMatchedForClassMonth('1月', '12月-1月'));

        // Three calendar months in the string: middle month is not an endpoint → false.
        $this->assertFalse($this->service->isMonthMatchedForClassMonth('6月', '5月-7月'));
    }

    #[Test]
    public function it_returns_the_legacy_refund_filter_sql_with_the_default_alias(): void
    {
        $this->assertSame(
            "((o.refund_fee IS NULL OR o.refund_fee <= 0) AND (o.refund_date IS NULL OR o.refund_date = '' OR o.refund_date = '0'))",
            $this->service->getRefundFilterCondition(),
        );
    }

    #[Test]
    public function it_supports_a_custom_alias_for_later_query_composition(): void
    {
        $this->assertSame(
            "((orders.refund_fee IS NULL OR orders.refund_fee <= 0) AND (orders.refund_date IS NULL OR orders.refund_date = '' OR orders.refund_date = '0'))",
            $this->service->getRefundFilterCondition('orders'),
        );
    }

    #[Test]
    public function it_rejects_an_empty_alias_for_the_refund_filter_condition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias must not be empty.');

        $this->service->getRefundFilterCondition('   ');
    }
}
