<?php

namespace App\Services;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;

class EventOwnershipService
{
    public function __construct(
        private readonly EventCancellationService $cancellationService,
    ) {}

    /**
     * 退会するユーザーが所有する全イベントを処理する。
     * 承諾済み合同主催者がいれば最も早く招待された1人へ引き継ぎ、
     * いなければイベントを中止扱いにする（黙って消さない）。
     */
    public function handleOwnerDeactivation(User $user): void
    {
        $user->events()->get()->each(function (Event $event): void {
            $this->resolve($event);
        });
    }

    /**
     * オーナーを承諾済みの合同主催者へ手動で移譲する。
     * 旧オーナーは承諾済みの合同主催者として残す。
     */
    public function transferTo(Event $event, User $newOwner): void
    {
        $previousOwnerId = $event->user_id;

        $event->eventOrganizers()->where('user_id', $newOwner->id)->delete();

        $event->update(['user_id' => $newOwner->id]);

        $event->eventOrganizers()->updateOrCreate(
            ['user_id' => $previousOwnerId],
            [
                'status' => OrganizerInvitationStatus::Accepted,
                'invited_at' => now(),
                'responded_at' => now(),
            ],
        );
    }

    private function resolve(Event $event): void
    {
        $successor = $event->eventOrganizers()
            ->where('status', OrganizerInvitationStatus::Accepted)
            ->orderBy('invited_at')
            ->orderBy('id')
            ->first();

        if ($successor instanceof EventOrganizer) {
            $event->update(['user_id' => $successor->user_id]);
            $successor->delete();

            return;
        }

        $this->cancellationService->cancel($event);
    }
}
