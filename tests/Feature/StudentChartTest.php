<?php

namespace Tests\Feature;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_student_can_view_chart_page(): void
    {
        $student = WpUser::create([
            'user_login' => 'chart_s', 'user_pass' => bcrypt('password'),
            'user_email' => 'chart_s@edu.test', 'display_name' => 'Chart Student',
        ]);

        $response = $this->actingAs($student, 'web')
            ->get('/edu/account/chart2');

        $response->assertOk();
        $response->assertSee('Growth Chart');
        $response->assertSee('chart2-container');
    }

    public function test_coach_gets_403(): void
    {
        $coach = WpUser::create([
            'user_login' => 'chart_c', 'user_pass' => bcrypt('password'),
            'user_email' => 'chart_c@edu.test', 'display_name' => 'Chart Coach',
        ]);

        $classId = DB::table('edu_class')->insertGetId([
            'class_name' => 'CC', 'district_id' => 101, 'class_year' => '2025',
        ]);

        DB::table('edu_class_user')->insert([
            'class_id' => $classId, 'month' => '1月-2月',
            'student' => json_encode([]), 'teacher' => json_encode([(string) $coach->ID]),
            'class_year' => '2025', 'sort' => 202501,
        ]);

        $response = $this->actingAs($coach, 'web')
            ->get('/edu/account/chart2');

        $response->assertForbidden();
    }

    public function test_guest_redirects_to_login(): void
    {
        $this->get('/edu/account/chart2')->assertRedirect('/login');
    }

    public function test_admin_can_view_chart_page(): void
    {
        $admin = WpUser::create([
            'user_login' => 'chart_admin', 'user_pass' => bcrypt('password'),
            'user_email' => 'chart_admin@edu.test', 'display_name' => 'Chart Admin',
        ]);

        DB::table('usermeta')->insert([
            'user_id' => $admin->ID, 'meta_key' => 'wp_3x_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);

        $response = $this->actingAs($admin, 'web')
            ->get('/edu/account/chart2');

        $response->assertOk();
        $response->assertSee('Select Student');
    }
}
