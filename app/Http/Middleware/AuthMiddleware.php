<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // wp-cookie guard (production/staging with WordPress)
        if (auth('wp')->check()) {
            return $next($request);
        }

        // Fallback: standard session guard (local dev via LoginController)
        if (app()->environment('local') && auth('web')->check()) {
            // Bridge the web user into the wp guard so $request->user() works
            auth('wp')->setUser(auth('web')->user());

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthorized',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        return redirect(
            app()->environment('local') ? route('login') : config('services.wp.login_url', '/wp-login.php')
        );
    }
}
