<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wp' => [
        'logged_in_cookie' => env('WP_LOGGED_IN_COOKIE'),
        'logged_in_key' => env('WP_LOGGED_IN_KEY'),
        'logged_in_salt' => env('WP_LOGGED_IN_SALT'),

        'auth_key' => env('WP_AUTH_KEY'),
        'auth_salt' => env('WP_AUTH_SALT'),

        'secure_auth_key' => env('WP_SECURE_AUTH_KEY'),
        'secure_auth_salt' => env('WP_SECURE_AUTH_SALT'),

        'nonce_key' => env('WP_NONCE_KEY'),
        'nonce_salt' => env('WP_NONCE_SALT'),

        'login_url' => env('WP_LOGIN_URL'),
    ],

];
