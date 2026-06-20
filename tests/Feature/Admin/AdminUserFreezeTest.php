<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserFreezeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_freeze_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.freeze', $target), ['reason' => 'スパム行為'])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame(UserStatus::Frozen, $target->status);
        $this->assertSame('スパム行為', $target->frozen_reason);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'freeze',
            'target_type' => 'user',
            'target_id' => $target->id,
        ]);
    }

    public function test_admin_can_unfreeze_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->frozen('スパム行為')->create();

        $this->actingAs($admin)
            ->post(route('admin.users.unfreeze', $target), ['reason' => '確認済み、誤判定'])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame(UserStatus::Active, $target->status);
        $this->assertNull($target->frozen_reason);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'unfreeze',
            'target_type' => 'user',
            'target_id' => $target->id,
        ]);
    }

    public function test_frozen_user_cannot_login(): void
    {
        User::factory()->frozen('スパム行為のため')->create(['email' => 'frozen@example.com', 'password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => 'frozen@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_frozen_login_error_shows_reason(): void
    {
        User::factory()->frozen('規約違反')->create(['email' => 'frozen@example.com', 'password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => 'frozen@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('規約違反', session('errors')->first('email'));
    }

    public function test_reason_is_required_to_freeze(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.freeze', $target), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_non_admin_cannot_freeze(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.users.freeze', $target), ['reason' => 'スパム'])
            ->assertForbidden();
    }

    public function test_frozen_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile'))->assertOk();

        $user->update(['status' => UserStatus::Frozen, 'frozen_reason' => '規約違反']);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
