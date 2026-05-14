<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_withdraw(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('profile.destroy'));

        $response->assertRedirect(route('events.index'));
        $this->assertEquals(UserStatus::Inactive, $user->fresh()->status);
        $this->assertGuest();
    }

    public function test_guest_cannot_access_withdrawal(): void
    {
        $response = $this->delete(route('profile.destroy'));

        $response->assertRedirect(route('login'));
    }
}
