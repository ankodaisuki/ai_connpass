<?php

namespace Tests\Feature;

use App\Models\GoogleCalendarToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleCalendarConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_redirects_to_google(): void
    {
        $user = User::factory()->create();

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->actingAs($user)
            ->get(route('google.connect'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_callback_stores_token(): void
    {
        $user = User::factory()->create();

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->token = 'access-123';
        $socialiteUser->refreshToken = 'refresh-123';
        $socialiteUser->expiresIn = 3600;
        $socialiteUser->shouldReceive('getEmail')->andReturn('me@example.com');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->actingAs($user)
            ->get(route('google.callback'))
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('google_calendar_tokens', [
            'user_id' => $user->id,
            'google_email' => 'me@example.com',
        ]);
        $this->assertSame('access-123', $user->fresh()->googleCalendarToken->access_token);
    }

    public function test_disconnect_deletes_token(): void
    {
        $user = User::factory()->create();
        GoogleCalendarToken::create(['user_id' => $user->id, 'access_token' => 'a']);

        $this->actingAs($user)
            ->delete(route('google.disconnect'))
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('google_calendar_tokens', ['user_id' => $user->id]);
    }

    public function test_connect_requires_auth(): void
    {
        $this->get(route('google.connect'))->assertRedirect(route('login'));
    }
}
