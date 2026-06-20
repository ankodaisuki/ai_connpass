<?php

namespace App\Jobs;

use App\Enums\ReminderRecipientStatus;
use App\Mail\EventReminderMail;
use App\Models\EventReminderRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEventReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly EventReminderRecipient $recipient) {}

    public function handle(): void
    {
        $this->recipient->refresh();

        if ($this->recipient->status === ReminderRecipientStatus::Sent) {
            return;
        }

        Mail::to($this->recipient->email)->send(new EventReminderMail($this->recipient->reminder));

        $this->recipient->update([
            'status' => ReminderRecipientStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->recipient->reminder->increment('sent_count');
    }

    public function failed(\Throwable $exception): void
    {
        $this->recipient->update([
            'status' => ReminderRecipientStatus::Failed,
            'error' => $exception->getMessage(),
        ]);

        $this->recipient->reminder->increment('failed_count');

        Log::error('リマインドメール送信に最終失敗', [
            'recipient_id' => $this->recipient->id,
            'email' => $this->recipient->email,
            'reminder_id' => $this->recipient->event_reminder_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
