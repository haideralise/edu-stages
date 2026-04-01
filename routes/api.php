<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// PWA: exchange WP cookie for Bearer token
Route::get('/auth/token', [AuthController::class, 'issue']);

// Login with WP credentials
Route::post('/login', [AuthController::class, 'login']);
