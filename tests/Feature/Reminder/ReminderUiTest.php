<?php

namespace Tests\Feature\Reminder;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\EventReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_reminder_form_on_event_show_page(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertSee('参加者へのリマインド');
        $response->assertSee(route('events.reminders.store', $event));
    }

    public function test_accepted_co_organizer_sees_reminder_form(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $response = $this->actingAs($coOrganizer)->get(route('events.show', $event));

        $response->assertSee('参加者へのリマインド');
    }

    public function test_regular_user_does_not_see_reminder_form(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($other)->get(route('events.show', $event));

        $response->assertDontSee('参加者へのリマインド');
    }

    public function test_owner_sees_delivery_history_with_resend_button_for_failed(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'subject' => '会場変更のお知らせ',
            'sent_count' => 3,
            'failed_count' => 1,
        ]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertSee('配信履歴');
        $response->assertSee('会場変更のお知らせ');
        $response->assertSee(route('events.reminders.resend', [$event, $reminder]));
    }

    public function test_resend_button_not_shown_when_no_failures(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventReminder::factory()->create([
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'sent_count' => 5,
            'failed_count' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertDontSee('失敗分を再送');
    }
}
