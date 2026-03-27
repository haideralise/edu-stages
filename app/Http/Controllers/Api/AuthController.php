<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\WpUser;
use App\Support\PasswordCheck;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

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
}
