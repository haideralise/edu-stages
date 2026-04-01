<?php

namespace Tests\Unit;

use App\Support\BmiForAge;
use Tests\TestCase;

class BmiForAgeTest extends TestCase
{
    // ── calculateAgeMonths ────────────────────────────────────────

    public function test_calculate_age_months_basic(): void
    {
        // 2010-01-15 to 2025-01-15 → 180 months (15 years)
        $months = BmiForAge::calculateAgeMonths('2010-01-15', strtotime('2025-01-15'));
        $this->assertEquals(180, $months);
    }

    public function test_calculate_age_months_mid_month(): void
    {
        // 2010-05-12 to 2025-01-15 → 14 years 8 months = 176
        $months = BmiForAge::calculateAgeMonths('2010-05-12', strtotime('2025-01-15'));
        $this->assertEquals(176, $months);
    }

    public function test_calculate_age_months_same_date(): void
    {
        $months = BmiForAge::calculateAgeMonths('2025-01-15', strtotime('2025-01-15'));
        $this->assertEquals(0, $months);
    }

    public function test_calculate_age_months_future_birthdate(): void
    {
        $months = BmiForAge::calculateAgeMonths('2026-01-01', strtotime('2025-01-15'));
        $this->assertEquals(0, $months);
    }

    // ── getThresholds ─────────────────────────────────────────────

    public function test_get_thresholds_valid_male(): void
    {
        // HK-2020: male 183 months → p5=15.4
        $thresholds = BmiForAge::getThresholds(183, 'male');
        $this->assertNotNull($thresholds);
        $this->assertArrayHasKey('p5', $thresholds);
        $this->assertArrayHasKey('p85', $thresholds);
        $this->assertArrayHasKey('p95', $thresholds);
        $this->assertEquals(15.4, $thresholds['p5']);
    }

    public function test_get_thresholds_valid_female(): void
    {
        // HK-2020: female 146 months → p5=14.3
        $thresholds = BmiForAge::getThresholds(146, 'female');
        $this->assertNotNull($thresholds);
        $this->assertEquals(14.3, $thresholds['p5']);
    }

    public function test_get_thresholds_under_24_months(): void
    {
        $this->assertNull(BmiForAge::getThresholds(12, 'male'));
    }

    public function test_get_thresholds_over_219_months(): void
    {
        $this->assertNull(BmiForAge::getThresholds(220, 'male'));
    }

    public function test_get_thresholds_invalid_gender(): void
    {
        $this->assertNull(BmiForAge::getThresholds(120, 'unknown'));
    }

    public function test_get_thresholds_finds_nearest_key(): void
    {
        // 63 months is between 61 and 67; nearest is 61
        $t63 = BmiForAge::getThresholds(63, 'male');
        $t61 = BmiForAge::getThresholds(61, 'male');
        $this->assertEquals($t61, $t63);

        // 65 months is between 61 and 67; nearest is 67
        $t65 = BmiForAge::getThresholds(65, 'male');
        $t67 = BmiForAge::getThresholds(67, 'male');
        $this->assertEquals($t67, $t65);
    }

    public function test_get_thresholds_exact_key(): void
    {
        // Exact monthly key should return directly
        $thresholds = BmiForAge::getThresholds(48, 'male');
        $this->assertNotNull($thresholds);
        $this->assertEquals(13.7, $thresholds['p5']);
    }

    public function test_get_thresholds_boundary_24_months(): void
    {
        $this->assertNotNull(BmiForAge::getThresholds(24, 'male'));
    }

    public function test_get_thresholds_boundary_219_months(): void
    {
        $this->assertNotNull(BmiForAge::getThresholds(219, 'female'));
    }

    // ── categorize ────────────────────────────────────────────────

    public function test_categorize_adult_fallback_no_birthdate(): void
    {
        $this->assertEquals('underweight', BmiForAge::categorize(16.0, null, 'male', time()));
    }

    public function test_categorize_adult_fallback_no_gender(): void
    {
        $this->assertEquals('normal', BmiForAge::categorize(22.0, '2010-01-01', null, time()));
    }

    public function test_categorize_adult_fallback_over_18(): void
    {
        // Person over 18.25 years old → outside HK-2020 range → adult thresholds
        $recordDate = strtotime('2035-01-01');
        $this->assertEquals('normal', BmiForAge::categorize(22.0, '2010-01-01', 'male', $recordDate));
    }

    public function test_categorize_child_underweight(): void
    {
        // 10yo boy (born 2015-01-15, record 2025-01-15 → 120 months)
        // Nearest key ~122: p5=13.6. BMI 13.0 < 13.6 → underweight
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('underweight', BmiForAge::categorize(13.0, '2015-01-15', 'male', $recordDate));
    }

    public function test_categorize_child_normal(): void
    {
        // 10yo boy at ~122 months: p5=13.6, p85=19.2. BMI 16.0 → normal
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('normal', BmiForAge::categorize(16.0, '2015-01-15', 'male', $recordDate));
    }

    public function test_categorize_child_overweight(): void
    {
        // 10yo boy at ~122 months: p85=19.2, p95=22.0. BMI 20.0 → overweight
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('overweight', BmiForAge::categorize(20.0, '2015-01-15', 'male', $recordDate));
    }

    public function test_categorize_child_obese(): void
    {
        // 10yo boy at ~122 months: p95=22.0. BMI 23.0 → obese
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('obese', BmiForAge::categorize(23.0, '2015-01-15', 'male', $recordDate));
    }

    public function test_categorize_female_child(): void
    {
        // 10yo girl (born 2015-01-15, record 2025-01-15 → 120 months)
        // Nearest key ~122: p5=13.4, p85=18.7. BMI 16.0 → normal
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('normal', BmiForAge::categorize(16.0, '2015-01-15', 'female', $recordDate));
    }
}
