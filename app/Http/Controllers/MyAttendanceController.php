<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MyAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $tab = $request->query('tab') === 'waitlist' ? 'waitlist' : 'applied';

        if ($tab === 'waitlist') {
            $attendances = $user->eventAttendances()
                ->waitlistedToPublishedEvent()
                ->with('event.user')
                ->orderBy('waitlisted_at', 'asc')
                ->paginate(15)->withQueryString();
        } else {
            $attendances = $user->eventAttendances()
                ->appliedToPublishedEvent()
                ->with('event.user')
                ->orderBy('applied_at', 'asc')
                ->paginate(15)->withQueryString();
        }

        return view('my.attendances', compact('attendances', 'tab'));
    }

    public function attended(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $attendances = $user->eventAttendances()
            ->attendedPastPublishedEvent()
            ->with('event.user')
            ->orderByDesc('attended_at')
            ->paginate(15);

        return view('my.attended-events', compact('attendances'));
    }
}
