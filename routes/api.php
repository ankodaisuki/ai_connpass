<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\EventAttendanceController;
use App\Http\Controllers\Api\V1\EventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // 認証不要
        Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');

        // 認証必須
        Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
            Route::post('refresh', [AuthController::class, 'refresh'])->name('api.v1.auth.refresh');
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });

    // イベント (認証不要)
    Route::get('events', [EventController::class, 'index'])->name('api.v1.events.index');
    Route::get('events/{event}', [EventController::class, 'show'])->name('api.v1.events.show');

    // イベント (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('events', [EventController::class, 'store'])->name('api.v1.events.store');
        Route::put('events/{event}', [EventController::class, 'update'])->name('api.v1.events.update');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('api.v1.events.destroy');
    });

    // イベント参加 (認証不要)
    Route::get('events/{event}/attendances', [EventAttendanceController::class, 'index'])
        ->name('api.v1.events.attendances.index');

    // イベント参加 (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('events/{event}/attendances', [EventAttendanceController::class, 'store'])
            ->name('api.v1.events.attendances.store');
        Route::delete('events/{event}/attendances', [EventAttendanceController::class, 'destroy'])
            ->name('api.v1.events.attendances.destroy');
    });
});
