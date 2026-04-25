<?php

namespace Tests\Feature;

use App\Models\EduAttendance;
use App\Models\EduClassUser;
use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_gets_own_attendance(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        // Seed an attendance record for this student
        $classUser = DB::table('edu_class_user')
            ->whereRaw("student LIKE ?", ['%"' . $student->ID . '"%'])
            ->first();

        if ($classUser) {
            DB::table('edu_attendance')->insert([
                'class_id' => $classUser->class_id,
                'month' => $classUser->month,
                'class_year' => $classUser->class_year,
                'user_id' => $student->ID,
                'date' => '2025-01-11',
                'attendance' => 'leave',
            ]);
        }

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['timestamp'],
            ]);
    }

    public function test_student_cannot_access_other_student(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();
        $other = WpUser::where('user_login', 'student_li')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/attendance?user_id=' . $other->ID);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_admin_gets_attendance(): void
    {
        $admin = WpUser::where('user_login', 'admin')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/account/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['timestamp'],
            ]);
    }

    public function test_admin_filters_by_user_id(): void
    {
        $admin = WpUser::where('user_login', 'admin')->first();
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/account/attendance?user_id=' . $student->ID);

        $response->assertOk();
    }

    public function test_guest_gets_401(): void
    {
        $response = $this->getJson('/api/account/attendance');

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthorized',
                'code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_response_matches_scheme_d(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['timestamp'],
            ]);
    }

    public function test_attendance_data_has_correct_structure(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        // Seed attendance data
        $classUser = DB::table('edu_class_user')
            ->whereRaw("student LIKE ?", ['%"' . $student->ID . '"%'])
            ->first();

        if ($classUser) {
            DB::table('edu_attendance')->insert([
                'class_id' => $classUser->class_id,
                'month' => $classUser->month,
                'class_year' => $classUser->class_year,
                'user_id' => $student->ID,
                'date' => '2025-01-11',
                'attendance' => 'present',
            ]);
        }

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/attendance');

        $response->assertOk();

        $data = $response->json('data');
        if (count($data) > 0) {
            $first = $data[0];
            $this->assertArrayHasKey('class_id', $first);
            $this->assertArrayHasKey('class_name', $first);
            $this->assertArrayHasKey('month', $first);
            $this->assertArrayHasKey('dates', $first);
        }
    }
}
