<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Calendar\EventDateTime;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient;
        $this->client->setClientId((string) config('services.google.client_id'));
        $this->client->setClientSecret((string) config('services.google.client_secret'));
        $this->client->setHttpClient(new GuzzleClient(['timeout' => 10]));
    }

    /**
     * 連携済みユーザーのカレンダーに予定を作成し、Google予定IDを返す。
     * 未連携・トークン更新失敗時は null を返す（呼び出し側でスキップ）。
     */
    public function createEvent(User $user, Event $event): ?string
    {
        $client = $this->authorizedClient($user);
        if ($client === null) {
            return null;
        }

        $service = new GoogleCalendar($client);
        $googleEvent = new GoogleCalendarEvent([
            'summary' => $event->title,
            'description' => $event->description,
            'location' => trim($event->prefecture.' '.$event->location),
            'start' => new EventDateTime([
                'dateTime' => $event->event_date->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
            'end' => new EventDateTime([
                'dateTime' => $event->end_date->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
        ]);

        return $service->events->insert('primary', $googleEvent)->getId();
    }

    /**
     * 予定を削除する。未連携時は何もしない。
     */
    public function deleteEvent(User $user, string $googleEventId): void
    {
        $client = $this->authorizedClient($user);
        if ($client === null) {
            return;
        }

        (new GoogleCalendar($client))->events->delete('primary', $googleEventId);
    }

    /**
     * アクセストークンを失効させる。失敗してもログのみ。
     */
    public function revoke(User $user): void
    {
        $token = $user->googleCalendarToken;
        if ($token === null) {
            return;
        }

        try {
            $this->client->revokeToken($token->access_token);
        } catch (\Throwable $e) {
            Log::warning('Googleトークンのrevokeに失敗', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 有効なアクセストークンを持つ Google クライアントを返す。
     * 未連携・更新失敗時は null。
     */
    private function authorizedClient(User $user): ?GoogleClient
    {
        $token = $user->googleCalendarToken;
        if ($token === null) {
            return null;
        }

        $accessToken = $token->access_token;

        if ($token->expires_at === null || $token->expires_at->isPast()) {
            if ($token->refresh_token === null) {
                return null;
            }

            $new = $this->client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (isset($new['error'])) {
                Log::warning('Googleトークン更新に失敗', ['user_id' => $user->id, 'error' => $new['error']]);

                return null;
            }

            $accessToken = $new['access_token'];
            $token->update([
                'access_token' => $accessToken,
                'expires_at' => now()->addSeconds((int) ($new['expires_in'] ?? 3600)),
            ]);
        }

        $this->client->setAccessToken(['access_token' => $accessToken]);

        return $this->client;
    }
}
