<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CoOrganizerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_co_organizer_can_record_attendance(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $owner->id,
            'status' => EventStatus::Published,
            'event_date' => Carbon::yesterday(),
            'end_date' => Carbon::tomorrow(),
        ]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);
        $attendance = EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $this->actingAs($coOrganizer)
            ->patch(route('events.attendances.update', [$event, $attendance]))
            ->assertRedirect();

        $this->assertNotNull($attendance->fresh()->attended_at);
    }

    public function test_co_organizer_sees_attendee_list(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $participant = User::factory()->create(['name' => '参加者サム']);
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $this->actingAs($coOrganizer)
            ->get(route('events.show', $event))
            ->assertSee('参加者サム');
    }
}
