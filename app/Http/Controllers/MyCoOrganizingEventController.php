<?php

namespace App\Http\Controllers;

use App\Enums\OrganizerInvitationStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;

class MyCoOrganizingEventController extends Controller
{
    /**
     * 自分が承諾済み合同主催者として参加しているイベント一覧
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $events = $user->organizerInvitations()
            ->where('status', OrganizerInvitationStatus::Accepted)
            ->with('event.user')
            ->latest('invited_at')
            ->get()
            ->pluck('event')
            ->filter();

        return view('my.co-organizing-events', compact('events'));
    }
}
