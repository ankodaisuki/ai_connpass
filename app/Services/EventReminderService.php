<?php

namespace App\Services;

use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventReminderService
{
    public function send(Event $event, User $sender, string $subject, string $body): EventReminder
    {
        $appliedAttendances = $event->appliedAttendances()->with('user')->get();

        return DB::transaction(function () use ($event, $sender, $subject, $body, $appliedAttendances) {
            $reminder = EventReminder::create([
                'event_id' => $event->id,
                'sent_by_user_id' => $sender->id,
                'subject' => $subject,
                'body' => $body,
                'total_count' => $appliedAttendances->count(),
            ]);

            $appliedAttendances->each(function (EventAttendance $attendance) use ($reminder) {
                $recipient = EventReminderRecipient::create([
                    'event_reminder_id' => $reminder->id,
                    'user_id' => $attendance->user_id,
                    'email' => $attendance->user->email,
                    'status' => ReminderRecipientStatus::Pending,
                ]);

                SendEventReminderJob::dispatch($recipient);
            });

            return $reminder;
        });
    }

    public function resend(EventReminder $reminder): void
    {
        $failedRecipients = $reminder->recipients()
            ->where('status', ReminderRecipientStatus::Failed)
            ->get();

        DB::transaction(function () use ($reminder, $failedRecipients) {
            $failedRecipients->each(function (EventReminderRecipient $recipient) {
                $recipient->update([
                    'status' => ReminderRecipientStatus::Pending,
                    'error' => null,
                ]);
            });

            $reminder->decrement('failed_count', $failedRecipients->count());
        });

        $failedRecipients->each(function (EventReminderRecipient $recipient) {
            SendEventReminderJob::dispatch($recipient);
        });
    }
}
