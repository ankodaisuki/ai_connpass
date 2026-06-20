<?php

namespace Tests\Feature\Reminder;

use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Mail\EventReminderMail;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_mail_and_marks_recipient_as_sent(): void
    {
        Mail::fake();
        $reminder = EventReminder::factory()->create(['total_count' => 1, 'sent_count' => 0]);
        $recipient = EventReminderRecipient::factory()->create([
            'event_reminder_id' => $reminder->id,
            'status' => ReminderRecipientStatus::Pending,
        ]);

        (new SendEventReminderJob($recipient))->handle();

        Mail::assertSent(EventReminderMail::class, function ($mail) use ($recipient) {
            return $mail->hasTo($recipient->email);
        });

        $recipient->refresh();
        $this->assertSame(ReminderRecipientStatus::Sent, $recipient->status);
        $this->assertNotNull($recipient->sent_at);

        $reminder->refresh();
        $this->assertSame(1, $reminder->sent_count);
    }

    public function test_job_is_idempotent_for_already_sent_recipient(): void
    {
        Mail::fake();
        $reminder = EventReminder::factory()->create(['sent_count' => 1]);
        $recipient = EventReminderRecipient::factory()->sent()->create([
            'event_reminder_id' => $reminder->id,
        ]);

        (new SendEventReminderJob($recipient))->handle();

        Mail::assertNothingSent();
        $reminder->refresh();
        $this->assertSame(1, $reminder->sent_count);
    }

    public function test_job_records_failure_on_final_failure(): void
    {
        $reminder = EventReminder::factory()->create(['total_count' => 1]);
        $recipient = EventReminderRecipient::factory()->create([
            'event_reminder_id' => $reminder->id,
            'status' => ReminderRecipientStatus::Pending,
        ]);

        $exception = new \RuntimeException('接続タイムアウト');
        (new SendEventReminderJob($recipient))->failed($exception);

        $recipient->refresh();
        $this->assertSame(ReminderRecipientStatus::Failed, $recipient->status);
        $this->assertStringContainsString('接続タイムアウト', $recipient->error);

        $reminder->refresh();
        $this->assertSame(1, $reminder->failed_count);
    }
}
