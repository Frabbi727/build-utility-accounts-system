<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Resident\BillApiController;
use App\Http\Controllers\Api\V1\Resident\DashboardController;
use App\Http\Controllers\Api\V1\Resident\MaintenanceRequestApiController;
use App\Http\Controllers\Api\V1\Resident\NoticeApiController;
use App\Http\Controllers\Api\V1\Resident\PaymentSubmissionApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public Auth
    Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Authenticated Resident Routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('auth/fcm-token', [AuthController::class, 'registerFcmToken'])->name('api.v1.auth.fcm-token');
        Route::post('auth/change-password', [AuthController::class, 'changePassword'])->name('api.v1.auth.change-password');

        // Resident Portal
        Route::prefix('resident')->group(function (): void {
            Route::get('flats', [DashboardController::class, 'flats'])->name('api.v1.resident.flats');
            Route::get('dashboard', [DashboardController::class, 'index'])->name('api.v1.resident.dashboard');
            Route::get('bills', [BillApiController::class, 'index'])->name('api.v1.resident.bills.index');
            Route::get('bills/{bill}', [BillApiController::class, 'show'])->name('api.v1.resident.bills.show');
            Route::get('payment-submissions', [PaymentSubmissionApiController::class, 'submissions'])->name('api.v1.resident.submissions.index');
            Route::post('payment-submissions', [PaymentSubmissionApiController::class, 'store'])->name('api.v1.resident.submissions.store');
            Route::get('payments', [PaymentSubmissionApiController::class, 'payments'])->name('api.v1.resident.payments.index');
            Route::get('payments/{payment}/receipt', [PaymentSubmissionApiController::class, 'receipt'])->name('api.v1.resident.payments.receipt');
            Route::get('maintenance-requests', [MaintenanceRequestApiController::class, 'index'])->name('api.v1.resident.maintenance.index');
            Route::post('maintenance-requests', [MaintenanceRequestApiController::class, 'store'])->name('api.v1.resident.maintenance.store');
            Route::get('maintenance-requests/{maintenanceRequest}', [MaintenanceRequestApiController::class, 'show'])->name('api.v1.resident.maintenance.show');
            Route::get('notices', [NoticeApiController::class, 'index'])->name('api.v1.resident.notices.index');
            Route::get('notices/{notice}', [NoticeApiController::class, 'show'])->name('api.v1.resident.notices.show');
        });
    });
});
