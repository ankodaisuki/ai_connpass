<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('運営ダッシュボード');
    }

    public function test_dashboard_shows_correct_counts(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();
        EventAttendance::factory()->create([
            'event_id' => $event1->id,
            'status' => AttendanceStatus::Applied,
            'applied_at' => now(),
        ]);
        EventAttendance::factory()->create([
            'event_id' => $event2->id,
            'status' => AttendanceStatus::Cancelled,
            'applied_at' => now(),
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('4'); // admin + 3 users
        $response->assertSee('2'); // 2 events
        $response->assertSee('1'); // 1 Applied attendance
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
