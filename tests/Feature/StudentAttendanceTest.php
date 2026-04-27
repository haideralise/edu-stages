<?php

namespace Tests\Feature;

use App\Enums\EduAttendanceStatus;
use App\Models\EduAttendance;
use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Models\WpUser;
use App\Services\AttendanceQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(string $login = 'student_a'): WpUser
    {
        return WpUser::create([
            'user_login' => $login,
            'user_pass' => bcrypt('password'),
            'user_email' => "{$login}@edu.test",
            'display_name' => ucfirst($login),
        ]);
    }

    private function createClassWithEnrollment(int $studentId, array $attrs = []): array
    {
        $classId = DB::table('edu_class')->insertGetId(array_merge([
            'class_name' => 'Test Swim Class',
            'district_id' => 101,
            'class_year' => '2026',
        ], $attrs['class'] ?? []));

        DB::table('edu_class_user')->insert([
            'class_id' => $classId,
            'month' => $attrs['month'] ?? '1月-2月',
            'student' => json_encode([(string) $studentId]),
            'teacher' => json_encode([]),
            'class_year' => $attrs['class_year'] ?? '2026',
            'sort' => 202601,
        ]);

        return ['class_id' => $classId, 'month' => $attrs['month'] ?? '1月-2月'];
    }

    private function createAttendanceRecord(int $userId, int $classId, array $attrs = []): EduAttendance
    {
        $data = array_merge([
            'class_id' => $classId,
            'month' => '1月-2月',
            'user_id' => $userId,
            'date' => '2026-01-15',
            'attendance' => 'present',
            'class_year' => '2026',
        ], $attrs);

        $id = DB::table('edu_attendance')->insertGetId($data);

        return EduAttendance::findOrFail($id);
    }

    // ── Auth ─────────────────────────────────────────────────────

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/edu/account/attend')->assertRedirect('/wp-login.php');
    }

    // ── Index ────────────────────────────────────────────────────

    public function test_student_can_view_attendance(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createClassWithEnrollment($student->ID);

        $this->createAttendanceRecord($student->ID, $enrollment['class_id']);

        // Mock the service to return known data
        $this->mock(AttendanceQueryService::class, function ($mock) use ($student, $enrollment) {
            $mock->shouldReceive('fetchUsersMonthAttendByClassMonths')
                ->once()
                ->andReturn([
                    $student->ID => [
                        $enrollment['class_id'] => [
                            '1月-2月' => [
                                '2026-01-15' => 'present',
                                '2026-01-22' => 'leave',
                            ],
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/attend');

        $response->assertOk();
        $response->assertSee('Attendance Records');
        $response->assertSee('Test Swim Class');
        $response->assertSee('2026-01-15');
    }

    public function test_student_sees_own_attendance_only(): void
    {
        $studentA = $this->createStudent('student_a');
        $studentB = $this->createStudent('student_b');
        $enrollment = $this->createClassWithEnrollment($studentA->ID);

        // Also enroll student B
        DB::table('edu_class_user')->insert([
            'class_id' => $enrollment['class_id'],
            'month' => '3月-4月',
            'student' => json_encode([(string) $studentB->ID]),
            'teacher' => json_encode([]),
            'class_year' => '2026',
            'sort' => 202603,
        ]);

        $this->mock(AttendanceQueryService::class, function ($mock) use ($studentA, $enrollment) {
            $mock->shouldReceive('fetchUsersMonthAttendByClassMonths')
                ->once()
                ->withArgs(function ($userIds, $classMonths) use ($studentA) {
                    return $userIds === [$studentA->ID];
                })
                ->andReturn([
                    $studentA->ID => [
                        $enrollment['class_id'] => [
                            '1月-2月' => ['2026-01-15' => 'present'],
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($studentA, 'wp')
            ->get('/edu/account/attend');

        $response->assertOk();
        $response->assertSee('Present');
    }

    public function test_attendance_displays_correct_status_labels(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createClassWithEnrollment($student->ID);

        $this->mock(AttendanceQueryService::class, function ($mock) use ($student, $enrollment) {
            $mock->shouldReceive('fetchUsersMonthAttendByClassMonths')
                ->once()
                ->andReturn([
                    $student->ID => [
                        $enrollment['class_id'] => [
                            '1月-2月' => [
                                '2026-01-08' => 'present',
                                '2026-01-15' => 'leave',
                                '2026-01-22' => 'cancelled',
                            ],
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/attend');

        $response->assertOk();
        $response->assertSee('Present');
        $response->assertSee('Leave');
        $response->assertSee('Cancelled');
    }

    public function test_empty_attendance_page_renders(): void
    {
        $student = $this->createStudent();

        $this->mock(AttendanceQueryService::class, function ($mock) {
            $mock->shouldReceive('fetchUsersMonthAttendByClassMonths')
                ->once()
                ->andReturn([]);
        });

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/attend');

        $response->assertOk();
        $response->assertSee('No attendance records found');
    }

    // ── Leave Request ────────────────────────────────────────────

    public function test_leave_request_changes_status(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createClassWithEnrollment($student->ID);

        $attendance = $this->createAttendanceRecord($student->ID, $enrollment['class_id'], [
            'date' => now()->addDay()->format('Y-m-d'),
            'attendance' => 'present',
        ]);

        $response = $this->actingAs($student, 'wp')
            ->postJson('/edu/account/attendance/leave', [
                'attendance_id' => $attendance->id,
                'status' => 'leave',
            ]);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $attendance->id,
                'status' => 'leave',
            ],
            'message' => 'success',
        ]);

        $this->assertDatabaseHas('edu_attendance', [
            'id' => $attendance->id,
            'attendance' => 'leave',
        ]);
    }

    public function test_leave_request_own_record_only(): void
    {
        $studentA = $this->createStudent('student_a');
        $studentB = $this->createStudent('student_b');
        $enrollment = $this->createClassWithEnrollment($studentB->ID);

        $attendance = $this->createAttendanceRecord($studentB->ID, $enrollment['class_id'], [
            'date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($studentA, 'wp')
            ->postJson('/edu/account/attendance/leave', [
                'attendance_id' => $attendance->id,
                'status' => 'leave',
            ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthorized',
            'code' => 'UNAUTHORIZED',
        ]);
    }

    public function test_leave_request_future_only(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createClassWithEnrollment($student->ID);

        $attendance = $this->createAttendanceRecord($student->ID, $enrollment['class_id'], [
            'date' => now()->subDay()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($student, 'wp')
            ->postJson('/edu/account/attendance/leave', [
                'attendance_id' => $attendance->id,
                'status' => 'leave',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.date.0', 'Cannot modify past attendance records');
    }

    public function test_leave_request_validation(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->postJson('/edu/account/attendance/leave', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['attendance_id', 'status']]);
    }
}
