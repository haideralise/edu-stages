<?php

namespace Tests\Feature;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentResultTest extends TestCase
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

    private function seedLevelsAndResults(int $userId): void
    {
        $courseId = DB::table('edu_level')->insertGetId(['pid' => 0, 'name' => 'Swimming']);
        $levelId = DB::table('edu_level')->insertGetId(['pid' => $courseId, 'name' => 'Beginner']);
        $itemId = DB::table('edu_level')->insertGetId([
            'pid' => $levelId, 'name' => 'Freestyle 25m', 'data' => json_encode(['max_score' => 10]),
        ]);

        $classId = DB::table('edu_class')->insertGetId([
            'class_name' => 'Test Class', 'district_id' => 101, 'class_year' => '2025',
        ]);

        DB::table('edu_result')->insert([
            'class_id' => $classId,
            'class_month' => '1月-2月',
            'exam_id' => $itemId,
            'user_id' => $userId,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'exam_type' => 'score',
            'exam_name' => 'Freestyle 25m',
            'exam_data' => '8',
            'exam_date' => '2025-01-25',
            'class_year' => '2025',
            'created' => time(),
            'status' => 1,
        ]);
    }

    public function test_student_sees_own_results(): void
    {
        $student = $this->createStudent();
        $this->seedLevelsAndResults($student->ID);

        $response = $this->actingAs($student, 'web')
            ->get('/edu/account/test-result');

        $response->assertOk();
        $response->assertSee('Freestyle 25m');
        $response->assertSee('8');
    }

    public function test_student_does_not_see_other_results(): void
    {
        $student = $this->createStudent();
        $other = $this->createStudent('student_b');
        $this->seedLevelsAndResults($other->ID);

        $response = $this->actingAs($student, 'web')
            ->get('/edu/account/test-result');

        $response->assertOk();
        $response->assertDontSee('>8<'); // other student's score not in table cell
    }

    public function test_level_tree_grouping(): void
    {
        $student = $this->createStudent();
        $this->seedLevelsAndResults($student->ID);

        $response = $this->actingAs($student, 'web')
            ->get('/edu/account/test-result');

        $response->assertOk();
        $response->assertSee('Swimming');
        $response->assertSee('Beginner');
        $response->assertSee('Freestyle 25m');
    }

    public function test_requires_auth(): void
    {
        $this->get('/edu/account/test-result')->assertRedirect('/login');
    }

    public function test_coach_cannot_access_student_results(): void
    {
        $coach = WpUser::create([
            'user_login' => 'coach_test',
            'user_pass' => bcrypt('password'),
            'user_email' => 'coach@edu.test',
            'display_name' => 'Coach Test',
        ]);

        $classId = DB::table('edu_class')->insertGetId([
            'class_name' => 'Test Class', 'district_id' => 101, 'class_year' => '2025',
        ]);

        DB::table('edu_class_user')->insert([
            'class_id' => $classId,
            'month' => '1月-2月',
            'student' => json_encode([]),
            'teacher' => json_encode([(string) $coach->ID]),
            'class_year' => '2025',
            'sort' => 202501,
        ]);

        $response = $this->actingAs($coach, 'web')
            ->get('/edu/account/test-result');

        $response->assertForbidden();
    }
}
