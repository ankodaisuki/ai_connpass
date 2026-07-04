<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RestoreEventTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_restore_an_admin_deleted_event_as_private(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $event = Event::factory()->create(['status' => EventStatus::Published]);
        app(AdminService::class)->deleteEvent($event, $admin, '規約違反');

        $response = $this->actingAs($admin)
            ->patch(route('admin.events.restore', $event->id), ['reason' => '誤削除のため']);

        $response->assertRedirect(route('admin.events.trashed'));
        $restored = Event::find($event->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertSame(EventStatus::Private, $restored->status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'restore_event',
            'target_type' => 'event',
            'target_id' => $event->id,
            'reason' => '誤削除のため',
        ]);
    }

    public function test_non_admin_cannot_access_restore(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create();
        $event->delete();

        $this->actingAs($user)
            ->patch(route('admin.events.restore', $event->id), ['reason' => 'x'])
            ->assertForbidden();
    }

    public function test_user_cancelled_event_without_admin_log_cannot_be_restored(): void
    {
        // 管理者削除ログの無いソフトデリート済みイベントは復元対象外（404）
        $admin = $this->admin();
        $event = Event::factory()->create();
        $event->delete();

        $this->actingAs($admin)
            ->patch(route('admin.events.restore', $event->id), ['reason' => 'x'])
            ->assertNotFound();
    }
}
