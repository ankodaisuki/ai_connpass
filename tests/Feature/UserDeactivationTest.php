<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
