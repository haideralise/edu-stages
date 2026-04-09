<?php

use App\Models\User;
use App\Models\WpUser;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'wp'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'wp_users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'wp_users',
        ],
        // P1: WP cookie authentication for web routes
        'wp' => [
            'driver' => 'wp-cookie',
            'provider' => 'wp_users',
        ],
        // Sanctum reads Bearer token and resolves via wp_users provider
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'wp_users',
        ],
    ],

    'providers' => [
        // Default Laravel (unused, kept for scaffold compatibility)
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
        // WordPress users — shared DB with edu2
        'wp_users' => [
            'driver' => 'eloquent',
            'model' => WpUser::class,
        ],
    ],

    'passwords' => [
        'wp_users' => [
            'provider' => 'wp_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
