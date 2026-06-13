<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrganizerInvitationStatus;
use App\Enums\UserStatus;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OwnerDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ownership_transfers_to_earliest_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $first->id,
            'invited_at' => Carbon::parse('2026-06-01 10:00'),
        ]);
        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $second->id,
            'invited_at' => Carbon::parse('2026-06-05 10:00'),
        ]);

        $owner->update(['status' => UserStatus::Inactive]);

        $this->assertSame($first->id, $event->fresh()->user_id);
        $this->assertDatabaseMissing('event_organizers', [
            'event_id' => $event->id,
            'user_id' => $first->id,
        ]);
        $this->assertNull($event->fresh()->deleted_at);
    }

    public function test_event_is_cancelled_when_no_accepted_co_organizer(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->create([
            'event_id' => $event->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);

        $owner->update(['status' => UserStatus::Inactive]);

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        Mail::assertSent(EventCancelledMail::class);
    }

    public function test_ended_events_are_not_cancelled_on_owner_deactivation(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $owner->id,
            'status' => EventStatus::Published,
            'event_date' => Carbon::yesterday(),
            'end_date' => Carbon::yesterday()->addHours(2),
        ]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);

        $owner->update(['status' => UserStatus::Inactive]);

        $this->assertNull($event->fresh()->deleted_at);
        Mail::assertNotSent(EventCancelledMail::class);
    }
}
