<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Mail\AdminEventDeletedMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminEventDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_event(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', $event), ['reason' => 'スパムイベント'])
            ->assertRedirect(route('admin.events.index'));

        $this->assertSoftDeleted('events', ['id' => $event->id]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'delete_event',
            'target_type' => 'event',
            'target_id' => $event->id,
        ]);
    }

    public function test_applied_attendees_are_notified_on_delete(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();
        $attendee = User::factory()->create();
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'status' => AttendanceStatus::Applied,
            'applied_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', $event), ['reason' => 'スパムイベント']);

        Mail::assertSent(AdminEventDeletedMail::class, fn ($mail) => $mail->hasTo($attendee->email));
    }

    public function test_cancelled_attendees_are_not_notified(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();
        $attendee = User::factory()->create();
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'status' => AttendanceStatus::Cancelled,
            'applied_at' => now(),
            'cancelled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', $event), ['reason' => 'スパムイベント']);

        Mail::assertNotSent(AdminEventDeletedMail::class);
    }

    public function test_reason_is_required_to_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', $event), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_non_admin_cannot_delete_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->actingAs($user)
            ->delete(route('admin.events.destroy', $event), ['reason' => 'スパム'])
            ->assertForbidden();
    }
}
