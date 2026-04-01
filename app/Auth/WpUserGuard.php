<?php

namespace App\Auth;

use App\Models\WpUser;
use App\Models\EduClassUser;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class WpUserGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;
    protected ?string $role = null;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request  = $request;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $cookieName = $this->getCookieName();
        if (!$cookieName || empty($_COOKIE[$cookieName])) {
            return null;
        }
        if (!$cookieName || empty($_COOKIE[$cookieName])) {
            return null;
        }

        $user = $this->validateWpCookie($_COOKIE[$cookieName]);

        if (!$user) {
            return null;
        }

        $this->user = $user;
        $this->role = $this->detectRole($user);

        return $this->user;
    }

    public function validate(array $credentials = []): bool
    {
        return false; // Not used; we rely on WP cookie
    }

    public function getRole(): ?string
    {
        $this->user(); // trigger detection if not done yet
        return $this->role;
    }

    // -------------------------------------------------------
    // Role detection — exact priority order from docs §0.6
    // -------------------------------------------------------
    protected function detectRole(WpUser $user): string
    {
        // Priority 1: username is 'mssc' → admin
        if ($user->user_login === 'mssc') {
            return 'admin';
        }

        // Priority 2: usermeta wp_capabilities contains 'administrator' → admin
        $capabilities = $user->meta()
            ->where('meta_key', 'wp_3x_capabilities')
            ->value('meta_value');

        if ($capabilities && str_contains($capabilities, 'administrator')) {
            return 'admin';
        }

        // Priority 3: edu_class_user.teacher JSON contains this user_id → coach
        $isCoach = EduClassUser::whereRaw('JSON_VALID(teacher)')
            ->whereJsonContains('teacher', (string) $user->ID)
            ->exists();

        if ($isCoach) {
            return 'coach';
        }

        // Priority 4: everyone else → student
        return 'student';
    }

    protected function getCookieName(): ?string
    {
        // First try LOGGED_IN_COOKIE from .env
        $name = env('WP_LOGGED_IN_COOKIE');
        if ($name && isset($_COOKIE[$name])) {
            return $name;
        }

        // Fallback: scan $_COOKIE for wordpress_logged_in_* key
        foreach ($_COOKIE as $key => $value) {
            if (str_starts_with($key, 'wordpress_logged_in_')) {
                return $key;
            }
        }

        return null;
    }

    protected function validateWpCookie(string $cookie): ?WpUser
    {
        // 1. Parse cookie
        $parts = explode('|', $cookie);

        if (count($parts) !== 4) {
            return null;
        }

        [$username, $expiration, $token, $hmac] = $parts;

        // 2. Expiration check
        if ((int) $expiration < time()) {
            return null;
        }

        // 3. Get user
        $user = WpUser::where('user_login', $username)->first();
        if (!$user) {
            return null;
        }

        // 4. Password fragment (IMPORTANT - same as WP)
        if (str_starts_with($user->user_pass, '$P$') || str_starts_with($user->user_pass, '$2y$')) {
            $passFrag = substr($user->user_pass, 8, 4);
        } else {
            $passFrag = substr($user->user_pass, -4);
        }

        // 5. Generate key (equivalent of wp_hash)
        $scheme = 'logged_in';

        $salt = $this->wpSalt($scheme);

        $key = hash_hmac(
            'md5',
            $username . '|' . $passFrag . '|' . $expiration . '|' . $token,
            $salt
        );

        // 6. Generate HMAC
        $expected = hash_hmac(
            'sha256',
            $username . '|' . $expiration . '|' . $token,
            $key
        );

        if (!hash_equals($expected, $hmac)) {
            return null;
        }

        // 7. Verify session token (VERY IMPORTANT)
        if (!$this->verifySessionToken($user, $token)) {
            return null;
        }

        return $user;
    }

    protected function wpSalt(string $scheme): string
    {
        switch ($scheme) {
            case 'auth':
                return env('WP_AUTH_KEY') . env('WP_AUTH_SALT');

            case 'secure_auth':
                return env('WP_SECURE_AUTH_KEY') . env('WP_SECURE_AUTH_SALT');

            case 'logged_in':
            default:
                return env('WP_LOGGED_IN_KEY') . env('WP_LOGGED_IN_SALT');
        }
    }

    protected function verifySessionToken(WpUser $user, string $token): bool
    {
        $meta = $user->meta()
            ->where('meta_key', 'session_tokens')
            ->value('meta_value');

        if (!$meta) {
            return false;
        }

        $sessions = @unserialize($meta);

        if (!is_array($sessions)) {
            return false;
        }

        $hashed = hash('sha256', $token);

        if (!isset($sessions[$hashed])) {
            return false;
        }

        if ($sessions[$hashed]['expiration'] < time()) {
            return false;
        }

        return true;
    }
}
