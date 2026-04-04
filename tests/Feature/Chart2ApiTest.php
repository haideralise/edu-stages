<?php

namespace Tests\Feature;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Chart2ApiTest extends TestCase
{
    use RefreshDatabase;

    private function createStudentWithBmi(): WpUser
    {
        $student = WpUser::create([
            'user_login' => 'chart_student', 'user_pass' => bcrypt('password'),
            'user_email' => 'chart_student@edu.test', 'display_name' => 'Chart Student',
        ]);

        DB::table('usermeta')->insert([
            ['user_id' => $student->ID, 'meta_key' => 'billing_birthdate', 'meta_value' => '2015-06-15'],
            ['user_id' => $student->ID, 'meta_key' => 'billing_gender', 'meta_value' => 'male'],
        ]);

        DB::table('edu_bmi')->insert([
            ['user_id' => $student->ID, 'height' => 110.5, 'weight' => 20.0, 'hc' => 52.0, 'bmi' => 16.38, 'date' => strtotime('2020-06-15')],
            ['user_id' => $student->ID, 'height' => 115.0, 'weight' => 22.0, 'hc' => 53.0, 'bmi' => 16.64, 'date' => strtotime('2021-06-15')],
        ]);

        return $student;
    }

    private function createAdmin(): WpUser
    {
        $admin = WpUser::create([
            'user_login' => 'chart_admin', 'user_pass' => bcrypt('password'),
            'user_email' => 'chart_admin@edu.test', 'display_name' => 'Chart Admin',
        ]);

        DB::table('usermeta')->insert([
            'user_id' => $admin->ID, 'meta_key' => 'wp_3x_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);

        return $admin;
    }

    public function test_student_gets_own_bmi_chart(): void
    {
        $student = $this->createStudentWithBmi();
        $token = $student->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'datasets',
                'labels' => ['x', 'y'],
                'series' => ['student'],
                'meta' => ['gender', 'birthdate'],
            ],
            'meta' => ['timestamp'],
        ]);
    }

    public function test_student_cannot_get_other_student_chart(): void
    {
        $student = $this->createStudentWithBmi();
        $token = $student->createToken('api')->plainTextToken;

        $other = WpUser::create([
            'user_login' => 'other_s', 'user_pass' => bcrypt('password'),
            'user_email' => 'other_s@edu.test', 'display_name' => 'Other',
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart?user_id='.$other->ID);

        $response->assertForbidden();
    }

    public function test_admin_can_get_any_student_chart(): void
    {
        $student = $this->createStudentWithBmi();
        $admin = $this->createAdmin();
        $token = $admin->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart?user_id='.$student->ID);

        $response->assertOk();
        $response->assertJsonPath('data.meta.gender', 'male');
    }

    public function test_coach_gets_403(): void
    {
        $student = $this->createStudentWithBmi();

        $coach = WpUser::create([
            'user_login' => 'chart_coach', 'user_pass' => bcrypt('password'),
            'user_email' => 'chart_coach@edu.test', 'display_name' => 'Chart Coach',
        ]);

        $classId = DB::table('edu_class')->insertGetId([
            'class_name' => 'Chart Class', 'district_id' => 101, 'class_year' => '2025',
        ]);

        DB::table('edu_class_user')->insert([
            'class_id' => $classId, 'month' => '1月-2月',
            'student' => json_encode([(string) $student->ID]),
            'teacher' => json_encode([(string) $coach->ID]),
            'class_year' => '2025', 'sort' => 202501,
        ]);

        $token = $coach->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart');

        $response->assertForbidden();
    }

    public function test_response_matches_scheme_d(): void
    {
        $student = $this->createStudentWithBmi();
        $token = $student->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['datasets', 'labels', 'series', 'meta'],
            'meta' => ['timestamp'],
        ]);
    }

    public function test_response_contains_expected_keys(): void
    {
        $student = $this->createStudentWithBmi();
        $token = $student->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart?type=bmi');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('series', $data);
        $this->assertArrayHasKey('student', $data['series']);
        $this->assertArrayHasKey('p5', $data['series']);
        $this->assertArrayHasKey('p85', $data['series']);
        $this->assertArrayHasKey('p95', $data['series']);
        $this->assertCount(2, $data['datasets']); // 2 BMI records
    }

    public function test_invalid_type_returns_422(): void
    {
        $student = $this->createStudentWithBmi();
        $token = $student->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart?type=invalid');

        $response->assertStatus(422);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/account/growth-chart')->assertUnauthorized();
    }

    public function test_result_type(): void
    {
        $student = $this->createStudentWithBmi();
        $token = $student->createToken('api')->plainTextToken;

        DB::table('edu_result')->insert([
            'class_id' => 1, 'class_month' => '1月-2月', 'exam_id' => 1,
            'user_id' => $student->ID, 'first_name' => 'Chart', 'last_name' => 'Student',
            'exam_type' => 'score', 'exam_name' => 'Freestyle', 'exam_data' => '9',
            'exam_date' => '2025-01-25', 'class_year' => '2025',
            'created' => time(), 'status' => 1,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart?type=result');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['datasets', 'labels', 'series' => ['student']],
            'meta' => ['timestamp'],
        ]);
    }

    public function test_height_type(): void
    {
        $student = $this->createStudentWithBmi();
        $token = $student->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/account/growth-chart?type=height');

        $response->assertOk();
        $response->assertJsonPath('data.labels.y', 'Height (cm)');
    }
}
