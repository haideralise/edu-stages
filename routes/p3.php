<?php

/**
 * P3 routes — Stage 1 (student/coach pages).
 *
 * All routes use the /edu/ prefix per 05eng spec.
 * Kept in a separate file to avoid merge conflicts with P1's web.php.
 */

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CoachResultController;
use App\Http\Controllers\StudentBmiController;
use App\Http\Controllers\StudentResultController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:sanctum'])->prefix('edu')->group(function () {

    // Account info
    Route::get('/account/info', [AccountController::class, 'show'])->name('account.info');
    Route::post('/account/info', [AccountController::class, 'update'])->name('account.info.update');

    // Student BMI
    Route::get('/account/mybmi', [StudentBmiController::class, 'index'])->name('account.mybmi');
    Route::post('/account/bmi', [StudentBmiController::class, 'store'])->name('account.bmi.store');
    Route::get('/account/bmi/{bmi}', [StudentBmiController::class, 'show'])->name('account.bmi.show');
    Route::put('/account/bmi/{bmi}', [StudentBmiController::class, 'update'])->name('account.bmi.update');
    Route::delete('/account/bmi/{bmi}', [StudentBmiController::class, 'destroy'])->name('account.bmi.delete');

    // Student test results
    Route::get('/account/test-result', [StudentResultController::class, 'index'])->name('account.test-result');

    // Coach results (read-only)
    Route::get('/coach/results', [CoachResultController::class, 'index'])->name('coach.results');
});
