<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = User::factory()->admin()->create();
        AdminAuditLog::factory()->create([
            'admin_user_id' => $admin->id,
            'action' => 'freeze',
            'reason' => 'スパム行為',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'));

        $response->assertOk();
        $response->assertSee('スパム行為');
    }

    public function test_non_admin_cannot_view_audit_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
    }
}
