<?php

namespace Tests\Feature\My;

use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyCoOrganizingEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_accepted_co_organizing_events(): void
    {
        $me = User::factory()->create();
        $owner = User::factory()->create();

        $accepted = Event::factory()->create(['user_id' => $owner->id, 'title' => 'accepted-event']);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $accepted->id, 'user_id' => $me->id]);

        $pending = Event::factory()->create(['user_id' => $owner->id, 'title' => 'pending-event']);
        EventCoOrganizer::factory()->create(['event_id' => $pending->id, 'user_id' => $me->id]);

        $response = $this->actingAs($me)->get(route('my.co-organizing-events'));

        $response->assertOk();
        $response->assertSee('accepted-event');
        $response->assertDontSee('pending-event');
    }

    public function test_does_not_show_other_users_events(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $owner = User::factory()->create();

        $event = Event::factory()->create(['user_id' => $owner->id, 'title' => 'others-event']);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $other->id]);

        $this->actingAs($me)
            ->get(route('my.co-organizing-events'))
            ->assertDontSee('others-event');
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('my.co-organizing-events'))->assertRedirect(route('login'));
    }
}
