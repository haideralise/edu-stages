<?php

namespace Tests\Feature;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoachHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function createCoachWithHistory(): array
    {
        $pass = bcrypt('password');

        $coach = WpUser::create([
            'user_login' => 'coach_h', 'user_pass' => $pass,
            'user_email' => 'coach_h@edu.test', 'display_name' => 'Coach H',
        ]);

        $studentA = WpUser::create([
            'user_login' => 'student_ha', 'user_pass' => $pass,
            'user_email' => 'student_ha@edu.test', 'display_name' => 'Student HA',
        ]);

        $studentB = WpUser::create([
            'user_login' => 'student_hb', 'user_pass' => $pass,
            'user_email' => 'student_hb@edu.test', 'display_name' => 'Student HB',
        ]);

        $classId = DB::table('edu_class')->insertGetId([
            'class_name' => 'History Class', 'district_id' => 101, 'class_year' => '2025',
        ]);

        DB::table('edu_class_user')->insert([
            'class_id'   => $classId,
            'month'      => '1月-2月',
            'student'    => json_encode([(string) $studentA->ID]),
            'teacher'    => json_encode([(string) $coach->ID]),
            'class_year' => '2025',
            'sort'       => 202501,
        ]);

        // Result for student A (in coach's class)
        DB::table('edu_result')->insert([
            'class_id' => $classId, 'class_month' => '1月-2月', 'exam_id' => 1,
            'user_id' => $studentA->ID, 'first_name' => 'Student', 'last_name' => 'HA',
            'exam_type' => 'score', 'exam_name' => 'Freestyle', 'exam_data' => '9',
            'exam_date' => '2025-01-25', 'class_year' => '2025',
            'created' => time(), 'status' => 1,
        ]);

        // Result for student B (NOT in coach's class)
        DB::table('edu_result')->insert([
            'class_id' => $classId + 999, 'class_month' => '3月-4月', 'exam_id' => 2,
            'user_id' => $studentB->ID, 'first_name' => 'Student', 'last_name' => 'HB',
            'exam_type' => 'score', 'exam_name' => 'Backstroke', 'exam_data' => '7',
            'exam_date' => '2025-03-15', 'class_year' => '2025',
            'created' => time(), 'status' => 1,
        ]);

        return [$coach, $studentA, $studentB];
    }

    public function test_coach_sees_own_class_students_history(): void
    {
        [$coach, $studentA, $studentB] = $this->createCoachWithHistory();

        $response = $this->actingAs($coach, 'web')
            ->get('/edu/result/history');

        $response->assertOk();
        $response->assertSee('Student HA');
        $response->assertSee('Freestyle');
        $response->assertDontSee('Student HB');
        $response->assertDontSee('Backstroke');
    }

    public function test_coach_cannot_see_other_coach_students(): void
    {
        [$coach, $studentA, $studentB] = $this->createCoachWithHistory();

        $otherCoach = WpUser::create([
            'user_login' => 'coach_other', 'user_pass' => bcrypt('password'),
            'user_email' => 'coach_other@edu.test', 'display_name' => 'Other Coach',
        ]);

        // Make other coach a teacher of a different class
        $otherClassId = DB::table('edu_class')->insertGetId([
            'class_name' => 'Other Class', 'district_id' => 102, 'class_year' => '2025',
        ]);

        DB::table('edu_class_user')->insert([
            'class_id'   => $otherClassId,
            'month'      => '1月-2月',
            'student'    => json_encode([]),
            'teacher'    => json_encode([(string) $otherCoach->ID]),
            'class_year' => '2025',
            'sort'       => 202501,
        ]);

        $response = $this->actingAs($otherCoach, 'web')
            ->get('/edu/result/history');

        $response->assertOk();
        $response->assertDontSee('Student HA');
        $response->assertDontSee('Freestyle');
    }

    public function test_student_gets_403(): void
    {
        $student = WpUser::create([
            'user_login' => 'plain_student', 'user_pass' => bcrypt('password'),
            'user_email' => 'plain@edu.test', 'display_name' => 'Plain Student',
        ]);

        $response = $this->actingAs($student, 'web')
            ->get('/edu/result/history');

        $response->assertForbidden();
    }

    public function test_admin_sees_all(): void
    {
        [$coach, $studentA, $studentB] = $this->createCoachWithHistory();

        $admin = WpUser::create([
            'user_login' => 'admin_h', 'user_pass' => bcrypt('password'),
            'user_email' => 'admin_h@edu.test', 'display_name' => 'Admin H',
        ]);

        DB::table('usermeta')->insert([
            'user_id' => $admin->ID, 'meta_key' => 'wp_3x_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);

        $response = $this->actingAs($admin, 'web')
            ->get('/edu/result/history');

        $response->assertOk();
        $response->assertSee('Student HA');
        $response->assertSee('Student HB');
    }

    public function test_requires_auth(): void
    {
        $this->get('/edu/result/history')->assertRedirect('/login');
    }

    public function test_filter_by_class_year(): void
    {
        [$coach, $studentA] = $this->createCoachWithHistory();

        // Add a result with different year
        $classId2 = DB::table('edu_class')->insertGetId([
            'class_name' => 'Class 2024', 'district_id' => 101, 'class_year' => '2024',
        ]);

        DB::table('edu_class_user')->insert([
            'class_id'   => $classId2,
            'month'      => '9月-10月',
            'student'    => json_encode([(string) $studentA->ID]),
            'teacher'    => json_encode([(string) $coach->ID]),
            'class_year' => '2024',
            'sort'       => 202409,
        ]);

        DB::table('edu_result')->insert([
            'class_id' => $classId2, 'class_month' => '9月-10月', 'exam_id' => 3,
            'user_id' => $studentA->ID, 'first_name' => 'Student', 'last_name' => 'HA',
            'exam_type' => 'score', 'exam_name' => 'Butterfly', 'exam_data' => '8',
            'exam_date' => '2024-09-20', 'class_year' => '2024',
            'created' => time(), 'status' => 1,
        ]);

        // Filter for 2025 only
        $response = $this->actingAs($coach, 'web')
            ->get('/edu/result/history?class_year=2025');

        $response->assertOk();
        $response->assertSee('Freestyle');
        $response->assertDontSee('Butterfly');

        // Filter for 2024 only
        $response = $this->actingAs($coach, 'web')
            ->get('/edu/result/history?class_year=2024');

        $response->assertOk();
        $response->assertSee('Butterfly');
        $response->assertDontSee('Freestyle');
    }
}
