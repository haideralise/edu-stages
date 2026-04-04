<?php

/**
 * Coach web routes (read-only views).
 *
 * All routes use auth.wp + role.coach middleware.
 * Route prefixes follow 05eng-routeAndRoleIndex.md SSOT.
 */

use App\Http\Controllers\CoachHistoryController;
use App\Http\Controllers\CoachResultController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth.wp', 'role.coach'])->prefix('edu')->group(function () {

    // result — Coach result entry (own class students only)
    Route::get('/coach/results', [CoachResultController::class, 'index'])->name('coach.results');

    // history_show — Coach history results
    Route::get('/result/history', [CoachHistoryController::class, 'index'])->name('coach.history');
});
