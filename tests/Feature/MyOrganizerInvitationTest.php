<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCoOrganizer;
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
        EventCoOrganizer::factory()->create(['event_id' => $pendingEvent->id, 'user_id' => $me->id]);

        $acceptedEvent = Event::factory()->create(['title' => 'accepted-event']);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $acceptedEvent->id, 'user_id' => $me->id]);

        $othersEvent = Event::factory()->create(['title' => 'others-event']);
        EventCoOrganizer::factory()->create(['event_id' => $othersEvent->id, 'user_id' => $other->id]);

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

    public function test_nav_shows_badge_when_pending_invitations_exist(): void
    {
        $me = User::factory()->create();
        $event = Event::factory()->create();
        EventCoOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $me->id]);

        $this->actingAs($me)
            ->get(route('events.index'))
            ->assertSee('合同主催の招待')
            ->assertSee('1');
    }

    public function test_nav_shows_no_badge_when_no_pending_invitations(): void
    {
        $me = User::factory()->create();

        $response = $this->actingAs($me)->get(route('events.index'));

        $response->assertSee('合同主催の招待');
        // バッジ数字（件数）が表示されないことを確認
        $this->assertStringNotContainsString(
            'bg-indigo-600 text-white text-xs font-bold',
            $response->getContent(),
        );
    }
}
