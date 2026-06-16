<?php

namespace Tests\Feature\Reminder;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\EventReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderStoreTest extends TestCase
{
    use RefreshDatabase;

    private EventReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventReminderService::class);
    }

    public function test_send_creates_reminder_header_and_dispatches_jobs_for_applied_attendees(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $applied1 = User::factory()->create();
        $applied2 = User::factory()->create();
        $cancelled = User::factory()->create();
        $waitlisted = User::factory()->create();

        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $applied1->id, 'status' => AttendanceStatus::Applied, 'applied_at' => now()]);
        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $applied2->id, 'status' => AttendanceStatus::Applied, 'applied_at' => now()]);
        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $cancelled->id, 'status' => AttendanceStatus::Cancelled, 'applied_at' => now(), 'cancelled_at' => now()]);
        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $waitlisted->id, 'status' => AttendanceStatus::Waitlisted, 'waitlisted_at' => now()]);

        $reminder = $this->service->send($event, $owner, '持ち物のお願い', 'ノートPCを持参してください。');

        $this->assertDatabaseHas('event_reminders', [
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'subject' => '持ち物のお願い',
            'total_count' => 2,
        ]);

        $this->assertDatabaseCount('event_reminder_recipients', 2);

        $this->assertDatabaseHas('event_reminder_recipients', [
            'event_reminder_id' => $reminder->id,
            'user_id' => $applied1->id,
            'status' => ReminderRecipientStatus::Pending->value,
        ]);

        $this->assertDatabaseMissing('event_reminder_recipients', [
            'user_id' => $cancelled->id,
        ]);
        $this->assertDatabaseMissing('event_reminder_recipients', [
            'user_id' => $waitlisted->id,
        ]);

        Queue::assertPushed(SendEventReminderJob::class, 2);
    }

    public function test_send_snapshots_email_at_time_of_send(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $participant = User::factory()->create(['email' => 'before@example.com']);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
            'applied_at' => now(),
        ]);

        $reminder = $this->service->send($event, $owner, '件名', '本文');

        $this->assertDatabaseHas('event_reminder_recipients', [
            'event_reminder_id' => $reminder->id,
            'email' => 'before@example.com',
        ]);
    }

    public function test_owner_can_trigger_send_via_route(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->post(route('events.reminders.store', $event), [
            'subject' => 'テスト件名',
            'body' => 'テスト本文',
        ]);

        $response->assertRedirect(route('events.show', $event));
        $this->assertDatabaseCount('event_reminders', 1);
    }

    public function test_non_organizer_gets_403_when_trying_to_send(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $this->actingAs($other)
            ->post(route('events.reminders.store', $event), ['subject' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }

    public function test_subject_and_body_are_required(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.reminders.store', $event), [])
            ->assertSessionHasErrors(['subject', 'body']);
    }
}
