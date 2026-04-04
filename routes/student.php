<?php

/**
 * Student web routes.
 *
 * All routes use auth.wp middleware (WP cookie authentication).
 * Route prefixes follow 05eng-routeAndRoleIndex.md SSOT.
 */

use App\Http\Controllers\AccountController;
use App\Http\Controllers\StudentBmiController;
use App\Http\Controllers\StudentChartController;
use App\Http\Controllers\StudentResultController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth.wp'])->prefix('edu/account')->group(function () {

    // Account info (PM-approved scope extension)
    Route::get('/info', [AccountController::class, 'show'])->name('account.info');
    Route::post('/info', [AccountController::class, 'update'])->name('account.info.update');

    // account_mybmi — Student BMI list
    Route::get('/mybmi', [StudentBmiController::class, 'index'])->name('account.mybmi');

    // add_bmi — Student BMI CRUD
    Route::post('/bmi', [StudentBmiController::class, 'store'])->name('account.bmi.store');
    Route::get('/bmi/{bmi}', [StudentBmiController::class, 'show'])->name('account.bmi.show');
    Route::put('/bmi/{bmi}', [StudentBmiController::class, 'update'])->name('account.bmi.update');
    Route::delete('/bmi/{bmi}', [StudentBmiController::class, 'destroy'])->name('account.bmi.delete');

    // account_test_result — Student test results
    Route::get('/test-result', [StudentResultController::class, 'index'])->name('account.test-result');

    // chart2 — Student growth chart
    Route::get('/chart2', [StudentChartController::class, 'index'])->name('account.chart2');
});
