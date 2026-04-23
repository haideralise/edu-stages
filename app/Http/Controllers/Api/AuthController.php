<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\WpUser;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Issue a Sanctum token for WP user credentials.
     *
     * Doc 08 describes a WP-cookie guard for browser sessions; this endpoint
     * serves API/mobile clients using Sanctum tokens instead. Both share the
     * same WpUser model backed by wp_3x_users.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = WpUser::where('user_login', $request->input('user_login'))
            ->orWhere('user_email', $request->input('user_login'))
            ->first();

        if (! $user || ! $this->checkPassword($request->input('password'), $user->user_pass)) {
            return $this->error('Unauthorized', 'UNAUTHORIZED', 401);
        }

        // One token per device context — clean up old "api" tokens first
        $user->tokens()->where('name', 'api')->delete();
        $token = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user'  => $this->formatUser($user),
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

    // ── Private ──────────────────────────────────────────────────

    private function formatUser(WpUser $user): array
    {
        return [
            'id'           => $user->ID,
            'user_login'   => $user->user_login,
            'user_email'   => $user->user_email,
            'display_name' => $user->display_name,
            'role'         => $user->resolveRole(),
        ];
    }

    /**
     * WP stores passwords as phpass ($P$/$H$), but some may already be
     * bcrypt if previously migrated. We support both.
     */
    private function checkPassword(string $password, string $hash): bool
    {
        // Bcrypt — works if WP passwords were rehashed
        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$')) {
            return Hash::check($password, $hash);
        }

        // WordPress phpass portable hash ($P$ or $H$)
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return $this->verifyPhpass($password, $hash);
        }

        // Ancient MD5 fallback (pre-WP 2.5)
        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals($hash, md5($password));
        }

        return false;
    }

    /**
     * Portable phpass verification — mirrors wp_check_password() internals.
     */
    private function verifyPhpass(string $password, string $stored): bool
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        $countLog2 = strpos($itoa64, $stored[3]);
        $count = 1 << $countLog2;
        $salt  = substr($stored, 4, 8);

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $encoded = substr($stored, 0, 12);
        $i = 0;
        $len = 16;

        do {
            $value = ord($hash[$i++]);
            $encoded .= $itoa64[$value & 0x3f];
            if ($i < $len) {
                $value |= ord($hash[$i]) << 8;
            }
            $encoded .= $itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $len) break;
            if ($i < $len) {
                $value |= ord($hash[$i]) << 16;
            }
            $encoded .= $itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $len) break;
            $encoded .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $len);

        return hash_equals($stored, $encoded);
    }
}
