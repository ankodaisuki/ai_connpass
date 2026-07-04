<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventAttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventCoOrganizerController;
use App\Http\Controllers\EventOwnershipController;
use App\Http\Controllers\EventReminderController;
use App\Http\Controllers\GoogleCalendarConnectionController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\MyCoOrganizingEventController;
use App\Http\Controllers\MyOrganizerInvitationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->name('events.index');

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
    Route::get('my/organizer-invitations', [MyOrganizerInvitationController::class, 'index'])->name('my.organizer-invitations');
    Route::get('my/co-organizing-events', [MyCoOrganizingEventController::class, 'index'])->name('my.co-organizing-events');
    Route::post('events/{event}/organizers', [EventCoOrganizerController::class, 'store'])->name('events.organizers.store');
    Route::delete('events/{event}/organizers/{eventCoOrganizer}', [EventCoOrganizerController::class, 'destroy'])->scopeBindings()->name('events.organizers.destroy');
    Route::patch('organizer-invitations/{eventCoOrganizer}/accept', [EventCoOrganizerController::class, 'accept'])->name('organizer-invitations.accept');
    Route::patch('organizer-invitations/{eventCoOrganizer}/decline', [EventCoOrganizerController::class, 'decline'])->name('organizer-invitations.decline');
    Route::patch('events/{event}/ownership', [EventOwnershipController::class, 'update'])->name('events.ownership.update');
    Route::post('events/{event}/reminders', [EventReminderController::class, 'store'])->name('events.reminders.store');
    Route::post('events/{event}/reminders/{reminder}/resend', [EventReminderController::class, 'resend'])->scopeBindings()->name('events.reminders.resend');
    Route::get('google/connect', [GoogleCalendarConnectionController::class, 'connect'])->name('google.connect');
    Route::get('google/callback', [GoogleCalendarConnectionController::class, 'callback'])->name('google.callback');
    Route::delete('google/disconnect', [GoogleCalendarConnectionController::class, 'disconnect'])->name('google.disconnect');
});

Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('users/{user}/freeze', [AdminUserController::class, 'freeze'])->name('users.freeze');
    Route::post('users/{user}/unfreeze', [AdminUserController::class, 'unfreeze'])->name('users.unfreeze');
    Route::get('events', [AdminEventController::class, 'index'])->name('events.index');
    Route::get('events/trashed', [AdminEventController::class, 'trashed'])->name('events.trashed');
    Route::patch('events/{id}/restore', [AdminEventController::class, 'restore'])->name('events.restore');
    Route::delete('events/{event}', [AdminEventController::class, 'destroy'])->name('events.destroy');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

Route::middleware('auth')->post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});
