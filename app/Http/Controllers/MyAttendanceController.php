<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class MyAttendanceController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $attendances = $user->eventAttendances()
            ->appliedToPublishedEvent()
            ->with('event.user')
            ->orderBy('applied_at', 'asc')
            ->paginate(15);

        return view('my.attendances', compact('attendances'));
    }
}
