<?php

namespace Tests\Feature;

use App\Models\EduBmi;
use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SchemeDErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(): WpUser
    {
        return WpUser::create([
            'user_login' => 'student_test',
            'user_pass' => bcrypt('password'),
            'user_email' => 'student_test@edu.test',
            'display_name' => 'Test Student',
        ]);
    }

    // ── 401 Unauthorized ──────────────────────────────────────

    public function test_401_returns_scheme_d_json(): void
    {
        $response = $this->getJson('/edu/account/mybmi');

        $response->assertStatus(401);
        $response->assertExactJson([
            'message' => 'Unauthorized',
            'code' => 'UNAUTHORIZED',
        ]);
    }

    // ── 403 Forbidden ─────────────────────────────────────────

    public function test_403_returns_scheme_d_json(): void
    {
        $student = $this->createStudent();
        $other = WpUser::create([
            'user_login' => 'student_other',
            'user_pass' => bcrypt('password'),
            'user_email' => 'other@edu.test',
            'display_name' => 'Other Student',
        ]);

        $bmi = EduBmi::create([
            'user_id' => $other->ID,
            'height' => 140.0,
            'weight' => 35.0,
            'hc' => 52.0,
            'bmi' => EduBmi::calculateBmi(140.0, 35.0),
            'date' => strtotime('2025-01-15'),
        ]);

        $response = $this->actingAs($student, 'wp')
            ->putJson("/edu/account/bmi/{$bmi->id}", [
                'date' => '2025-01-15',
                'height' => 142.0,
                'weight' => 36.0,
            ]);

        $response->assertStatus(403);
        $response->assertExactJson([
            'message' => 'Forbidden',
            'code' => 'FORBIDDEN',
        ]);
    }

    // ── 404 Not Found ─────────────────────────────────────────

    public function test_404_returns_scheme_d_json(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->getJson('/edu/account/bmi/99999');

        $response->assertStatus(404);
        $response->assertExactJson([
            'message' => 'Not found',
            'code' => 'NOT_FOUND',
        ]);
    }

    // ── 419 CSRF Token Mismatch ───────────────────────────────

    public function test_419_returns_scheme_d_json(): void
    {
        // Register a test route that throws TokenMismatchException
        Route::post('/test-csrf-419', function () {
            throw new TokenMismatchException;
        });

        $response = $this->postJson('/test-csrf-419');

        $response->assertStatus(419);
        $response->assertExactJson([
            'message' => 'CSRF token mismatch',
            'code' => 'TOKEN_MISMATCH',
        ]);
    }

    // ── 422 Validation Error ──────────────────────────────────

    public function test_422_returns_scheme_d_json(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($student, 'wp')
            ->postJson('/edu/account/bmi', []);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => ['date', 'height', 'weight'],
        ]);
        $response->assertJson([
            'message' => 'Validation failed',
        ]);
    }
}
