<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOrganizerRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_co_organizers_only_includes_accepted(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->create();
        $pending = User::factory()->create();

        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $accepted->id]);
        EventOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id]);

        $ids = $event->acceptedCoOrganizers->pluck('id');

        $this->assertTrue($ids->contains($accepted->id));
        $this->assertFalse($ids->contains($pending->id));
    }

    public function test_is_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($event->isOwner($owner));
        $this->assertFalse($event->isOwner(User::factory()->create()));
    }

    public function test_is_organizer_includes_owner_and_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $pending = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);
        EventOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id]);

        $this->assertTrue($event->isOrganizer($owner));
        $this->assertTrue($event->isOrganizer($coOrganizer));
        $this->assertFalse($event->isOrganizer($pending));
        $this->assertFalse($event->isAcceptedCoOrganizer($owner));
    }
}
