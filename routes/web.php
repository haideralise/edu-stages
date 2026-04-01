<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('Laravel OK', 200)->header('Content-Type', 'text/plain');
});

Route::get('/test-auth', function () {
    $guard = auth('wp');
    if (!$guard->check()) {
        return response()->json(['error' => 'Not logged in'], 401);
    }
    return response()->json([
        'user_id'    => $guard->user()->ID,
        'user_login' => $guard->user()->user_login,
        'role'       => $guard->getRole(),
    ]);
});

// Any logged-in user (admin, coach, student)
Route::middleware('auth.wp')->group(function () {
    Route::get('/dashboard', fn() => 'Welcome');
});

// Admin only
Route::middleware('role.admin')->group(function () {
    Route::get('/admin/classes', fn() => 'Admin Classes');
});

// Admin or Coach
Route::middleware('role.coach')->group(function () {
    Route::get('/coach/attendance', fn() => 'Coach Attendance');
});
