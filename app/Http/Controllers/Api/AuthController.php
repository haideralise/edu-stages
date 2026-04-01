<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WpUser;
use Illuminate\Http\Request;
use App\Services\PasswordHash;

class AuthController extends Controller
{
    // PWA: validate WP cookie → issue Sanctum Bearer token
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

    // API login with WP username + password
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = WpUser::where('user_login', $request->login)
            ->orWhere('user_email', $request->login)
            ->first();

        if (!$user || !$this->checkPassword($request->password, $user->user_pass)) {
            return response()->json([
                'message' => 'Unauthorized',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        $expiration = now()->addDays(7);
        $token      = $user->createToken('api-token', ['*'], $expiration);

        return response()->json([
            'data' => [
                'token'      => $token->plainTextToken,
                'expires_at' => $expiration->toISOString(),
            ],
        ]);
    }

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
}
