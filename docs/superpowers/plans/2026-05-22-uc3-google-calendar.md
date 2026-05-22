# UC3 Google カレンダー連携 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (推奨) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 参加者が連携した Google アカウントのカレンダーへ、イベント申込時に予定を自動作成し、キャンセル時に削除できるようにする。

**Architecture:** laravel/socialite で各ユーザーの OAuth 同意・トークン取得、google/apiclient で Calendar API を呼ぶ。トークンは専用テーブルに暗号化保存。申込/キャンセル時に `EventAttendanceService` から `GoogleCalendarService` をベストエフォート（try/catch・同期）で呼び、外部連携が失敗しても申込/キャンセルは必ず成立させる。

**Tech Stack:** Laravel 13 / PHP 8.4 / laravel/socialite / google/apiclient / PHPUnit / MySQL（テストは SQLite インメモリ）

設計書: `docs/superpowers/specs/2026-05-22-uc3-google-calendar-design.md`

---

## ファイル構成

| ファイル | 役割 |
|---|---|
| `config/services.php`（変更） | `google`（client_id / secret / redirect）設定 |
| `.env.example`（変更） | `GOOGLE_*` プレースホルダ |
| `database/migrations/*_create_google_calendar_tokens_table.php`（新規） | トークンテーブル |
| `database/migrations/*_add_google_calendar_event_id_to_event_attendances.php`（新規） | 予定IDカラム |
| `app/Models/GoogleCalendarToken.php`（新規） | トークンモデル（暗号化キャスト） |
| `app/Models/User.php`（変更） | `googleCalendarToken()` / `hasGoogleCalendarConnected()` |
| `app/Models/EventAttendance.php`（変更） | Fillable に `google_calendar_event_id` |
| `app/Services/GoogleCalendarService.php`（新規） | Calendar API ラッパ（create/delete/refresh/revoke） |
| `app/Http/Controllers/GoogleCalendarConnectionController.php`（新規） | connect / callback / disconnect |
| `routes/web.php`（変更） | `/google/connect` `/google/callback` `/google/disconnect` |
| `app/Services/EventAttendanceService.php`（変更） | apply/cancel からカレンダー同期をベストエフォート呼び出し |
| `resources/views/profile/show.blade.php`（変更） | 連携セクション |
| `tests/Unit/GoogleCalendarTokenTest.php`（新規） | トークン暗号化・リレーション |
| `tests/Feature/GoogleCalendarConnectionTest.php`（新規） | connect/callback/disconnect |
| `tests/Feature/GoogleCalendarSyncTest.php`（新規） | apply/cancel のカレンダー同期 |

---

## Task 1: 依存追加と Google 設定

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: 依存パッケージを追加**

```bash
composer require laravel/socialite google/apiclient --no-interaction
```
Expected: `laravel/socialite` と `google/apiclient` が `composer.json` の require に追加され、インストール成功。

- [ ] **Step 2: `config/services.php` に google を追加**

`config/services.php` の `return [` 配列内（末尾の `];` の直前）に追記：

```php
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
```

- [ ] **Step 3: `.env.example` に Google 変数を追加**

