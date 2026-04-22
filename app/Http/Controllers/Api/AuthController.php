<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\WpUser;
// from P1
use App\Services\PasswordHash;
// end from P1
use App\Support\PasswordCheck;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    // ── from P1: PWA cookie → Sanctum Bearer token ──────────────
    public function issue(Request $request)
    {
        $guard = auth('wp');

        if (!$guard->check()) {
            return response()->json([
                'message' => 'Unauthorized',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        $user       = $guard->user();
        $expiration = now()->addDays(7);
        $token      = $user->createToken('pwa-token', ['*'], $expiration);

        return response()->json([
            'data' => [
                'token'      => $token->plainTextToken,
                'expires_at' => $expiration->toISOString(),
            ],
        ]);
    }
    // ── end from P1 ─────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        $user = WpUser::where('user_login', $request->input('user_login'))
            ->orWhere('user_email', $request->input('user_login'))
            ->first();

        if (! $user || ! PasswordCheck::verify($request->input('password'), $user->user_pass)) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        // One token per device context — clean up old "api" tokens first
        $user->tokens()->where('name', 'api')->delete();
        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->formatUser($request->user()));
    }

    private function formatUser(WpUser $user): array
    {
        return [
            'id' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'role' => $user->resolveRole(),
        ];
    }

    // ── from P1: WP multi-format password verification ──────────
    private function checkPassword(string $password, string $hash): bool
    {
        // 1. MD5 (very old WP)
        if (strlen($hash) <= 32) {
            return hash_equals($hash, md5($password));
        }

        // 2. New WordPress hashing (WP 6.8+ → $wp prefix)
        if (str_starts_with($hash, '$wp')) {
            $passwordToVerify = base64_encode(
                hash_hmac('sha384', $password, 'wp-sha384', true)
            );

            return password_verify($passwordToVerify, substr($hash, 3));
        }

        // 3. phpass ($P$ or $H$)
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            $hasher = new PasswordHash(8, true);
            return $hasher->CheckPassword($password, $hash);
        }

        // 4. Modern bcrypt (or anything else)
        return password_verify($password, $hash);
    }
    // ── end from P1 ─────────────────────────────────────────────
}
