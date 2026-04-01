<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.wp'    => \App\Http\Middleware\AuthMiddleware::class,
            'role.admin' => \App\Http\Middleware\AdminMiddleware::class,
            'role.coach' => \App\Http\Middleware\CoachMiddleware::class,
        ]);
    })
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {

        // Scheme D — 401 Unauthorized
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'code'    => 'UNAUTHORIZED',
                ], 401);
            }
        });

        // Scheme D — 422 Validation
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Scheme D — 404 Not Found
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Not found',
                    'code'    => 'NOT_FOUND',
                ], 404);
            }
        });

        // Scheme D — 500 Server Error
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if ($statusCode >= 500) {
                    return response()->json([
                        'message' => 'Server error',
                        'code'    => 'SERVER_ERROR',
                    ], 500);
                }
            }
        });
    })->create();
