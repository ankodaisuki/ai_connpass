<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

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
}
