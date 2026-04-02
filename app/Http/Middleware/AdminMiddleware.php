<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $guard = auth('wp');
        if (!$guard->check() || $guard->getRole() !== 'admin') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'code'    => 'UNAUTHORIZED',
                ], 401);
            }
            return redirect(env('WP_LOGIN_URL', '/wp-login.php'));
        }
        return $next($request);
    }
}
