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

    // Classes (readonly)
    Route::get('/classes', [EduClassController::class, 'index']);

    // BMI records
    Route::get('/bmi', [BmiController::class, 'index']);

    // Test results
    Route::get('/results', [ResultController::class, 'index']);

    // Chart2 — growth charts
    Route::get('/chart2/bmi/{user_id}', [Chart2Controller::class, 'bmi']);
    Route::get('/chart2/result/{user_id}', [Chart2Controller::class, 'result']);
});
