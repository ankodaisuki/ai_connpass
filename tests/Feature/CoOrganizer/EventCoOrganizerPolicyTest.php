<?php

namespace Tests\Feature\CoOrganizer;

use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCoOrganizerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): array
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        return [$event, $owner, $coOrganizer, $stranger];
    }

    public function test_update_allowed_for_owner_and_accepted_co_organizer(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        $this->assertTrue($owner->can('update', $event));
        $this->assertTrue($coOrganizer->can('update', $event));
        $this->assertFalse($stranger->can('update', $event));
    }

    public function test_update_attendance_allowed_for_owner_and_accepted_co_organizer(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        $this->assertTrue($owner->can('updateAttendance', $event));
        $this->assertTrue($coOrganizer->can('updateAttendance', $event));
        $this->assertFalse($stranger->can('updateAttendance', $event));
    }

    public function test_delete_allowed_for_owner_only(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        $this->assertTrue($owner->can('delete', $event));
        $this->assertFalse($coOrganizer->can('delete', $event));
        $this->assertFalse($stranger->can('delete', $event));
    }

    public function test_invite_remove_transfer_allowed_for_owner_only(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        foreach (['inviteOrganizer', 'removeOrganizer', 'transferOwnership'] as $ability) {
            $this->assertTrue($owner->can($ability, $event), $ability);
            $this->assertFalse($coOrganizer->can($ability, $event), $ability);
            $this->assertFalse($stranger->can($ability, $event), $ability);
        }
    }
}
