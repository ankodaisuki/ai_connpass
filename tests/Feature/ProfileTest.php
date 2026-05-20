<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /** 参加申し込み数は Applied かつ公開中イベントのみカウントする */
    public function test_attendance_count_only_includes_applied_published_events(): void
    {
        $user = User::factory()->create();
        $organizer = User::factory()->create();

        $published = Event::factory()->for($organizer)->create(['status' => EventStatus::Published]);
        $private = Event::factory()->for($organizer)->create(['status' => EventStatus::Private]);
        $draft = Event::factory()->for($organizer)->create(['status' => EventStatus::Draft]);
        $cancelledEvent = Event::factory()->for($organizer)->create(['status' => EventStatus::Published]);

        EventAttendance::factory()->for($published)->for($user)->create();
        EventAttendance::factory()->for($private)->for($user)->create();
        EventAttendance::factory()->for($draft)->for($user)->create();
        EventAttendance::factory()->for($cancelledEvent)->for($user)->cancelled()->create();

        $response = $this->actingAs($user)->get(route('profile'));

        $response->assertStatus(200);
        $this->assertSame(1, $response->viewData('attendanceCount'));
    }
}
