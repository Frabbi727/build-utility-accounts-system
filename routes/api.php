<?php

use App\Http\Controllers\Api\V1\AuthController;
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
    });
});
