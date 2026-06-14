<?php

namespace Tests\Unit\Models;

use App\Models\GoogleCalendarToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleCalendarTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_are_stored_encrypted(): void
    {
        $user = User::factory()->create();
        GoogleCalendarToken::create([
            'user_id' => $user->id,
            'access_token' => 'plain-access',
            'refresh_token' => 'plain-refresh',
            'expires_at' => now()->addHour(),
            'google_email' => 'me@example.com',
        ]);

        $raw = DB::table('google_calendar_tokens')->where('user_id', $user->id)->first();

        $this->assertNotSame('plain-access', $raw->access_token);
        $this->assertNotSame('plain-refresh', $raw->refresh_token);
        $this->assertSame('plain-access', $user->googleCalendarToken->access_token);
    }

    public function test_has_google_calendar_connected(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasGoogleCalendarConnected());

        GoogleCalendarToken::create([
            'user_id' => $user->id,
            'access_token' => 'a',
        ]);

        $this->assertTrue($user->fresh()->hasGoogleCalendarConnected());
    }
}
