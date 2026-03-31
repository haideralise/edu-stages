<?php

namespace Tests\Feature;

use App\Models\EduBmi;
use App\Models\EduClassUser;
use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BmiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_gets_own_bmi_records(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/bmi');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'user_id', 'height', 'weight', 'hc', 'bmi', 'category', 'date']],
                'meta' => ['timestamp'],
            ]);

        // All records belong to this student
        $ids = collect($response->json('data'))->pluck('user_id')->unique();
        $this->assertEquals([$student->ID], $ids->values()->all());
    }

    public function test_student_cannot_access_other_student(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();
        $other = WpUser::where('user_login', 'student_li')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/bmi?user_id='.$other->ID);

        $response->assertForbidden();
    }

    public function test_admin_gets_all_bmi_records(): void
    {
        $admin = WpUser::where('user_login', 'admin')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/bmi');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('user_id')->unique();
        $this->assertTrue($ids->count() > 1);
    }

    public function test_admin_filters_by_user_id(): void
    {
        $admin = WpUser::where('user_login', 'admin')->first();
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/bmi?user_id='.$student->ID);

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('user_id')->unique();
        $this->assertEquals([$student->ID], $ids->values()->all());
    }

    public function test_coach_gets_own_class_students(): void
    {
        $coach = WpUser::where('user_login', 'coach_lee')->first();
        $studentIds = EduClassUser::studentIdsForTeacher($coach->ID);

        $response = $this->actingAs($coach, 'sanctum')
            ->getJson('/api/bmi');

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('user_id')->unique();
        foreach ($returnedIds as $id) {
            $this->assertTrue($studentIds->contains($id));
        }
    }

    public function test_coach_cannot_access_non_class_student(): void
    {
        $coach = WpUser::where('user_login', 'coach_lee')->first();
        $studentIds = EduClassUser::studentIdsForTeacher($coach->ID);

        // Find a student NOT in coach's classes
        $allStudentIds = EduBmi::distinct()->pluck('user_id');
        $outsideStudent = $allStudentIds->first(fn ($id) => ! $studentIds->contains($id));

        if (! $outsideStudent) {
            $this->markTestSkipped('All BMI students are in coach class');
        }

        $response = $this->actingAs($coach, 'sanctum')
            ->getJson('/api/bmi?user_id='.$outsideStudent);

        $response->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $response = $this->getJson('/api/bmi');

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
            ->getJson('/api/bmi');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['timestamp'],
            ]);
    }

    public function test_category_included_in_response(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/bmi');

        $response->assertOk();

        $first = $response->json('data.0');
        $this->assertArrayHasKey('category', $first);
        $this->assertContains($first['category'], ['underweight', 'normal', 'overweight', 'obese']);
    }
}
