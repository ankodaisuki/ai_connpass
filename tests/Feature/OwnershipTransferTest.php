<?php

namespace Tests\Feature;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_transfer_to_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($owner)
            ->patch(route('events.ownership.update', $event), ['user_id' => $coOrganizer->id])
            ->assertRedirect(route('events.show', $event));

        $this->assertSame($coOrganizer->id, $event->fresh()->user_id);
        $this->assertDatabaseMissing('event_organizers', [
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);
        $this->assertDatabaseHas('event_organizers', [
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'status' => OrganizerInvitationStatus::Accepted->value,
        ]);
    }

    public function test_cannot_transfer_to_non_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $pending = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->create([
            'event_id' => $event->id,
            'user_id' => $pending->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->patch(route('events.ownership.update', $event), ['user_id' => $pending->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame($owner->id, $event->fresh()->user_id);
    }

    public function test_co_organizer_cannot_transfer_ownership(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($coOrganizer)
            ->patch(route('events.ownership.update', $event), ['user_id' => $coOrganizer->id])
            ->assertForbidden();
    }
}
