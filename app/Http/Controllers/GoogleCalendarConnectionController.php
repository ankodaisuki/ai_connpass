<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleCalendarConnectionController extends Controller
{
    public function connect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.events'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('profile')->withErrors(['google' => 'Googleカレンダー連携に失敗しました。']);
        }

        /** @var User $user */
        $user = auth()->user();
        $user->googleCalendarToken()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
                'expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
                'google_email' => $googleUser->getEmail(),
            ],
        );

        return redirect()->route('profile')->with('success', 'Googleカレンダーと連携しました。');
    }

    public function disconnect(GoogleCalendarService $service): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $service->revoke($user);
        $user->googleCalendarToken()->delete();

        return redirect()->route('profile')->with('success', 'Googleカレンダー連携を解除しました。');
    }
}
