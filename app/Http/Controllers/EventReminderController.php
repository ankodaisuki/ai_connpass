<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReminder;
use App\Models\User;
use App\Services\EventReminderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventReminderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly EventReminderService $reminderService) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('sendReminder', $event);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->reminderService->send($event, $user, $validated['subject'], $validated['body']);

        return redirect()->route('events.show', $event)->with('success', 'リマインドメールを送信しました。');
    }

    public function resend(Event $event, EventReminder $reminder): RedirectResponse
    {
        $this->authorize('sendReminder', $event);

        $this->reminderService->resend($reminder);

        return redirect()->route('events.show', $event)->with('success', '失敗分を再送しました。');
    }
}
