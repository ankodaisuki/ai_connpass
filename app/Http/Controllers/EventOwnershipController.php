<?php

namespace App\Http\Controllers;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\EventOwnershipService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventOwnershipController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly EventOwnershipService $ownershipService) {}

    /**
     * オーナーを承諾済みの合同主催者へ移譲する
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('transferOwnership', $event);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $isAcceptedCoOrganizer = $event->eventCoOrganizers()
            ->where('user_id', $validated['user_id'])
            ->where('status', OrganizerInvitationStatus::Accepted)
            ->exists();

        if (! $isAcceptedCoOrganizer) {
            throw ValidationException::withMessages([
                'user_id' => '承諾済みの合同主催者にのみオーナーを移譲できます。',
            ]);
        }

        /** @var User $newOwner */
        $newOwner = User::query()->findOrFail($validated['user_id']);

        $this->ownershipService->transferTo($event, $newOwner);

        return redirect()->route('events.show', $event)->with('success', 'オーナーを移譲しました。');
    }
}
