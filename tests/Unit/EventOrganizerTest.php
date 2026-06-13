<?php

namespace Tests\Unit;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOrganizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_event_and_user(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        $organizer = EventOrganizer::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($organizer->event->is($event));
        $this->assertTrue($organizer->user->is($user));
    }

    public function test_status_is_cast_to_enum(): void
    {
        $organizer = EventOrganizer::factory()->create([
            'status' => OrganizerInvitationStatus::Accepted,
        ]);

        $this->assertSame(OrganizerInvitationStatus::Accepted, $organizer->fresh()->status);
    }

    public function test_factory_accepted_state(): void
    {
        $organizer = EventOrganizer::factory()->accepted()->create();

        $this->assertSame(OrganizerInvitationStatus::Accepted, $organizer->status);
        $this->assertNotNull($organizer->responded_at);
    }
}
