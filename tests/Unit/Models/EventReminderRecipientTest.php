<?php

namespace Tests\Unit\Models;

use App\Enums\ReminderRecipientStatus;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventReminderRecipientTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_belongs_to_reminder(): void
    {
        $recipient = EventReminderRecipient::factory()->create();

        $this->assertInstanceOf(EventReminder::class, $recipient->reminder);
    }

    public function test_recipient_status_is_cast_to_enum(): void
    {
        $recipient = EventReminderRecipient::factory()->create(['status' => ReminderRecipientStatus::Pending]);

        $this->assertSame(ReminderRecipientStatus::Pending, $recipient->status);
    }

    public function test_recipient_sent_at_is_cast_to_datetime(): void
    {
        $recipient = EventReminderRecipient::factory()->create(['sent_at' => now()]);

        $this->assertInstanceOf(Carbon::class, $recipient->sent_at);
    }
}
