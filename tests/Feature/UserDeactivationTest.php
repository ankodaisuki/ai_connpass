<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UserDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_applied_attendances_are_cancelled_when_user_becomes_inactive(): void
    {
        $organizer = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);

        $attendance = EventAttendance::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $user->update(['status' => UserStatus::Inactive]);

        $this->assertEquals(AttendanceStatus::Cancelled, $attendance->fresh()->status);
        $this->assertNotNull($attendance->fresh()->cancelled_at);
    }

    public function test_already_cancelled_attendances_are_not_affected(): void
    {
        $organizer = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);

        $attendance = EventAttendance::factory()->cancelled()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $cancelledAt = $attendance->cancelled_at;

        $user->update(['status' => UserStatus::Inactive]);

        $this->assertEquals($cancelledAt, $attendance->fresh()->cancelled_at);
    }

    public function test_withdrawal_via_profile_cancels_attendances(): void
    {
        $organizer = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);

        $attendance = EventAttendance::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $this->actingAs($user)->delete(route('profile.destroy'));

        $this->assertEquals(AttendanceStatus::Cancelled, $attendance->fresh()->status);
    }

    public function test_event_attendance_count_excludes_cancelled_attendances(): void
    {
        $organizer = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);

        EventAttendance::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $user->update(['status' => UserStatus::Inactive]);

        $response = $this->get(route('events.show', $event));

        $response->assertSee('0 / ');
    }

    public function test_google_calendar_events_are_deleted_when_user_becomes_inactive(): void
    {
        $organizer = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);
        GoogleCalendarToken::create(['user_id' => $user->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);

        $attendance = EventAttendance::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => AttendanceStatus::Applied,
            'google_calendar_event_id' => 'gcal-event-1',
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock): void {
            $mock->shouldReceive('deleteEvent')->once()->with(Mockery::type(User::class), 'gcal-event-1');
            $mock->shouldReceive('revoke')->once();
        });

        $user->update(['status' => UserStatus::Inactive]);

        $this->assertNull($attendance->fresh()->google_calendar_event_id);
    }

    public function test_google_calendar_token_is_revoked_and_deleted_when_user_becomes_inactive(): void
    {
        $organizer = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);
        GoogleCalendarToken::create(['user_id' => $user->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);

        EventAttendance::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => AttendanceStatus::Applied,
            'google_calendar_event_id' => 'gcal-event-1',
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock): void {
            $mock->shouldReceive('deleteEvent')->once();
            $mock->shouldReceive('revoke')->once();
        });

        $user->update(['status' => UserStatus::Inactive]);

        $this->assertDatabaseMissing('google_calendar_tokens', ['user_id' => $user->id]);
    }

    public function test_calendar_cleanup_is_skipped_when_user_is_not_connected(): void
    {
        $organizer = User::factory()->create();
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);

        EventAttendance::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock): void {
            $mock->shouldReceive('deleteEvent')->never();
            $mock->shouldReceive('revoke')->never();
        });

        $user->update(['status' => UserStatus::Inactive]);

        $this->assertEquals(AttendanceStatus::Cancelled, EventAttendance::where('user_id', $user->id)->first()->status);
    }
}
