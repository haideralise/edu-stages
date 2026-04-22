<?php

use App\Http\Controllers\Api\EduAdminLogController;
use App\Http\Controllers\Api\EduAssessController;
use App\Http\Controllers\Api\EduAttendanceController;
use App\Http\Controllers\Api\EduClassController;
use App\Http\Controllers\Api\EduCoachController;
use App\Http\Controllers\Api\EduDistrictController;
use App\Http\Controllers\Api\EduOrderController;
use App\Http\Controllers\Api\EduStudentController;
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


Route::middleware(['web', 'auth.wp', 'role.admin'])->group(function () {

    // ── Classes (Stage 3 stub — data wired when ClassMonthFacade is ready) ──
    Route::get('classes',              [EduClassController::class, 'index']);
    Route::get('class/{id}',           [EduClassController::class, 'showClass']);
    Route::put('class/{id}',           [EduClassController::class, 'updateClass']);

    // ── Orders ────────────────────────────────────────────────────────────
    Route::get('order/add',            [EduOrderController::class, 'showAdd']);
    Route::get('order/list',           [EduOrderController::class, 'showList']);
    Route::get('order/refund',         [EduOrderController::class, 'showRefund']);
    Route::get('order/renew',          [EduOrderController::class, 'showRenew']);
    Route::get('order/classes',        [EduOrderController::class, 'searchClasses']);    // select2 AJAX
    Route::get('order/months',         [EduOrderController::class, 'searchMonths']);     // select2 AJAX
    Route::get('order/renew-months',   [EduOrderController::class, 'searchRenewMonths']); // select2 AJAX
    Route::post('order',               [EduOrderController::class, 'store']);
    Route::put('order/{id}',           [EduOrderController::class, 'update']);
    Route::post('order/renew',         [EduOrderController::class, 'storeRenew']);
    Route::post('order/refund',        [EduOrderController::class, 'storeRefund']);

    // ── Assess / Field ────────────────────────────────────────────────────
    Route::get('assess/field',         [EduAssessController::class, 'field']);
    Route::get('assess/popup-field',   [EduAssessController::class, 'popupField']);
    Route::get('assess/player',        [EduAssessController::class, 'player']);
    Route::get('assess/video-player',  [EduAssessController::class, 'videoPlayer']);
    Route::post('field/lv1',           [EduAssessController::class, 'addLv1']);
    Route::post('field/lv2',           [EduAssessController::class, 'addLv2']);
    Route::post('field/item',          [EduAssessController::class, 'addItem']);
    Route::put('field/lv/{id}',        [EduAssessController::class, 'updateLv']);
    Route::delete('field/lv/{id}',     [EduAssessController::class, 'deleteLv']);
    Route::put('field/item/{id}',      [EduAssessController::class, 'updateItem']);
    Route::put('asses/{id}',           [EduAssessController::class, 'updatePopupField']); // asses_popup_field.js
    Route::post('uploader',            [EduAssessController::class, 'uploader']);          // WebUploader

    // ── District ──────────────────────────────────────────────────────────
    Route::get('district',             [EduDistrictController::class, 'index']);
    Route::post('district',            [EduDistrictController::class, 'store']);
    Route::put('district/{id}',        [EduDistrictController::class, 'update']);

    // ── Coach management ──────────────────────────────────────────────────
    Route::get('coach',                [EduCoachController::class, 'index']);
    Route::post('coach/wage',          [EduCoachController::class, 'updateWage']);         // class_coach.js focusout
    Route::get('coach/private-classes', [EduCoachController::class, 'privateClasses']);   // 05eng: Admin only
    Route::post('coach/private-classes',[EduCoachController::class, 'savePrivateClasses']);

    // ── Student management — Admin only (05eng-routeAndRoleIndex §Admin) ──
    Route::get('student',              [EduStudentController::class, 'show']);
    Route::post('student/{id}/fee',    [EduStudentController::class, 'storeFee']);
    Route::get('student/{id}/salary',  [EduStudentController::class, 'downloadSalary']);  // Stage 4 stub

    // ── Admin Log ─────────────────────────────────────────────────────────
    Route::get('admin-log',            [EduAdminLogController::class, 'index']);

    // ── Attendance write — Admin only (01eng §5.2.3) ──────────────────────
    Route::post('attendance/ajax',     [EduAttendanceController::class, 'ajaxUpdate']);
    Route::post('attendance/delete',   [EduAttendanceController::class, 'ajaxDelete']);


    // ── NEW AJAX operations (split from handlePostRequestsClass) ──
    Route::post('class/{id}/prev-data', [EduClassController::class, 'getPrevData']);
    Route::post('class/{id}/exam', [EduClassController::class, 'addExam']);
    Route::put('class/{id}/user', [EduClassController::class, 'updateUser']);  // ✅ RENAMED (was /class/{id})
    Route::post('class/{id}/month-data', [EduClassController::class, 'getMonthData']);
    Route::post('class/{id}/levels', [EduClassController::class, 'getLevel2']);
    Route::put('class/{id}/exam/update', [EduClassController::class, 'updateExam']);  // ✅ RENAMED (was /class/{id}/exam)
});


Route::middleware(['web', 'auth.wp'])->group(function () {

    // Class detail page — Admin can modify (Group 1 PUT above); Coach is view-only
    // $is_admin passed to view controls edit UI visibility
    Route::get('class/{id}',           [EduClassController::class, 'showClass']);

    // Attendance page — Admin can modify (Group 1 POSTs above); Coach is view-only
    Route::get('attendance',           [EduAttendanceController::class, 'index']);
});

// Route::middleware(['web', 'auth.wp', 'role.coach'])->group(function () {
//     // result entry, coach_salary (own only) — Stage 3
// });
