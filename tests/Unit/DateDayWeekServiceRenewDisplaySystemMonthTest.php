<?php

namespace Tests\Unit;

use App\Services\DateDayWeekService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verification: renew “system month” boundary (same rule as displayOrders / prepareWhatappOrders).
 *
 * Days 1–15 → previous calendar month; day 16+ → current month.
 */
class DateDayWeekServiceRenewDisplaySystemMonthTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('renewDisplaySystemYearMonthProvider')]
    public function test_renew_display_system_year_month_matches_day_boundary(
        string $frozenUtc,
        int $expectYear,
        int $expectMonth
    ): void {
        Carbon::setTestNow(Carbon::parse($frozenUtc, 'UTC'));

        $svc = new DateDayWeekService;
        $r = $svc->getRenewDisplaySystemYearMonth();

        $this->assertSame($expectYear, $r['year'], 'year for '.$frozenUtc);
        $this->assertSame($expectMonth, $r['month'], 'month for '.$frozenUtc);
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: int}>
     */
    public static function renewDisplaySystemYearMonthProvider(): iterable
    {
        yield 'day_14_previous_month' => ['2025-03-14 12:00:00', 2025, 2];

        yield 'day_15_previous_month' => ['2025-03-15 23:59:59', 2025, 2];

        yield 'day_16_current_month' => ['2025-03-16 00:00:00', 2025, 3];

        yield 'jan_15_rolls_to_december_prior_year' => ['2025-01-15 08:00:00', 2024, 12];

        yield 'jan_16_stays_january' => ['2025-01-16 08:00:00', 2025, 1];
    }
}