`.env.example` の末尾に追記：

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/google/callback"
```

- [ ] **Step 4: 設定が読めることを確認**

```bash
php artisan config:clear && php artisan config:show services.google
```
Expected: `client_id` / `client_secret` / `redirect` のキーが表示される（値は空でよい）。

- [ ] **Step 5: コミット**

```bash
git add composer.json composer.lock config/services.php .env.example
git commit -m "chore: socialite/google-api-client追加とgoogle設定"
```

---

## Task 2: マイグレーション（トークンテーブル・予定IDカラム）

**Files:**
- Create: `database/migrations/*_create_google_calendar_tokens_table.php`
- Create: `database/migrations/*_add_google_calendar_event_id_to_event_attendances.php`
- Modify: `app/Models/EventAttendance.php`

- [ ] **Step 1: トークンテーブルのマイグレーションを作成**

```bash
php artisan make:migration create_google_calendar_tokens_table --no-interaction
```

- [ ] **Step 2: トークンテーブルの定義を記述**

生成された `*_create_google_calendar_tokens_table.php` の `up`/`down` を以下に置換：

```php
    public function up(): void
    {
        Schema::create('google_calendar_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('google_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_tokens');
    }
```

- [ ] **Step 3: 予定IDカラムのマイグレーションを作成**

```bash
php artisan make:migration add_google_calendar_event_id_to_event_attendances --no-interaction
```

- [ ] **Step 4: 予定IDカラムの定義を記述**

生成された `*_add_google_calendar_event_id_to_event_attendances.php` の `up`/`down` を以下に置換：

```php
    public function up(): void
    {
        Schema::table('event_attendances', function (Blueprint $table) {
            $table->string('google_calendar_event_id')->nullable()->after('attended_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendances', function (Blueprint $table) {
            $table->dropColumn('google_calendar_event_id');
        });
    }
```

- [ ] **Step 5: `EventAttendance` の Fillable に追加**

`app/Models/EventAttendance.php` の `#[Fillable([...])]` 配列に `'google_calendar_event_id',` を追加（`'attended_at',` の次の行）：

```php
#[Fillable([
    'event_id',
    'user_id',
    'status',
    'applied_at',
    'cancelled_at',
    'attended_at',
    'google_calendar_event_id',
])]
```

- [ ] **Step 6: マイグレーションを実行**

```bash
./vendor/bin/sail artisan migrate
```
Expected: 2 件の migration が DONE。

- [ ] **Step 7: コミット**

```bash
git add database/migrations app/Models/EventAttendance.php
git commit -m "feat: googleカレンダートークンテーブルと予定IDカラムを追加"
```

---

## Task 3: GoogleCalendarToken モデルと User リレーション

**Files:**
- Create: `app/Models/GoogleCalendarToken.php`
- Modify: `app/Models/User.php`
- Test: `tests/Unit/GoogleCalendarTokenTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Unit/GoogleCalendarTokenTest.php` を作成：

```php
<?php

namespace Tests\Unit;

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
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
php artisan test --compact --filter=GoogleCalendarTokenTest
```
Expected: FAIL（`GoogleCalendarToken` クラスが無い）。

- [ ] **Step 3: モデルを作成**

`app/Models/GoogleCalendarToken.php` を作成：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'access_token',
    'refresh_token',
    'expires_at',
    'google_email',
])]
class GoogleCalendarToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: User にリレーションとヘルパを追加**

`app/Models/User.php` の `eventAttendances()` メソッドの後に追記：

```php
    /**
     * @return HasOne<GoogleCalendarToken, $this>
     */
    public function googleCalendarToken(): HasOne
    {
        return $this->hasOne(GoogleCalendarToken::class);
    }

    public function hasGoogleCalendarConnected(): bool
    {
        return $this->googleCalendarToken()->exists();
    }
```

ファイル冒頭の `use` に追加（`HasMany` の import 付近）：

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

- [ ] **Step 5: テストが通ることを確認**

```bash
php artisan test --compact --filter=GoogleCalendarTokenTest
```
Expected: PASS（2 件）。

- [ ] **Step 6: コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models tests/Unit/GoogleCalendarTokenTest.php
git commit -m "feat: GoogleCalendarTokenモデルとUserリレーションを追加"
```

---

## Task 4: GoogleCalendarService（Calendar API ラッパ）

**Files:**
- Create: `app/Services/GoogleCalendarService.php`

このサービスの実 API 呼び出しは実 Google 接続が必要なため、ユニットテストでは検証しない（後続タスクの Feature テストではモックする／本番の Google Cloud 設定後に手動検証）。本タスクは実装のみ。

- [ ] **Step 1: サービスを実装**

`app/Services/GoogleCalendarService.php` を作成：

```php
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
```

- [ ] **Step 2: 静的解析的に問題ないことを確認（autoload + 構文）**

```bash
php -l app/Services/GoogleCalendarService.php
```
Expected: `No syntax errors detected`。

- [ ] **Step 3: コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/GoogleCalendarService.php
git commit -m "feat: GoogleCalendarServiceを追加（予定の作成/削除/トークン更新）"
```

---

## Task 5: OAuth 連携コントローラとルート

**Files:**
- Create: `app/Http/Controllers/GoogleCalendarConnectionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/GoogleCalendarConnectionTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/GoogleCalendarConnectionTest.php` を作成：

```php
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
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
php artisan test --compact --filter=GoogleCalendarConnectionTest
```
Expected: FAIL（ルート `google.connect` 等が無い）。

- [ ] **Step 3: コントローラを作成**

`app/Http/Controllers/GoogleCalendarConnectionController.php` を作成：

```php
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
```

- [ ] **Step 4: ルートを追加**

`routes/web.php` の `auth` ミドルウェアグループ内（`my/attended-events` 行の後など）に追記。冒頭の `use` に `use App\Http\Controllers\GoogleCalendarConnectionController;` を追加：

```php
    Route::get('google/connect', [GoogleCalendarConnectionController::class, 'connect'])->name('google.connect');
    Route::get('google/callback', [GoogleCalendarConnectionController::class, 'callback'])->name('google.callback');
    Route::delete('google/disconnect', [GoogleCalendarConnectionController::class, 'disconnect'])->name('google.disconnect');
