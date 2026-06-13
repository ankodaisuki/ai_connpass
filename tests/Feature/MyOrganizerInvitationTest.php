<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOrganizerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_my_pending_invitations(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $pendingEvent = Event::factory()->create(['title' => 'pending-event']);
        EventOrganizer::factory()->create(['event_id' => $pendingEvent->id, 'user_id' => $me->id]);

        $acceptedEvent = Event::factory()->create(['title' => 'accepted-event']);
        EventOrganizer::factory()->accepted()->create(['event_id' => $acceptedEvent->id, 'user_id' => $me->id]);

        $othersEvent = Event::factory()->create(['title' => 'others-event']);
        EventOrganizer::factory()->create(['event_id' => $othersEvent->id, 'user_id' => $other->id]);

        $response = $this->actingAs($me)->get(route('my.organizer-invitations'));

        $response->assertOk();
        $response->assertSee('pending-event');
        $response->assertDontSee('accepted-event');
        $response->assertDontSee('others-event');
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('my.organizer-invitations'))->assertRedirect(route('login'));
    }
}
