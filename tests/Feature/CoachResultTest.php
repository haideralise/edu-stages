<?php

namespace Tests\Feature;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoachResultTest extends TestCase
{
    use RefreshDatabase;

    private function createCoachWithStudents(): array
    {
        $pass = bcrypt('password');

        $coach = WpUser::create([
            'user_login' => 'coach_a', 'user_pass' => $pass,
            'user_email' => 'coach_a@edu.test', 'display_name' => 'Coach A',
        ]);

        $studentA = WpUser::create([
            'user_login' => 'student_a', 'user_pass' => $pass,
            'user_email' => 'student_a@edu.test', 'display_name' => 'Student A',
        ]);

        $studentB = WpUser::create([
            'user_login' => 'student_b', 'user_pass' => $pass,
            'user_email' => 'student_b@edu.test', 'display_name' => 'Student B',
        ]);

        // Create class
        $classId = DB::table('edu_class')->insertGetId([
            'class_name' => 'Coach Class', 'district_id' => 101, 'class_year' => '2025',
        ]);

        // Assign coach + student A to class
        DB::table('edu_class_user')->insert([
            'class_id' => $classId,
            'month' => '1月-2月',
            'student' => json_encode([(string) $studentA->ID]),
            'teacher' => json_encode([(string) $coach->ID]),
            'class_year' => '2025',
            'sort' => 202501,
        ]);

        // Result for student A (in coach's class)
        DB::table('edu_result')->insert([
            'class_id' => $classId, 'class_month' => '1月-2月', 'exam_id' => 1,
            'user_id' => $studentA->ID, 'first_name' => 'Student', 'last_name' => 'A',
            'exam_type' => 'score', 'exam_name' => 'Freestyle', 'exam_data' => '9',
            'exam_date' => '2025-01-25', 'class_year' => '2025',
            'created' => time(), 'status' => 1,
        ]);

        // Result for student B (NOT in coach's class)
        DB::table('edu_result')->insert([
            'class_id' => $classId + 999, 'class_month' => '1月-2月', 'exam_id' => 1,
            'user_id' => $studentB->ID, 'first_name' => 'Student', 'last_name' => 'B',
            'exam_type' => 'score', 'exam_name' => 'Backstroke', 'exam_data' => '7',
            'exam_date' => '2025-01-25', 'class_year' => '2025',
            'created' => time(), 'status' => 1,
        ]);

        return [$coach, $studentA, $studentB];
    }

    public function test_coach_sees_own_class_students_only(): void
    {
        [$coach, $studentA, $studentB] = $this->createCoachWithStudents();

        $response = $this->actingAs($coach, 'wp')
            ->get('/edu/coach/results');

        $response->assertOk();
        $response->assertSee('Student A');
        $response->assertDontSee('Student B');
    }

    public function test_coach_sees_student_results(): void
    {
        [$coach] = $this->createCoachWithStudents();

        $response = $this->actingAs($coach, 'wp')
            ->get('/edu/coach/results');

        $response->assertOk();
        $response->assertSee('Freestyle');
        $response->assertSee('9');
    }

    public function test_non_coach_gets_403(): void
    {
        $student = WpUser::create([
            'user_login' => 'just_student', 'user_pass' => bcrypt('password'),
            'user_email' => 'just@edu.test', 'display_name' => 'Just Student',
        ]);

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/coach/results');

        // CoachMiddleware redirects non-coach users to WP login
        $response->assertRedirect('/wp-login.php');
    }

    public function test_requires_auth(): void
    {
        $this->get('/edu/coach/results')->assertRedirect('/wp-login.php');
    }
}
