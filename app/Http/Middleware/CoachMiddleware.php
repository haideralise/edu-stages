<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CoachMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        /** @var \App\Auth\WpUserGuard $guard */
        $guard = auth('wp');
        if (!$guard->check() || !in_array($guard->getRole(), ['admin', 'coach'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'code'    => 'UNAUTHORIZED',
                ], 401);
            }
            return redirect(
                app()->environment('local') ? route('login') : config('wp.login_url', '/wp-login.php')
            );
        }
        return $next($request);
    }
}