```

- [ ] **Step 5: テストが通ることを確認**

```bash
php artisan test --compact --filter=GoogleCalendarConnectionTest
```
Expected: PASS（4 件）。

- [ ] **Step 6: コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/GoogleCalendarConnectionController.php routes/web.php tests/Feature/GoogleCalendarConnectionTest.php
git commit -m "feat: Googleカレンダー連携のconnect/callback/disconnectを追加"
```

---

## Task 6: 申込/キャンセル時のカレンダー同期（ベストエフォート）

**Files:**
- Modify: `app/Services/EventAttendanceService.php`
- Test: `tests/Feature/GoogleCalendarSyncTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/GoogleCalendarSyncTest.php` を作成：

```php
<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_creates_calendar_event_when_connected(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();
        GoogleCalendarToken::create(['user_id' => $applicant->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')->once()->andReturn('gcal-event-1');
        });

        $this->actingAs($applicant)->post(route('events.attendances.store', $event));

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'google_calendar_event_id' => 'gcal-event-1',
        ]);
    }

    public function test_apply_succeeds_without_calendar_when_not_connected(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')->never();
        });

        $this->actingAs($applicant)
            ->post(route('events.attendances.store', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Applied->value,
            'google_calendar_event_id' => null,
        ]);
    }

    public function test_apply_succeeds_even_if_calendar_throws(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();
        GoogleCalendarToken::create(['user_id' => $applicant->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('createEvent')->once()->andThrow(new \RuntimeException('API down'));
        });

        $this->actingAs($applicant)
            ->post(route('events.attendances.store', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Applied->value,
            'google_calendar_event_id' => null,
        ]);
    }

    public function test_cancel_deletes_calendar_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHours(2),
        ]);
        $applicant = User::factory()->create();
        GoogleCalendarToken::create(['user_id' => $applicant->id, 'access_token' => 'a', 'expires_at' => now()->addHour()]);
        EventAttendance::factory()->for($event)->for($applicant)->create([
            'google_calendar_event_id' => 'gcal-event-1',
        ]);

        $this->mock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('deleteEvent')->once()->with(Mockery::type(User::class), 'gcal-event-1');
        });

        $this->actingAs($applicant)
            ->delete(route('events.attendances.destroy', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Cancelled->value,
            'google_calendar_event_id' => null,
        ]);
    }
}
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
php artisan test --compact --filter=GoogleCalendarSyncTest
```
Expected: FAIL（同期処理が未実装で `google_calendar_event_id` が保存されない）。

- [ ] **Step 3: EventAttendanceService に同期処理を追加**

`app/Services/EventAttendanceService.php` を編集。

冒頭 `use` に追加：

```php
use Illuminate\Support\Facades\Log;
```

コンストラクタを追加（`class EventAttendanceService` 直下、最初のメソッドの前）：

```php
    public function __construct(private readonly GoogleCalendarService $googleCalendarService) {}
```

`use` に `use App\Services\GoogleCalendarService;` ……は同一 namespace（`App\Services`）なので不要。`GoogleCalendarService` をそのまま参照できる。

`apply()` 内、参加レコードを作成/更新している分岐を、作成したレコードを変数に保持する形に変更し、最後にカレンダー同期を呼ぶ。`apply()` の末尾（`if ($existing !== null) { ... } else { ... }` ブロック）を以下に置換：

```php
        if ($existing !== null) {
            $existing->update([
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
                'cancelled_at' => null,
            ]);
            $attendance = $existing;
        } else {
            $attendance = EventAttendance::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
            ]);
        }

        $this->syncCalendarOnApply($event, $user, $attendance);
```

`cancel()` 内、`$attendance->update([...Cancelled...])` の後に同期削除を追加：

```php
        $attendance->update([
            'status' => AttendanceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->syncCalendarOnCancel($user, $attendance);
```

クラス末尾（最後の `}` の前）に private メソッドを追加：

