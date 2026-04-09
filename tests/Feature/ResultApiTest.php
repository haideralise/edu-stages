<?php

namespace Tests\Feature;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_gets_own_results(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/results');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'results' => [['id', 'user_id', 'exam_id', 'exam_name', 'exam_data', 'exam_date']],
                    'levels',
                ],
                'meta' => ['timestamp'],
            ]);

        $ids = collect($response->json('data.results'))->pluck('user_id')->unique();
        $this->assertEquals([$student->ID], $ids->values()->all());
    }

    public function test_student_cannot_access_other_student(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();
        $other = WpUser::where('user_login', 'student_li')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/results?user_id='.$other->ID);

        $response->assertForbidden();
    }

    public function test_admin_gets_all_results(): void
    {
        $admin = WpUser::where('user_login', 'admin')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/account/results');

        $response->assertOk();

        $ids = collect($response->json('data.results'))->pluck('user_id')->unique();
        $this->assertTrue($ids->count() > 1);
    }

    public function test_admin_filters_by_user_id(): void
    {
        $admin = WpUser::where('user_login', 'admin')->first();
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/account/results?user_id='.$student->ID);

        $response->assertOk();

        $ids = collect($response->json('data.results'))->pluck('user_id')->unique();
        $this->assertEquals([$student->ID], $ids->values()->all());
    }

    public function test_coach_gets_own_class_students(): void
    {
        $coach = WpUser::where('user_login', 'coach_lee')->first();
        $studentIds = EduClassUser::studentIdsForTeacher($coach->ID);

        $response = $this->actingAs($coach, 'sanctum')
            ->getJson('/api/account/results');

        $response->assertOk();

        $returnedIds = collect($response->json('data.results'))->pluck('user_id')->unique();
        foreach ($returnedIds as $id) {
            $this->assertTrue($studentIds->contains($id));
        }
    }

    public function test_coach_cannot_access_non_class_student(): void
    {
        $coach = WpUser::where('user_login', 'coach_lee')->first();
        $studentIds = EduClassUser::studentIdsForTeacher($coach->ID);

        $allStudentIds = EduResult::distinct()->pluck('user_id');
        $outsideStudent = $allStudentIds->first(fn ($id) => ! $studentIds->contains($id));

        if (! $outsideStudent) {
            $this->markTestSkipped('All result students are in coach class');
        }

        $response = $this->actingAs($coach, 'sanctum')
            ->getJson('/api/account/results?user_id='.$outsideStudent);

        $response->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $response = $this->getJson('/api/account/results');

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
            ->getJson('/api/account/results');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['results', 'levels'],
                'meta' => ['timestamp'],
            ]);
    }

    public function test_levels_included_in_response(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/results');

        $response->assertOk();

        $levels = $response->json('data.levels');
        $this->assertNotEmpty($levels);
        $this->assertArrayHasKey('name', $levels[0]);
    }
}
