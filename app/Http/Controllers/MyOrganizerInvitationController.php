<?php

namespace App\Http\Controllers;

use App\Enums\OrganizerInvitationStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;

class MyOrganizerInvitationController extends Controller
{
    /**
     * 自分宛ての保留中（Pending）の合同主催招待一覧
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $invitations = $user->organizerInvitations()
            ->where('status', OrganizerInvitationStatus::Pending)
            ->with('event.user')
            ->latest('invited_at')
            ->get();

        return view('my.organizer-invitations', compact('invitations'));
    }
}
