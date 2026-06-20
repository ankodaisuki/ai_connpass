<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $this->post(route('register'), [
            'email' => 'new@example.com',
            'name' => 'テストユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('events.index'));

        $this->assertAuthenticated();
    }

    public function test_inactive_user_can_re_register_with_same_email(): void
    {
        $inactive = User::factory()->create([
            'email' => 'returning@example.com',
            'status' => UserStatus::Inactive,
        ]);

        $this->post(route('register'), [
            'email' => 'returning@example.com',
            'name' => '復帰ユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('events.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'id' => $inactive->id,
            'name' => '復帰ユーザー',
            'status' => UserStatus::Active->value,
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_active_user_email_cannot_be_re_registered(): void
    {
        User::factory()->create(['email' => 'active@example.com']);

        $this->post(route('register'), [
            'email' => 'active@example.com',
            'name' => '別ユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_frozen_user_email_cannot_be_re_registered(): void
    {
        User::factory()->frozen('規約違反')->create(['email' => 'frozen@example.com']);

        $this->post(route('register'), [
            'email' => 'frozen@example.com',
            'name' => '別ユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }
}
