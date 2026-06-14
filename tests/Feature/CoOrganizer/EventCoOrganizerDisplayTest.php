<?php

namespace Tests\Feature\CoOrganizer;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCoOrganizerDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_co_organizer_is_shown_on_public_page(): void
    {
        $owner = User::factory()->create(['name' => 'オーナー花子']);
        $accepted = User::factory()->create(['name' => '承諾ジョン']);
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $accepted->id]);

        $response = $this->get(route('events.show', $event));

        $response->assertSee('オーナー花子');
        $response->assertSee('承諾ジョン');
    }

    public function test_pending_and_declined_are_hidden_on_public_page(): void
    {
        $owner = User::factory()->create();
        $pending = User::factory()->create(['name' => '保留ペンディング']);
        $declined = User::factory()->create(['name' => '辞退デクライン']);
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id]);
        EventCoOrganizer::factory()->declined()->create(['event_id' => $event->id, 'user_id' => $declined->id]);

        $response = $this->get(route('events.show', $event));

        $response->assertDontSee('保留ペンディング');
        $response->assertDontSee('辞退デクライン');
    }
}
