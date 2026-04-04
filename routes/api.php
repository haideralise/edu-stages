<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BmiController;
use App\Http\Controllers\Api\Chart2Controller;
use App\Http\Controllers\Api\EduClassController;
use App\Http\Controllers\Api\ResultController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected — Sanctum token required
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Classes (coach/admin, readonly)
    Route::get('/classes', [EduClassController::class, 'index']);

    // Student account endpoints (09eng §PWA Student)
    Route::prefix('account')->group(function () {
        Route::get('/bmi', [BmiController::class, 'index']);
        Route::get('/results', [ResultController::class, 'index']);
        Route::get('/growth-chart', [Chart2Controller::class, 'index']);
    });
});
