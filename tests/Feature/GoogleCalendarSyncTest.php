<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_creates_calendar_event_when_connected(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();
        GoogleCalendarToken::create(['user_id' => $applicant->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')->once()->andReturn('gcal-event-1');
        });

        $this->actingAs($applicant)->post(route('events.attendances.store', $event));

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'google_calendar_event_id' => 'gcal-event-1',
        ]);
    }

    public function test_apply_succeeds_without_calendar_when_not_connected(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')->never();
        });

        $this->actingAs($applicant)
            ->post(route('events.attendances.store', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Applied->value,
            'google_calendar_event_id' => null,
        ]);
    }

    public function test_apply_succeeds_even_if_calendar_throws(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();
        GoogleCalendarToken::create(['user_id' => $applicant->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')->once()->andThrow(new \RuntimeException('API down'));
        });

        $this->actingAs($applicant)
            ->post(route('events.attendances.store', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Applied->value,
            'google_calendar_event_id' => null,
        ]);
    }

    public function test_cancel_deletes_calendar_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();
        GoogleCalendarToken::create(['user_id' => $applicant->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);
        EventAttendance::factory()->for($event)->for($applicant)->create([
            'google_calendar_event_id' => 'gcal-event-1',
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('deleteEvent')->once()->with(Mockery::type(User::class), 'gcal-event-1');
        });

        $this->actingAs($applicant)
            ->delete(route('events.attendances.destroy', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Cancelled->value,
            'google_calendar_event_id' => null,
        ]);
    }
}
