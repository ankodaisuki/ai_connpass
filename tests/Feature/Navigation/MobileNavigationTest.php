<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_mobile_menu_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('events.index'));

        $response->assertStatus(200);
        $response->assertSee(route('profile'));
        $response->assertSee(route('my.attendances'));
        $response->assertSee(route('logout'));
    }

    public function test_guest_sees_login_and_register_links(): void
    {
        $response = $this->get(route('events.index'));

        $response->assertStatus(200);
        $response->assertSee(route('login'));
        $response->assertSee(route('register'));
    }
}
