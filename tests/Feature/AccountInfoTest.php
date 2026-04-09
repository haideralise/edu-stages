<?php

namespace Tests\Feature;

use App\Models\WpUser;
use App\Models\WpUserMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountInfoTest extends TestCase
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

    // ── Auth ────────────────────────────────────────────────────

    public function test_guest_cannot_see_account_info(): void
    {
        $this->get('/edu/account/info')->assertRedirect('/wp-login.php');
    }

    public function test_student_can_see_account_info(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/info');

        $response->assertOk();
        $response->assertSee('Account Info');
        $response->assertSee($student->user_login);
        $response->assertSee($student->user_email);
    }

    // ── Update ──────────────────────────────────────────────────

    public function test_student_can_update_birthdate_and_gender(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->post('/edu/account/info', [
                'birthdate' => '2010-05-15',
                'gender' => 'female',
            ]);

        $response->assertRedirect(route('account.info'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('usermeta', [
            'user_id' => $student->ID,
            'meta_key' => 'billing_birthdate',
            'meta_value' => '2010-05-15',
        ]);
        $this->assertDatabaseHas('usermeta', [
            'user_id' => $student->ID,
            'meta_key' => 'billing_gender',
            'meta_value' => 'female',
        ]);
    }

    // ── Validation ──────────────────────────────────────────────

    public function test_update_validation_rejects_missing_fields(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->post('/edu/account/info', []);

        $response->assertSessionHasErrors(['birthdate', 'gender']);
    }

    public function test_update_validation_rejects_future_birthdate(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->post('/edu/account/info', [
                'birthdate' => '2099-01-01',
                'gender' => 'male',
            ]);

        $response->assertSessionHasErrors('birthdate');
    }

    public function test_update_validation_rejects_invalid_gender(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->post('/edu/account/info', [
                'birthdate' => '2010-05-15',
                'gender' => 'other',
            ]);

        $response->assertSessionHasErrors('gender');
    }

    // ── Overwrite / Pre-fill ────────────────────────────────────

    public function test_update_overwrites_existing_meta(): void
    {
        $student = $this->createStudent();

        // First save
        $this->actingAs($student, 'wp')
            ->post('/edu/account/info', [
                'birthdate' => '2010-05-15',
                'gender' => 'female',
            ]);

        // Second save with different values
        $this->actingAs($student, 'wp')
            ->post('/edu/account/info', [
                'birthdate' => '2011-08-20',
                'gender' => 'male',
            ]);

        $this->assertDatabaseHas('usermeta', [
            'user_id' => $student->ID,
            'meta_key' => 'billing_birthdate',
            'meta_value' => '2011-08-20',
        ]);
        $this->assertDatabaseHas('usermeta', [
            'user_id' => $student->ID,
            'meta_key' => 'billing_gender',
            'meta_value' => 'male',
        ]);

        // Ensure only one row per meta key
        $this->assertDatabaseCount('usermeta', 2);
    }

    public function test_view_shows_existing_values(): void
    {
        $student = $this->createStudent();

        WpUserMeta::create([
            'user_id' => $student->ID,
            'meta_key' => 'billing_birthdate',
            'meta_value' => '2010-05-15',
        ]);
        WpUserMeta::create([
            'user_id' => $student->ID,
            'meta_key' => 'billing_gender',
            'meta_value' => 'male',
        ]);

        $response = $this->actingAs($student, 'wp')
            ->get('/edu/account/info');

        $response->assertOk();
        $response->assertSee('2010-05-15');
        $response->assertSee('selected');
    }
}
