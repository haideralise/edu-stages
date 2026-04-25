<?php

namespace Tests\Feature;

use App\Models\EduOrder;
use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_gets_own_orders(): void
    {
        $student = WpUser::where('user_login', 'student_chan')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/account/orders');

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
            ->getJson('/api/account/orders?user_id=' . $other->ID);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_admin_gets_orders(): void
    {
        $admin = WpUser::where('user_login', 'admin')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/account/orders');

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
            ->getJson('/api/account/orders?user_id=' . $student->ID);

        $response->assertOk();
    }

    public function test_guest_gets_401(): void
    {
        $response = $this->getJson('/api/account/orders');

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
            ->getJson('/api/account/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['timestamp'],
            ]);
    }
}
