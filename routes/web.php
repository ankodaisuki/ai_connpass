<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventAttendanceController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventOrganizerController;
use App\Http\Controllers\EventOwnershipController;
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
    Route::post('events/{event}/organizers', [EventOrganizerController::class, 'store'])->name('events.organizers.store');
    Route::delete('events/{event}/organizers/{eventOrganizer}', [EventOrganizerController::class, 'destroy'])->scopeBindings()->name('events.organizers.destroy');
    Route::patch('organizer-invitations/{eventOrganizer}/accept', [EventOrganizerController::class, 'accept'])->name('organizer-invitations.accept');
    Route::patch('organizer-invitations/{eventOrganizer}/decline', [EventOrganizerController::class, 'decline'])->name('organizer-invitations.decline');
    Route::patch('events/{event}/ownership', [EventOwnershipController::class, 'update'])->name('events.ownership.update');
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