```php
    private function syncCalendarOnApply(Event $event, User $user, EventAttendance $attendance): void
    {
        if (! $user->hasGoogleCalendarConnected()) {
            return;
        }

        try {
            $googleEventId = $this->googleCalendarService->createEvent($user, $event);
            if ($googleEventId !== null) {
                $attendance->update(['google_calendar_event_id' => $googleEventId]);
            }
        } catch (\Throwable $e) {
            Log::warning('Googleカレンダー登録に失敗', ['user_id' => $user->id, 'event_id' => $event->id, 'error' => $e->getMessage()]);
        }
    }

    private function syncCalendarOnCancel(User $user, EventAttendance $attendance): void
    {
        $googleEventId = $attendance->google_calendar_event_id;
        if ($googleEventId === null || ! $user->hasGoogleCalendarConnected()) {
            return;
        }

        try {
            $this->googleCalendarService->deleteEvent($user, $googleEventId);
            $attendance->update(['google_calendar_event_id' => null]);
        } catch (\Throwable $e) {
            Log::warning('Googleカレンダー削除に失敗', ['user_id' => $user->id, 'attendance_id' => $attendance->id, 'error' => $e->getMessage()]);
        }
    }
```

- [ ] **Step 4: テストが通ることを確認**

```bash
php artisan test --compact --filter=GoogleCalendarSyncTest
```
Expected: PASS（4 件）。

- [ ] **Step 5: 既存テストの非破壊を確認**

```bash
php artisan test --compact
```
Expected: 全件 PASS。

- [ ] **Step 6: コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/EventAttendanceService.php tests/Feature/GoogleCalendarSyncTest.php
git commit -m "feat: 申込/キャンセル時にGoogleカレンダーをベストエフォート同期"
```

---

## Task 7: プロフィールに連携 UI を追加

**Files:**
- Modify: `app/Http/Controllers/ProfileController.php`
- Modify: `resources/views/profile/show.blade.php`
- Test: `tests/Feature/ProfileTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/ProfileTest.php` に以下のテストメソッドを追加（クラス内、最後の `}` の前）。冒頭 `use` に `use App\Models\GoogleCalendarToken;` を追加：

```php
    public function test_profile_shows_connect_button_when_not_connected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertSee('Googleカレンダー連携')
            ->assertSee('連携する');
    }

    public function test_profile_shows_disconnect_when_connected(): void
    {
        $user = User::factory()->create();
        GoogleCalendarToken::create([
            'user_id' => $user->id,
            'access_token' => 'a',
            'google_email' => 'me@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertSee('me@example.com')
            ->assertSee('連携を解除する');
    }
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
php artisan test --compact --filter=ProfileTest
```
Expected: FAIL（連携セクションが無い）。

- [ ] **Step 3: ProfileController で連携状態を渡す**

`app/Http/Controllers/ProfileController.php` の `show()` を編集。`$attendanceCount` の行の後に追記し、`compact` に追加：

```php
        $googleCalendarToken = $user->googleCalendarToken;

        return view('profile.show', compact('user', 'events', 'attendanceCount', 'googleCalendarToken'));
```

- [ ] **Step 4: プロフィールビューに連携セクションを追加**

`resources/views/profile/show.blade.php` の統計ブロック（`参加申し込み数` を表示している `</div>` のすぐ後、`<!-- 作成したイベント -->` の前）に追記：

```blade
        <!-- Googleカレンダー連携 -->
        <div class="mt-6 rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-6 shadow-sm">
            <h2 class="text-lg font-bold tracking-tight mb-2">Googleカレンダー連携</h2>
            @if ($googleCalendarToken)
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                    連携中：{{ $googleCalendarToken->google_email ?? 'Googleアカウント' }}（イベント申込時に自動で予定が登録されます）
                </p>
                <form method="POST" action="{{ route('google.disconnect') }}"
                    onsubmit="return confirm('Googleカレンダー連携を解除しますか？')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 dark:border-[#3E3E3A] text-slate-600 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 text-sm transition">
                        連携を解除する
                    </button>
                </form>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                    連携すると、イベント申込時に自動で Google カレンダーへ予定が登録されます。
                </p>
                <a href="{{ route('google.connect') }}"
                    class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
                    連携する
                </a>
            @endif
        </div>
```

- [ ] **Step 5: テストが通ることを確認**

```bash
php artisan test --compact --filter=ProfileTest
```
Expected: PASS（既存 + 追加 2 件）。

- [ ] **Step 6: 全テストと整形**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```
Expected: 全件 PASS、Pint OK。

- [ ] **Step 7: コミット**

```bash
git add app/Http/Controllers/ProfileController.php resources/views/profile/show.blade.php tests/Feature/ProfileTest.php
git commit -m "feat: プロフィールにGoogleカレンダー連携セクションを追加"
```

---

## 完了後

- 全テスト PASS・Pint OK を確認
- 本番で動かすには Google Cloud Console 設定（OAuth クライアント発行）と `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` の設定が必要（設計書 6 章）
- `superpowers:finishing-a-development-branch` で仕上げ
- 後続：開催前の自動リマインド通知（別サブ機能）
