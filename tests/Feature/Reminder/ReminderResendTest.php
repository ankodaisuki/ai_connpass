<?php

namespace Tests\Feature\Reminder;

use App\Enums\EventStatus;
use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Models\Event;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_dispatches_jobs_only_for_failed_recipients(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'failed_count' => 2,
        ]);

        $failed1 = EventReminderRecipient::factory()->failed()->create(['event_reminder_id' => $reminder->id]);
        $failed2 = EventReminderRecipient::factory()->failed()->create(['event_reminder_id' => $reminder->id]);
        $sent = EventReminderRecipient::factory()->sent()->create(['event_reminder_id' => $reminder->id]);

        $this->actingAs($owner)
            ->post(route('events.reminders.resend', [$event, $reminder]))
            ->assertRedirect(route('events.show', $event));

        Queue::assertPushed(SendEventReminderJob::class, 2);

        $failed1->refresh();
        $this->assertSame(ReminderRecipientStatus::Pending, $failed1->status);
        $this->assertNull($failed1->error);

        $sent->refresh();
        $this->assertSame(ReminderRecipientStatus::Sent, $sent->status);

        $reminder->refresh();
        $this->assertSame(0, $reminder->failed_count);
    }

    public function test_non_organizer_cannot_resend(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create(['event_id' => $event->id, 'sent_by_user_id' => $owner->id]);

        $this->actingAs($other)
            ->post(route('events.reminders.resend', [$event, $reminder]))
            ->assertForbidden();
    }

    public function test_cannot_resend_reminder_belonging_to_different_event(): void
    {
        $owner = User::factory()->create();
        $event1 = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $event2 = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create(['event_id' => $event2->id, 'sent_by_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('events.reminders.resend', [$event1, $reminder]))
            ->assertNotFound();
    }
}
