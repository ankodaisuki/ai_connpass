<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function show(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $events = $user->events()
            ->withCount('attendances')
            ->orderBy('event_date', 'desc')
            ->get();

        $attendanceCount = $user->eventAttendances()->count();

        return view('profile.show', compact('user', 'events', 'attendanceCount'));
    }

    public function destroy(): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $user->update(['status' => UserStatus::Inactive]);

        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('events.index');
    }
}
