<?php

namespace Tests\Feature\Reminder;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_send_reminder(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $this->assertTrue($owner->can('sendReminder', $event));
    }

    public function test_accepted_co_organizer_can_send_reminder(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $this->assertTrue($coOrganizer->can('sendReminder', $event));
    }

    public function test_pending_co_organizer_cannot_send_reminder(): void
    {
        $owner = User::factory()->create();
        $pendingUser = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventCoOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pendingUser->id]);

        $this->assertFalse($pendingUser->can('sendReminder', $event));
    }

    public function test_regular_user_cannot_send_reminder(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($other->can('sendReminder', $event));
    }

    public function test_unauthenticated_user_cannot_send_reminder_via_route(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);

        $this->post(route('events.reminders.store', $event), [
            'subject' => 'テスト',
            'body' => '本文',
        ])->assertRedirect(route('login'));
    }
}
