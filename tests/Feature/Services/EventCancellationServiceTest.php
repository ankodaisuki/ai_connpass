<?php

namespace Tests\Feature\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\EventCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_soft_deletes_event_and_notifies_attendees(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
        ]);

        app(EventCancellationService::class)->cancel($event);

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertSame(EventStatus::Private, $event->fresh()->status);
        Mail::assertSent(EventCancelledMail::class);
    }
}
