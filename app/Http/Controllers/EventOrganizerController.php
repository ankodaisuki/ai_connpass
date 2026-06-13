<?php

namespace App\Http\Controllers;

use App\Enums\OrganizerInvitationStatus;
use App\Mail\OrganizerInvitedMail;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EventOrganizerController extends Controller
{
    use AuthorizesRequests;

    /**
     * 合同主催者をメールアドレスで招待する（承認制・Pending で作成）
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('inviteOrganizer', $event);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $invitee = User::query()->where('email', $validated['email'])->first();

        if ($invitee === null) {
            throw ValidationException::withMessages([
                'email' => 'そのメールアドレスのユーザーが見つかりません。',
            ]);
        }

        if ($event->isOwner($invitee)) {
            throw ValidationException::withMessages([
                'email' => 'オーナー自身を合同主催者に招待することはできません。',
            ]);
        }

        if ($event->eventOrganizers()->where('user_id', $invitee->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'このユーザーはすでに招待済みです。',
            ]);
        }

        $event->eventOrganizers()->create([
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
            'invited_at' => now(),
        ]);

        /** @var User $owner */
        $owner = $request->user();

        try {
            Mail::to($invitee->email)->send(new OrganizerInvitedMail($event, $owner));
        } catch (\Throwable $e) {
            Log::warning('合同主催の招待メール送信に失敗', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('events.show', $event)->with('success', '合同主催者を招待しました。');
    }

    /**
     * 被招待者が招待を承諾する
     */
    public function accept(EventOrganizer $eventOrganizer): RedirectResponse
    {
        $this->authorizeResponse($eventOrganizer);

        $eventOrganizer->update([
            'status' => OrganizerInvitationStatus::Accepted,
            'responded_at' => now(),
        ]);

        return redirect()->route('my.organizer-invitations')->with('success', '合同主催の招待を承諾しました。');
    }

    /**
     * 被招待者が招待を辞退する
     */
    public function decline(EventOrganizer $eventOrganizer): RedirectResponse
    {
        $this->authorizeResponse($eventOrganizer);

        $eventOrganizer->update([
            'status' => OrganizerInvitationStatus::Declined,
            'responded_at' => now(),
        ]);

        return redirect()->route('my.organizer-invitations')->with('success', '合同主催の招待を辞退しました。');
    }

    /**
     * 合同主催者を除名する（招待レコードを削除）
     */
    public function destroy(Event $event, EventOrganizer $eventOrganizer): RedirectResponse
    {
        $this->authorize('removeOrganizer', $event);

        $eventOrganizer->delete();

        return redirect()->route('events.show', $event)->with('success', '合同主催者を外しました。');
    }

    /**
     * 招待に応答できるのは「被招待者本人」かつ「Pending のまま」の場合のみ
     */
    private function authorizeResponse(EventOrganizer $eventOrganizer): void
    {
        abort_unless(
            $eventOrganizer->user_id === auth()->id()
                && $eventOrganizer->status === OrganizerInvitationStatus::Pending,
            Response::HTTP_FORBIDDEN,
        );
    }
}
