<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_belongs_to_event(): void
    {
        $reminder = EventReminder::factory()->create();

        $this->assertInstanceOf(Event::class, $reminder->event);
    }

    public function test_reminder_belongs_to_sender(): void
    {
        $reminder = EventReminder::factory()->create();

        $this->assertInstanceOf(User::class, $reminder->sentBy);
    }

    public function test_reminder_has_many_recipients(): void
    {
        $reminder = EventReminder::factory()->create();
        EventReminderRecipient::factory()->count(3)->create(['event_reminder_id' => $reminder->id]);

        $this->assertCount(3, $reminder->recipients);
    }
}
