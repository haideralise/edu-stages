<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require __DIR__.'/../routes/p3.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Doc 09 Scheme D — 401 Unauthorized
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'code' => 'UNAUTHORIZED',
                ], 401);
            }
        });

        // Doc 09 Scheme D — 419 CSRF Token Mismatch
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'CSRF token mismatch',
                    'code' => 'TOKEN_MISMATCH',
                ], 419);
            }
        });

        // Doc 09 Scheme D — 403 Forbidden
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Forbidden',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        });

        // Doc 09 Scheme D — 404 Not Found
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Not found',
                    'code' => 'NOT_FOUND',
                ], 404);
            }
        });

        // Doc 09 Scheme D — 419 CSRF (HttpException fallback)
        // Laravel converts TokenMismatchException → HttpException(419) in prepareException
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 && ($request->expectsJson() || $request->is('api/*'))) {
                return response()->json([
                    'message' => 'CSRF token mismatch',
                    'code' => 'TOKEN_MISMATCH',
                ], 419);
            }
        });

        // Doc 09 Scheme D — 500 Server Error
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if ($statusCode >= 500) {
                    return response()->json([
                        'message' => 'Server error',
                        'code' => 'SERVER_ERROR',
                    ], 500);
                }
            }
        });
    })->create();
