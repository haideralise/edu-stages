<?php

namespace Tests\Unit;

use App\Models\EduBmi;
use App\Models\WpUser;
use App\Models\WpUserMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EduBmiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_bmi(): void
    {
        // 70kg, 170cm → 70 / (1.7^2) = 24.22
        $bmi = EduBmi::calculateBmi(170, 70);
        $this->assertEquals(24.22, $bmi);
    }

    public function test_calculate_bmi_zero_height(): void
    {
        $bmi = EduBmi::calculateBmi(0, 70);
        $this->assertEquals(0, $bmi);
    }

    public function test_calculate_bmi_precision(): void
    {
        // 35kg, 140cm → 35 / (1.4^2) = 17.86
        $bmi = EduBmi::calculateBmi(140, 35);
        $this->assertEquals(17.86, $bmi);
    }

    public function test_scope_for_user(): void
    {
        $user1 = WpUser::create([
            'user_login' => 'user1', 'user_pass' => bcrypt('pass'),
            'user_email' => 'u1@test.com', 'display_name' => 'U1',
        ]);
        $user2 = WpUser::create([
            'user_login' => 'user2', 'user_pass' => bcrypt('pass'),
            'user_email' => 'u2@test.com', 'display_name' => 'U2',
        ]);

        EduBmi::create(['user_id' => $user1->ID, 'height' => 140, 'weight' => 35, 'bmi' => 17.86, 'date' => time()]);
        EduBmi::create(['user_id' => $user2->ID, 'height' => 150, 'weight' => 45, 'bmi' => 20.0, 'date' => time()]);

        $results = EduBmi::forUser($user1->ID)->get();
        $this->assertCount(1, $results);
        $this->assertEquals($user1->ID, $results->first()->user_id);
    }

    public function test_category_underweight(): void
    {
        $bmi = new EduBmi(['bmi' => 16.0]);
        $this->assertEquals('underweight', $bmi->category);
    }

    public function test_category_normal(): void
    {
        $bmi = new EduBmi(['bmi' => 22.0]);
        $this->assertEquals('normal', $bmi->category);
    }

    public function test_category_overweight(): void
    {
        $bmi = new EduBmi(['bmi' => 27.0]);
        $this->assertEquals('overweight', $bmi->category);
    }

    public function test_category_obese(): void
    {
        $bmi = new EduBmi(['bmi' => 35.0]);
        $this->assertEquals('obese', $bmi->category);
    }

    public function test_user_relationship(): void
    {
        $user = WpUser::create([
            'user_login' => 'user1', 'user_pass' => bcrypt('pass'),
            'user_email' => 'u1@test.com', 'display_name' => 'User 1',
        ]);

        $bmi = EduBmi::create([
            'user_id' => $user->ID, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertEquals('User 1', $bmi->user->display_name);
    }

    // ── Age-aware category integration tests ──────────────────────

    public function test_category_age_aware_with_user_meta(): void
    {
        $user = WpUser::create([
            'user_login' => 'child1', 'user_pass' => bcrypt('pass'),
            'user_email' => 'child1@test.com', 'display_name' => 'Child 1',
        ]);
        WpUserMeta::create(['user_id' => $user->ID, 'meta_key' => 'billing_birthdate', 'meta_value' => '2011-01-15']);
        WpUserMeta::create(['user_id' => $user->ID, 'meta_key' => 'billing_gender', 'meta_value' => 'male']);

        // BMI 17.8 for a ~14yo boy (168mo): p5=16.2, p85=23.1 → normal
        $bmi = EduBmi::create([
            'user_id' => $user->ID, 'height' => 140, 'weight' => 35,
            'bmi' => 17.8, 'date' => strtotime('2025-01-15'),
        ]);

        // Load with user.meta to trigger age-aware path
        $bmi = EduBmi::with('user.meta')->find($bmi->id);

        $this->assertEquals('normal', $bmi->category);
    }

    public function test_category_fallback_when_no_birthdate(): void
    {
        $user = WpUser::create([
            'user_login' => 'nobday', 'user_pass' => bcrypt('pass'),
            'user_email' => 'nobday@test.com', 'display_name' => 'No Bday',
        ]);

        // BMI 17.8 with no birthdate meta → adult WHO: 17.8 < 18.5 → underweight
        $bmi = EduBmi::create([
            'user_id' => $user->ID, 'height' => 140, 'weight' => 35,
            'bmi' => 17.8, 'date' => strtotime('2025-01-15'),
        ]);

        $bmi = EduBmi::with('user.meta')->find($bmi->id);

        $this->assertEquals('underweight', $bmi->category);
    }

    public function test_same_bmi_different_category_bare_vs_child(): void
    {
        // Bare model (no user) → adult WHO: 17.8 < 18.5 → underweight
        $bare = new EduBmi(['bmi' => 17.8, 'date' => strtotime('2025-01-15')]);
        $this->assertEquals('underweight', $bare->category);

        // With child user → age-aware: 17.8 is normal for a 14yo boy
        $user = WpUser::create([
            'user_login' => 'kid', 'user_pass' => bcrypt('pass'),
            'user_email' => 'kid@test.com', 'display_name' => 'Kid',
        ]);
        WpUserMeta::create(['user_id' => $user->ID, 'meta_key' => 'billing_birthdate', 'meta_value' => '2011-01-15']);
        WpUserMeta::create(['user_id' => $user->ID, 'meta_key' => 'billing_gender', 'meta_value' => 'male']);

        $bmi = EduBmi::create([
            'user_id' => $user->ID, 'height' => 140, 'weight' => 35,
            'bmi' => 17.8, 'date' => strtotime('2025-01-15'),
        ]);
        $bmi = EduBmi::with('user.meta')->find($bmi->id);

        $this->assertEquals('normal', $bmi->category);
    }
}
