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
        $thresholds = BmiForAge::getThresholds(180, 'male');
        $this->assertNotNull($thresholds);
        $this->assertArrayHasKey('p5', $thresholds);
        $this->assertArrayHasKey('p85', $thresholds);
        $this->assertArrayHasKey('p95', $thresholds);
        $this->assertEquals(16.7, $thresholds['p5']);
    }

    public function test_get_thresholds_valid_female(): void
    {
        $thresholds = BmiForAge::getThresholds(144, 'female');
        $this->assertNotNull($thresholds);
        $this->assertEquals(15.0, $thresholds['p5']);
    }

    public function test_get_thresholds_under_24_months(): void
    {
        $this->assertNull(BmiForAge::getThresholds(12, 'male'));
    }

    public function test_get_thresholds_over_240_months(): void
    {
        $this->assertNull(BmiForAge::getThresholds(252, 'male'));
    }

    public function test_get_thresholds_invalid_gender(): void
    {
        $this->assertNull(BmiForAge::getThresholds(120, 'unknown'));
    }

    public function test_get_thresholds_rounds_to_nearest_6_months(): void
    {
        // 182 months should round to 180
        $t182 = BmiForAge::getThresholds(182, 'male');
        $t180 = BmiForAge::getThresholds(180, 'male');
        $this->assertEquals($t180, $t182);

        // 183 months should round to 186
        $t183 = BmiForAge::getThresholds(183, 'male');
        $t186 = BmiForAge::getThresholds(186, 'male');
        $this->assertEquals($t186, $t183);
    }

    public function test_get_thresholds_boundary_24_months(): void
    {
        $this->assertNotNull(BmiForAge::getThresholds(24, 'male'));
    }

    public function test_get_thresholds_boundary_240_months(): void
    {
        $this->assertNotNull(BmiForAge::getThresholds(240, 'female'));
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

    public function test_categorize_adult_fallback_over_20(): void
    {
        // Person over 20 years old → outside CDC range → adult thresholds
        $recordDate = strtotime('2035-01-01');
        $this->assertEquals('normal', BmiForAge::categorize(22.0, '2010-01-01', 'male', $recordDate));
    }

    public function test_categorize_child_underweight(): void
    {
        // 14yo boy: p5 ≈ 15.1 at 168 months. BMI 14.5 < p5 → underweight
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('underweight', BmiForAge::categorize(14.5, '2011-01-15', 'male', $recordDate));
    }

    public function test_categorize_child_normal(): void
    {
        // 14yo boy: p5 ≈ 15.1, p85 ≈ 21.4 at ~168mo. BMI 17.8 → normal
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('normal', BmiForAge::categorize(17.8, '2011-01-15', 'male', $recordDate));
    }

    public function test_categorize_child_overweight(): void
    {
        // 14yo boy at 168mo: p85=23.1, p95=25.9. BMI 24.0 → overweight
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('overweight', BmiForAge::categorize(24.0, '2011-01-15', 'male', $recordDate));
    }

    public function test_categorize_child_obese(): void
    {
        // 14yo boy at 168mo: p95=25.9. BMI 26.5 → obese
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('obese', BmiForAge::categorize(26.5, '2011-01-15', 'male', $recordDate));
    }

    public function test_categorize_female_child(): void
    {
        // 13yo girl: ~156 months. p5=15.5, p85=22.9, p95=25.4. BMI 20.0 → normal
        $recordDate = strtotime('2025-01-15');
        $this->assertEquals('normal', BmiForAge::categorize(20.0, '2012-01-15', 'female', $recordDate));
    }
}
