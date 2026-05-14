<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventAttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->name('events.index');

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('my/attendances', [MyAttendanceController::class, 'index'])->name('my.attendances');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('events/{event}/attendances', [EventAttendanceController::class, 'store'])->name('events.attendances.store');
    Route::delete('events/{event}/attendances', [EventAttendanceController::class, 'destroy'])->name('events.attendances.destroy');
});

Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::middleware('auth')->post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});
