<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventAttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GoogleCalendarConnectionController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->name('events.index');

// 一時デバッグ用（確認後削除）
Route::get('debug/google-config', function () {
    return response()->json([
        'GOOGLE_CLIENT_ID' => getenv('GOOGLE_CLIENT_ID') ? 'SET' : 'EMPTY',
        'APP_KEY' => getenv('APP_KEY') ? 'SET' : 'EMPTY',
        'DB_HOST' => getenv('DB_HOST') ? 'SET' : 'EMPTY',
        'APP_ENV' => getenv('APP_ENV'),
        'RAILWAY_PUBLIC_DOMAIN' => getenv('RAILWAY_PUBLIC_DOMAIN') ? 'SET' : 'EMPTY',
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('my/attendances', [MyAttendanceController::class, 'index'])->name('my.attendances');
    Route::get('my/attended-events', [MyAttendanceController::class, 'attended'])->name('my.attended-events');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('events/{event}/attendances', [EventAttendanceController::class, 'store'])->name('events.attendances.store');
    Route::delete('events/{event}/attendances', [EventAttendanceController::class, 'destroy'])->name('events.attendances.destroy');
    Route::patch('events/{event}/attendances/{attendance}', [EventAttendanceController::class, 'update'])->name('events.attendances.update');
    Route::get('events/{event}/attendances', fn ($event) => redirect()->route('events.show', $event));
    Route::get('google/connect', [GoogleCalendarConnectionController::class, 'connect'])->name('google.connect');
    Route::get('google/callback', [GoogleCalendarConnectionController::class, 'callback'])->name('google.callback');
    Route::delete('google/disconnect', [GoogleCalendarConnectionController::class, 'disconnect'])->name('google.disconnect');
});

Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::middleware('auth')->post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});
