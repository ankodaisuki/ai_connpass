<?php

namespace Tests\Feature\CoOrganizer;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_invite_and_delete_controls(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertSee('合同主催者を招待');
        $response->assertSee(route('events.organizers.store', $event));
    }

    public function test_co_organizer_does_not_see_invite_or_delete_controls(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $response = $this->actingAs($coOrganizer)->get(route('events.show', $event));

        $response->assertDontSee('合同主催者を招待');
        $response->assertDontSee('この操作は取り消せません');
    }

    public function test_co_organizer_sees_edit_control(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $response = $this->actingAs($coOrganizer)->get(route('events.show', $event));

        $response->assertSee(route('events.edit', $event));
    }

    public function test_co_organizer_does_not_see_delete_button_on_edit_page(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $response = $this->actingAs($coOrganizer)->get(route('events.edit', $event));

        $response->assertOk();
        $response->assertDontSee('このイベントを削除する');
    }

    public function test_owner_sees_delete_button_on_edit_page(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->get(route('events.edit', $event));

        $response->assertOk();
        $response->assertSee('このイベントを削除する');
    }
}
