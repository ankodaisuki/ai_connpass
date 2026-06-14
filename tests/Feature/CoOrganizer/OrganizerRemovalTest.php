<?php

namespace Tests\Feature\CoOrganizer;

use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_remove_co_organizer(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $organizer = EventCoOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('events.organizers.destroy', [$event, $organizer]))
            ->assertRedirect(route('events.show', $event));

        $this->assertDatabaseMissing('event_co_organizers', ['id' => $organizer->id]);
    }

    public function test_co_organizer_cannot_remove_others(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $organizer = EventCoOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($coOrganizer)
            ->delete(route('events.organizers.destroy', [$event, $organizer]))
            ->assertForbidden();

        $this->assertDatabaseHas('event_co_organizers', ['id' => $organizer->id]);
    }

    public function test_cannot_remove_organizer_belonging_to_another_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $otherEvent = Event::factory()->create(['user_id' => $owner->id]);
        $organizer = EventCoOrganizer::factory()->accepted()->create(['event_id' => $otherEvent->id]);

        $this->actingAs($owner)
            ->delete(route('events.organizers.destroy', [$event, $organizer]))
            ->assertNotFound();
    }
}
