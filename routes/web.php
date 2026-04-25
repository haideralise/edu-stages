<?php

use App\Http\Controllers\Api\EduAdminLogController;
use App\Http\Controllers\Api\EduAssessController;
use App\Http\Controllers\Api\EduAttendanceController;
use App\Http\Controllers\Api\EduClassController;
use App\Http\Controllers\Api\EduCoachController;
use App\Http\Controllers\Api\EduDistrictController;
use App\Http\Controllers\Api\EduOrderController;
use App\Http\Controllers\Api\EduStudentController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (app()->environment('local')) {
        return redirect()->route('login');
    }

    return response('Laravel OK', 200)->header('Content-Type', 'text/plain');
});

// Local dev login (staging uses WordPress /wp-login.php)
Route::middleware('web')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
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


Route::middleware(['web', 'auth.wp', 'role.admin'])->prefix('admin')->group(function () {

    // ── Classes (Stage 3 stub — data wired when ClassMonthFacade is ready) ──
    Route::get('classes', [EduClassController::class, 'index'])->name('admin.class.index');

    // ── Orders ────────────────────────────────────────────────────────────
    Route::get('order/add', [EduOrderController::class, 'showAdd'])->name('admin.order.add');
    Route::get('order/list', [EduOrderController::class, 'showList'])->name('admin.order.list');
    Route::get('order/refund', [EduOrderController::class, 'showRefund'])->name('admin.order.refund');
    Route::get('order/renew', [EduOrderController::class, 'showRenew'])->name('admin.order.renew');
    Route::get('order/classes', [EduOrderController::class, 'searchClasses'])->name('oadmin.rder.classes');
    Route::get('order/months', [EduOrderController::class, 'searchMonths'])->name('admin.order.months'); // select2 AJAX
    Route::get('order/renew-months', [EduOrderController::class, 'searchRenewMonths'])->name('oadmin.rder.renew.months'); // select2 AJAX
    Route::post('order', [EduOrderController::class, 'store'])->name('admin.order.store');
    Route::put('order/{id}', [EduOrderController::class, 'update'])->name('admin.order.update');
    Route::post('order/renew', [EduOrderController::class, 'storeRenew'])->name('admin.order.renew.store');
    Route::post('order/refund', [EduOrderController::class, 'storeRefund'])->name('admin.order.refund.store');
    Route::get('order/renew-list', [EduOrderController::class, 'renewList'])->name('admin.order.renew.list');

    // ── Assess / Field ────────────────────────────────────────────────────
    Route::get('assess/field', [EduAssessController::class, 'field'])->name('admin.assess.field');
    Route::get('assess/popup-field', [EduAssessController::class, 'popupField'])->name('admin.assess.popup-field');
    Route::get('assess/player', [EduAssessController::class, 'player'])->name('admin.assess.player');
    Route::get('assess/video-player', [EduAssessController::class, 'videoPlayer'])->name('admin.assess.video-player');
    Route::post('field/lv1', [EduAssessController::class, 'addLv1'])->name('admin.assess.lv1.store');
    Route::post('field/lv2', [EduAssessController::class, 'addLv2'])->name('admin.assess.lv2.store');
    Route::post('field/item', [EduAssessController::class, 'addItem'])->name('admin.assess.item.store');
    Route::put('field/lv/{id}', [EduAssessController::class, 'updateLv'])->name('admin.assess.lv.update');
    Route::delete('field/lv/{id}', [EduAssessController::class, 'deleteLv'])->name('admin.assess.lv.delete');
    Route::put('field/item/{id}', [EduAssessController::class, 'updateItem'])->name('admin.assess.item.update');
    Route::put('asses/{id}', [EduAssessController::class, 'updatePopupField'])->name('admin.assess.update');
    Route::post('uploader', [EduAssessController::class, 'uploader'])->name('admin.assess.uploader');

    // ── District ──────────────────────────────────────────────────────────
    Route::get('district', [EduDistrictController::class, 'index'])->name('admin.district.index');
    Route::post('district', [EduDistrictController::class, 'store'])->name('admin.district.store');
    Route::put('district/{id}', [EduDistrictController::class, 'update'])->name('admin.district.update');

    // ── Coach management ──────────────────────────────────────────────────
    Route::get('coach', [EduCoachController::class, 'index'])->name('admin.coach.index');
    Route::post('coach/wage', [EduCoachController::class, 'updateWage'])->name('admin.coach.wage.update');
    Route::get('coach/private-classes', [EduCoachController::class, 'privateClasses'])->name('admin.coach.private-classes');
    Route::post('coach/private-classes', [EduCoachController::class, 'savePrivateClasses'])->name('admin.coach.private-classes.save');

    // ── Student management — Admin only (05eng-routeAndRoleIndex §Admin) ──
    Route::get('student', [EduStudentController::class, 'show'])->name('admin.student.show');
    Route::post('student/{id}/fee', [EduStudentController::class, 'storeFee'])->name('admin.student.fee.store');
    Route::get('student/{id}/salary', [EduStudentController::class, 'downloadSalary'])->name('admin.student.salary.download');

    // ── Admin Log ─────────────────────────────────────────────────────────
    Route::get('admin-log', [EduAdminLogController::class, 'index'])->name('aadmin.dmin-log.index');

    // ── Attendance write — Admin only (01eng §5.2.3) ──────────────────────
    Route::post('attendance/ajax', [EduAttendanceController::class, 'ajaxUpdate'])->name('admin.attendance.ajax');
    Route::post('attendance/delete', [EduAttendanceController::class, 'ajaxDelete'])->name('admin.attendance.delete');

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
    Route::get('class/{id}', [EduClassController::class, 'showClass'])->name('edu.class.show');

    // Attendance page — Admin can modify (Group 1 POSTs above); Coach is view-only
    Route::get('attendance', [EduAttendanceController::class, 'index'])->name('edu.attendance.index');
});

// Route::middleware(['web', 'auth.wp', 'role.coach'])->prefix('edu')->group(function () {
//     // result entry, coach_salary (own only) — Stage 3
// });
