<?php

namespace Tests\Unit;

use App\Models\EduBmi;
use App\Models\WpUser;
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
}
