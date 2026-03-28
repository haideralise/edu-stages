<?php

namespace Tests\Feature;

use App\Models\EduClass;
use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EduClassApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): WpUser
    {
        return WpUser::create([
            'user_login' => 'testuser',
            'user_pass' => bcrypt('secret'),
            'user_email' => 'test@example.com',
            'display_name' => 'Test User',
        ]);
    }

    private function createClass(array $attrs = []): EduClass
    {
        $model = new EduClass;
        $model->forceFill(array_merge([
            'class_name' => '游泳初班',
            'district_id' => 101,
            'product_id' => 5001,
            'product_name' => '游泳初班',
            'date_time' => 'Sat|09:00am-10:00am',
            'date_month' => json_encode(['1月-2月']),
            'class_date' => json_encode(['2025-01-04']),
            'class_exam' => json_encode([1]),
            'lv3' => '鑽石山',
            'class_year' => '2025',
        ], $attrs));
        $model->save();

        return $model;
    }

    // ── Auth ─────────────────────────────────────────────────────

    public function test_returns_401_without_token(): void
    {
        $this->getJson('/api/classes')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized', 'code' => 'UNAUTHORIZED']);
    }

    public function test_returns_200_with_valid_token(): void
    {
        $user = $this->createUser();
        $this->createClass();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['class_id', 'class_name', 'class_year', 'district_id']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'timestamp'],
                'links' => ['next', 'prev'],
            ]);
    }

    // ── Scheme D compliance ──────────────────────────────────────

    public function test_response_follows_scheme_d(): void
    {
        $user = $this->createUser();
        $this->createClass();

        $json = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertIsInt($json['meta']['timestamp']);
        $this->assertEquals(1, $json['meta']['current_page']);
        $this->assertEquals(20, $json['meta']['per_page']);
    }

    // ── Filters ──────────────────────────────────────────────────

    public function test_filter_by_district_id(): void
    {
        $user = $this->createUser();
        $this->createClass(['district_id' => 101]);
        $this->createClass(['district_id' => 102]);

        $json = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?district_id=101')
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data']);
        $this->assertEquals(101, $json['data'][0]['district_id']);
    }

    public function test_filter_by_class_year(): void
    {
        $user = $this->createUser();
        $this->createClass(['class_year' => '2024']);
        $this->createClass(['class_year' => '2025']);

        $json = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?class_year=2024')
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data']);
        $this->assertEquals('2024', $json['data'][0]['class_year']);
    }

    public function test_combined_filters(): void
    {
        $user = $this->createUser();
        $this->createClass(['district_id' => 101, 'class_year' => '2025']);
        $this->createClass(['district_id' => 101, 'class_year' => '2024']);
        $this->createClass(['district_id' => 102, 'class_year' => '2025']);

        $json = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?district_id=101&class_year=2025')
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data']);
    }

    // ── Pagination ───────────────────────────────────────────────

    public function test_pagination(): void
    {
        $user = $this->createUser();
        for ($i = 0; $i < 5; $i++) {
            $this->createClass(['class_name' => "Class {$i}"]);
        }

        $json = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?per_page=2&page=1')
            ->assertOk()
            ->json();

        $this->assertCount(2, $json['data']);
        $this->assertEquals(5, $json['meta']['total']);
        $this->assertEquals(3, $json['meta']['last_page']);
        $this->assertNotNull($json['links']['next']);
        $this->assertNull($json['links']['prev']);
    }

    public function test_pagination_page_2(): void
    {
        $user = $this->createUser();
        for ($i = 0; $i < 5; $i++) {
            $this->createClass(['class_name' => "Class {$i}"]);
        }

        $json = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?per_page=2&page=2')
            ->assertOk()
            ->json();

        $this->assertCount(2, $json['data']);
        $this->assertNotNull($json['links']['prev']);
    }

    // ── Validation ───────────────────────────────────────────────

    public function test_422_on_invalid_district_id(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?district_id=abc')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['district_id']]);
    }

    public function test_422_on_invalid_class_year(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?class_year=24')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['class_year']]);
    }

    public function test_422_on_per_page_over_100(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes?per_page=200')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['per_page']]);
    }

    // ── Edge cases ───────────────────────────────────────────────

    public function test_empty_result(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJson(['data' => [], 'meta' => ['total' => 0]]);
    }

    public function test_resource_includes_all_fields(): void
    {
        $user = $this->createUser();
        $this->createClass();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJsonStructure(['data' => [[
                'class_id', 'class_name', 'district_id', 'product_id',
                'product_name', 'date_time', 'date_month', 'class_date',
                'class_exam', 'lv3', 'class_year',
            ]]]);
    }
}
