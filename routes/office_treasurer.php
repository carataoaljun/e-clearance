<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Office\AuthController as OfficeAuthController;
use App\Http\Controllers\Office\DashboardController as OfficeDashboardController;
use App\Http\Controllers\Treasurer\AuthController as TreasurerAuthController;
use App\Http\Controllers\Treasurer\DashboardController as TreasurerDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Office Portal
|--------------------------------------------------------------------------
*/
Route::prefix('office')->name('office.')->group(function () {
    Route::get('login', [OfficeAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [OfficeAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('logout', [OfficeAuthController::class, 'logout'])->middleware('office.auth', 'no.history')->name('logout');

    Route::middleware(['office.auth', 'no.history'])->group(function () {
        Route::get('dashboard', [OfficeDashboardController::class, 'index'])->name('dashboard');
        Route::get('submissions', [OfficeDashboardController::class, 'submissions'])->name('submissions');
        Route::get('submissions/{submission}/file', [OfficeDashboardController::class, 'viewSubmissionFile'])->name('submissions.file');
        Route::get('clearance-requests', [OfficeDashboardController::class, 'clearanceRequests'])->name('clearance.requests');
        Route::post('clearance/status', [OfficeDashboardController::class, 'setClearanceStatus'])->name('clearance.status');
        Route::post('clearance/bulk-status', [OfficeDashboardController::class, 'bulkSetClearanceStatus'])->name('clearance.bulk-status');
        Route::get('account/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
    });
});

/*
|--------------------------------------------------------------------------
| Treasurer Portal
|--------------------------------------------------------------------------
*/
Route::prefix('treasurer')->name('treasurer.')->group(function () {
    Route::get('login', [TreasurerAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [TreasurerAuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('logout', [TreasurerAuthController::class, 'logout'])->middleware('treasurer.auth', 'no.history')->name('logout');

    Route::middleware(['treasurer.auth', 'no.history'])->group(function () {
        Route::get('dashboard', [TreasurerDashboardController::class, 'index'])->name('dashboard');
        Route::get('clearance-updates', [TreasurerDashboardController::class, 'clearanceUpdates'])->name('clearance-updates');
        Route::post('clearance/status', [TreasurerDashboardController::class, 'setClearanceStatus'])->name('clearance.status');
        Route::post('clearance/bulk-status', [TreasurerDashboardController::class, 'bulkSetClearanceStatus'])->name('clearance.bulk-status');
        Route::get('submission-remark', [TreasurerDashboardController::class, 'submissionRemark'])->name('submission-remark');
        Route::get('submission-remark/{submission}/file', [TreasurerDashboardController::class, 'viewSubmissionFile'])->name('submission-remark.file');
        Route::get('account/edit', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
    });
});
