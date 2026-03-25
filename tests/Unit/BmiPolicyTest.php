<?php

namespace Tests\Unit;

use App\Models\EduBmi;
use App\Models\WpUser;
use App\Models\WpUserMeta;
use App\Policies\BmiPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BmiPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BmiPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new BmiPolicy;
    }

    private function createStudent(): WpUser
    {
        return WpUser::create([
            'user_login' => 'student', 'user_pass' => bcrypt('pass'),
            'user_email' => 'student@edu.test', 'display_name' => 'Student',
        ]);
    }

    private function createAdmin(): WpUser
    {
        $user = WpUser::create([
            'user_login' => 'admin', 'user_pass' => bcrypt('pass'),
            'user_email' => 'admin@edu.test', 'display_name' => 'Admin',
        ]);
        WpUserMeta::create([
            'user_id' => $user->ID, 'meta_key' => 'wp_3x_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);
        return $user;
    }

    public function test_student_can_view_own_bmi(): void
    {
        $student = $this->createStudent();
        $bmi = EduBmi::create([
            'user_id' => $student->ID, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertTrue($this->policy->view($student, $bmi));
    }

    public function test_student_cannot_view_other_bmi(): void
    {
        $student = $this->createStudent();
        $bmi = EduBmi::create([
            'user_id' => 99999, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertFalse($this->policy->view($student, $bmi));
    }

    public function test_admin_can_view_any_bmi(): void
    {
        $admin = $this->createAdmin();
        $bmi = EduBmi::create([
            'user_id' => 99999, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertTrue($this->policy->view($admin, $bmi));
    }

    public function test_student_can_update_own_bmi(): void
    {
        $student = $this->createStudent();
        $bmi = EduBmi::create([
            'user_id' => $student->ID, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertTrue($this->policy->update($student, $bmi));
    }

    public function test_student_cannot_update_other_bmi(): void
    {
        $student = $this->createStudent();
        $bmi = EduBmi::create([
            'user_id' => 99999, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertFalse($this->policy->update($student, $bmi));
    }

    public function test_admin_can_delete_any_bmi(): void
    {
        $admin = $this->createAdmin();
        $bmi = EduBmi::create([
            'user_id' => 99999, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertTrue($this->policy->delete($admin, $bmi));
    }

    public function test_student_cannot_delete_other_bmi(): void
    {
        $student = $this->createStudent();
        $bmi = EduBmi::create([
            'user_id' => 99999, 'height' => 140, 'weight' => 35,
            'bmi' => 17.86, 'date' => time(),
        ]);

        $this->assertFalse($this->policy->delete($student, $bmi));
    }
}
