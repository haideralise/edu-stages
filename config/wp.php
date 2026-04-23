<?php

// P3: WordPress integration config — moved from env() for config:cache compatibility

return [
    'login_url' => env('WP_LOGIN_URL', '/wp-login.php'),
    'logged_in_cookie' => env('WP_LOGGED_IN_COOKIE'),
    'auth_key' => env('WP_AUTH_KEY'),
    'auth_salt' => env('WP_AUTH_SALT'),
    'secure_auth_key' => env('WP_SECURE_AUTH_KEY'),
    'secure_auth_salt' => env('WP_SECURE_AUTH_SALT'),
    'logged_in_key' => env('WP_LOGGED_IN_KEY'),
    'logged_in_salt' => env('WP_LOGGED_IN_SALT'),
];
