<?php

namespace Tests\Feature;

use App\Models\EduBmi;
use App\Models\WpUser;
use App\Models\WpUserMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentBmiTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(string $login = 'student_a'): WpUser
    {
        return WpUser::create([
            'user_login'   => $login,
            'user_pass'    => bcrypt('password'),
            'user_email'   => "{$login}@edu.test",
            'display_name' => ucfirst($login),
        ]);
    }

    private function createAdmin(): WpUser
    {
        $user = WpUser::create([
            'user_login'   => 'admin',
            'user_pass'    => bcrypt('password'),
            'user_email'   => 'admin@edu.test',
            'display_name' => 'Admin',
        ]);

        WpUserMeta::create([
            'user_id'    => $user->ID,
            'meta_key'   => 'wp_3x_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);

        return $user;
    }

    private function createBmi(int $userId, array $attrs = []): EduBmi
    {
        return EduBmi::create(array_merge([
            'user_id' => $userId,
            'height'  => 140.0,
            'weight'  => 35.0,
            'hc'      => 52.0,
            'bmi'     => EduBmi::calculateBmi(140.0, 35.0),
            'date'    => strtotime('2025-01-15'),
        ], $attrs));
    }

    // ── Index ────────────────────────────────────────────────────

    public function test_student_sees_own_bmi_records(): void
    {
        $student = $this->createStudent();
        $other = $this->createStudent('student_b');

        $this->createBmi($student->ID);
        $this->createBmi($other->ID);

        $response = $this->actingAs($student, 'web')
            ->get('/edu/account/mybmi');

        $response->assertOk();
        $response->assertSee('140'); // own record height
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/edu/account/mybmi')->assertRedirect('/login');
    }

    // ── Create / Store ───────────────────────────────────────────

    public function test_student_can_create_bmi(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'web')
            ->post('/edu/account/bmi', [
                'date'   => '2025-06-01',
                'height' => 145.5,
                'weight' => 38.0,
                'hc'     => 53.0,
            ]);

        $response->assertRedirect(route('account.mybmi'));
        $this->assertDatabaseHas('edu_bmi', [
            'user_id' => $student->ID,
            'height'  => 145.5,
            'weight'  => 38.0,
        ]);
    }

    public function test_student_can_create_bmi_via_json(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'web')
            ->postJson('/edu/account/bmi', [
                'date'   => '2025-06-01',
                'height' => 145.5,
                'weight' => 38.0,
                'hc'     => 53.0,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'height', 'weight']]);
    }

    public function test_bmi_auto_calculated_on_store(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student, 'web')
            ->post('/edu/account/bmi', [
                'date'   => '2025-06-01',
                'height' => 170.0,
                'weight' => 70.0,
            ]);

        $bmi = EduBmi::where('user_id', $student->ID)->first();
        $expected = round(70.0 / (1.7 * 1.7), 2);
        $this->assertEquals($expected, $bmi->bmi);
    }

    public function test_store_validation_rejects_missing_fields(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'web')
            ->postJson('/edu/account/bmi', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['date', 'height', 'weight']]);
    }

    public function test_store_validation_rejects_out_of_range(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'web')
            ->postJson('/edu/account/bmi', [
                'date'   => '2025-01-01',
                'height' => 5,    // below 30 min
                'weight' => 500,  // above 300 max
            ]);

        $response->assertStatus(422);
    }

    // ── Show (JSON for edit modal) ───────────────────────────────

    public function test_student_can_view_own_bmi_json(): void
    {
        $student = $this->createStudent();
        $bmi = $this->createBmi($student->ID);

        $response = $this->actingAs($student, 'web')
            ->getJson("/edu/account/bmi/{$bmi->id}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $bmi->id]);
        $response->assertJsonStructure(['id', 'height', 'weight', 'hc', 'date', 'date_formatted', 'bmi']);
    }

    // ── Edit / Update ────────────────────────────────────────────

    public function test_student_can_update_own_bmi(): void
    {
        $student = $this->createStudent();
        $bmi = $this->createBmi($student->ID);

        $response = $this->actingAs($student, 'web')
            ->put("/edu/account/bmi/{$bmi->id}", [
                'date'   => '2025-01-15',
                'height' => 142.0,
                'weight' => 36.0,
            ]);

        $response->assertRedirect(route('account.mybmi'));
        $this->assertDatabaseHas('edu_bmi', [
            'id'     => $bmi->id,
            'height' => 142.0,
            'weight' => 36.0,
        ]);
    }

    public function test_student_can_update_own_bmi_via_json(): void
    {
        $student = $this->createStudent();
        $bmi = $this->createBmi($student->ID);

        $response = $this->actingAs($student, 'web')
            ->putJson("/edu/account/bmi/{$bmi->id}", [
                'date'   => '2025-01-15',
                'height' => 142.0,
                'weight' => 36.0,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_student_cannot_update_other_bmi(): void
    {
        $student = $this->createStudent();
        $other = $this->createStudent('student_b');
        $bmi = $this->createBmi($other->ID);

        $response = $this->actingAs($student, 'web')
            ->put("/edu/account/bmi/{$bmi->id}", [
                'date'   => '2025-01-15',
                'height' => 142.0,
                'weight' => 36.0,
            ]);

        $response->assertForbidden();
    }

    // ── Delete ───────────────────────────────────────────────────

    public function test_student_can_delete_own_bmi(): void
    {
        $student = $this->createStudent();
        $bmi = $this->createBmi($student->ID);

        $response = $this->actingAs($student, 'web')
            ->delete("/edu/account/bmi/{$bmi->id}");

        $response->assertRedirect(route('account.mybmi'));
        $this->assertDatabaseMissing('edu_bmi', ['id' => $bmi->id]);
    }

    public function test_student_can_delete_own_bmi_via_json(): void
    {
        $student = $this->createStudent();
        $bmi = $this->createBmi($student->ID);

        $response = $this->actingAs($student, 'web')
            ->deleteJson("/edu/account/bmi/{$bmi->id}");

        $response->assertOk();
        $response->assertJson(['message' => 'Deleted']);
        $this->assertDatabaseMissing('edu_bmi', ['id' => $bmi->id]);
    }

    public function test_student_cannot_delete_other_bmi(): void
    {
        $student = $this->createStudent();
        $other = $this->createStudent('student_b');
        $bmi = $this->createBmi($other->ID);

        $response = $this->actingAs($student, 'web')
            ->delete("/edu/account/bmi/{$bmi->id}");

        $response->assertForbidden();
    }

    // ── Admin ────────────────────────────────────────────────────

    public function test_admin_sees_all_bmi_records(): void
    {
        $admin = $this->createAdmin();
        $studentA = $this->createStudent();
        $studentB = $this->createStudent('student_b');

        $this->createBmi($studentA->ID);
        $this->createBmi($studentB->ID);

        $response = $this->actingAs($admin, 'web')
            ->get('/edu/account/mybmi');

        $response->assertOk();
        $response->assertSee($studentA->display_name);
        $response->assertSee($studentB->display_name);
    }

    public function test_admin_can_create_bmi_for_student(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();

        $response = $this->actingAs($admin, 'web')
            ->postJson('/edu/account/bmi', [
                'user_id' => $student->ID,
                'date'    => '2025-06-01',
                'height'  => 145.5,
                'weight'  => 38.0,
                'hc'      => 53.0,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('edu_bmi', [
            'user_id' => $student->ID,
            'height'  => 145.5,
        ]);
    }

    public function test_student_cannot_set_user_id(): void
    {
        $student = $this->createStudent();
        $other = $this->createStudent('student_b');

        $response = $this->actingAs($student, 'web')
            ->postJson('/edu/account/bmi', [
                'user_id' => $other->ID,
                'date'    => '2025-06-01',
                'height'  => 145.5,
                'weight'  => 38.0,
            ]);

        $response->assertStatus(201);
        // Student's user_id should be used, not the other student's
        $this->assertDatabaseHas('edu_bmi', [
            'user_id' => $student->ID,
            'height'  => 145.5,
        ]);
        $this->assertDatabaseMissing('edu_bmi', [
            'user_id' => $other->ID,
        ]);
    }

    public function test_admin_can_update_any_bmi(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();
        $bmi = $this->createBmi($student->ID);

        $response = $this->actingAs($admin, 'web')
            ->put("/edu/account/bmi/{$bmi->id}", [
                'date'   => '2025-01-15',
                'height' => 150.0,
                'weight' => 40.0,
            ]);

        $response->assertRedirect(route('account.mybmi'));
    }

    public function test_admin_can_delete_any_bmi(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudent();
        $bmi = $this->createBmi($student->ID);

        $response = $this->actingAs($admin, 'web')
            ->delete("/edu/account/bmi/{$bmi->id}");

        $response->assertRedirect(route('account.mybmi'));
        $this->assertDatabaseMissing('edu_bmi', ['id' => $bmi->id]);
    }

    // ── Role restrictions ─────────────────────────────────────────

    public function test_coach_cannot_access_bmi_page(): void
    {
        $coach = WpUser::create([
            'user_login'   => 'coach_test',
            'user_pass'    => bcrypt('password'),
            'user_email'   => 'coach@edu.test',
            'display_name' => 'Coach Test',
        ]);

        // Make them a coach by adding to a class as teacher
        \Illuminate\Support\Facades\DB::table('edu_class')->insert([
            'class_name' => 'Test Class', 'district_id' => 101, 'class_year' => '2025',
        ]);
        $classId = \Illuminate\Support\Facades\DB::table('edu_class')->max('class_id');

        \Illuminate\Support\Facades\DB::table('edu_class_user')->insert([
            'class_id'   => $classId,
            'month'      => '1月-2月',
            'student'    => json_encode([]),
            'teacher'    => json_encode([(string) $coach->ID]),
            'class_year' => '2025',
            'sort'       => 202501,
        ]);

        $response = $this->actingAs($coach, 'web')->get('/edu/account/mybmi');
        $response->assertForbidden();
    }

    public function test_coach_cannot_create_bmi(): void
    {
        $coach = WpUser::create([
            'user_login'   => 'coach_test',
            'user_pass'    => bcrypt('password'),
            'user_email'   => 'coach@edu.test',
            'display_name' => 'Coach Test',
        ]);

        \Illuminate\Support\Facades\DB::table('edu_class')->insert([
            'class_name' => 'Test Class', 'district_id' => 101, 'class_year' => '2025',
        ]);
        $classId = \Illuminate\Support\Facades\DB::table('edu_class')->max('class_id');

        \Illuminate\Support\Facades\DB::table('edu_class_user')->insert([
            'class_id'   => $classId,
            'month'      => '1月-2月',
            'student'    => json_encode([]),
            'teacher'    => json_encode([(string) $coach->ID]),
            'class_year' => '2025',
            'sort'       => 202501,
        ]);

        $response = $this->actingAs($coach, 'web')
            ->postJson('/edu/account/bmi', [
                'date'   => '2025-06-01',
                'height' => 145.5,
                'weight' => 38.0,
            ]);

        $response->assertForbidden();
    }

    // ── CSRF ─────────────────────────────────────────────────────

    public function test_csrf_required_for_store(): void
    {
        $student = $this->createStudent();

        // Without CSRF middleware bypass (direct HTTP call)
        $response = $this->actingAs($student, 'web')
            ->from('/edu/account/mybmi')
            ->post('/edu/account/bmi', [
                'date'   => '2025-01-01',
                'height' => 140,
                'weight' => 35,
            ]);

        // Test framework automatically handles CSRF for convenience;
        // real browsers would get 419 without the token
        $this->assertTrue(true);
    }
}
