<?php

namespace Tests\Feature;

use App\Models\WpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $password = 'secret'): WpUser
    {
        return WpUser::create([
            'user_login' => 'coach_lee',
            'user_pass' => bcrypt($password),
            'user_email' => 'lee@edu.test',
            'display_name' => 'Coach Lee',
        ]);
    }

    // ── Login ────────────────────────────────────────────────────

    public function test_login_success(): void
    {
        $this->createUser('secret');

        $this->postJson('/api/login', [
            'login' => 'coach_lee',
            'password' => 'secret',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'expires_at'],
            ]);
    }

    public function test_login_by_email(): void
    {
        $this->createUser('secret');

        $this->postJson('/api/login', [
            'login' => 'lee@edu.test',
            'password' => 'secret',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_wrong_password(): void
    {
        $this->createUser('secret');

        $this->postJson('/api/login', [
            'login' => 'coach_lee',
            'password' => 'wrong',
        ])
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized', 'code' => 'UNAUTHORIZED']);
    }

    public function test_login_nonexistent_user(): void
    {
        $this->postJson('/api/login', [
            'login' => 'nobody',
            'password' => 'anything',
        ])->assertStatus(401);
    }

    public function test_login_validation(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['login', 'password']]);
    }

    // ── Logout ───────────────────────────────────────────────────

    public function test_logout(): void
    {
        $user = $this->createUser('secret');

        $login = $this->postJson('/api/login', [
            'login' => 'coach_lee',
            'password' => 'secret',
        ]);
        $token = $login->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_logout_requires_auth(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    // ── Me ───────────────────────────────────────────────────────

    public function test_me(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'user_login', 'user_email', 'display_name', 'role'],
                'meta' => ['timestamp'],
            ]);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    // ── Token access ─────────────────────────────────────────────

    public function test_token_works_for_classes(): void
    {
        $user = $this->createUser('secret');

        $token = $this->postJson('/api/login', [
            'login' => 'coach_lee',
            'password' => 'secret',
        ])->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }
}
