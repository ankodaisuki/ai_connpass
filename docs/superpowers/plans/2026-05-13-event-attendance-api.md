# イベント参加管理API 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** イベントの参加管理API (`/api/v1/events/{event}/attendances/*` および `/api/v1/me/attendances`) を実装する。

**Architecture:** 認証・イベント機能と同じ Controller + Resource + Eloquent 構成。コントローラは event-nested 用 (`EventAttendanceController`) と user-scoped 用 (`MyAttendanceController`) の2つに分割。FormRequest は不要 (リクエストボディなし)、Policy も不要 (自分のリソース限定で担保)。

**Tech Stack:** Laravel 13, PHP 8.4, Laravel Sanctum, PHPUnit 12, Laravel Pint

**Spec reference:** [docs/superpowers/specs/2026-05-13-event-attendance-api-design.md](../specs/2026-05-13-event-attendance-api-design.md)

---

## ファイル構成

新規作成:

- `app/Http/Controllers/Api/V1/EventAttendanceController.php` — index/store/destroy (event-nested)
- `app/Http/Controllers/Api/V1/MyAttendanceController.php` — index (/me/attendances)
- `app/Http/Resources/Api/V1/EventAttendanceResource.php` — 参加者一覧用
- `app/Http/Resources/Api/V1/MyAttendanceResource.php` — 自分一覧用
- `tests/Feature/Api/V1/EventAttendanceTest.php` — event-nested エンドポイントの Feature テスト
- `tests/Feature/Api/V1/MyAttendanceTest.php` — /me/attendances の Feature テスト

変更:

- `routes/api.php` — 4ルート追加
- `app/Models/User.php` — `eventAttendances` リレーション確認 (既存)

---

## Task 1: EventAttendanceResource を作成

**Files:**
- Create: `app/Http/Resources/Api/V1/EventAttendanceResource.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:resource Api/V1/EventAttendanceResource --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Resources/Api/V1/EventAttendanceResource.php` を以下で完全に置き換える:

```php
<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 参加者一覧用のリソース
 *
 * @mixin EventAttendance
 */
class EventAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'applied_at' => $this->applied_at->toIso8601ZuluString(),
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 2: MyAttendanceResource を作成

**Files:**
- Create: `app/Http/Resources/Api/V1/MyAttendanceResource.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:resource Api/V1/MyAttendanceResource --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Resources/Api/V1/MyAttendanceResource.php` を以下で完全に置き換える:

```php
<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 自分の申し込み一覧用のリソース（event情報を含む）
 *
 * @mixin EventAttendance
 */
class MyAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'event_date' => $this->event->event_date->toIso8601ZuluString(),
                'prefecture' => $this->event->prefecture,
                'location' => $this->event->location,
                'capacity' => $this->event->capacity,
            ],
            'applied_at' => $this->applied_at->toIso8601ZuluString(),
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 3: EventAttendanceTest の雛形と index 最初のテスト

**Files:**
- Create: `tests/Feature/Api/V1/EventAttendanceTest.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:test --phpunit Api/V1/EventAttendanceTest --no-interaction
```

- [ ] **Step 2: 実装**

`tests/Feature/Api/V1/EventAttendanceTest.php` を以下で完全に置き換える:

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * index: Published イベントの Applied 参加者を返却（Cancelled は除外）
     */
    public function test_index_returns_only_applied_attendances_for_published_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $applicants = User::factory()->count(2)->create();
        foreach ($applicants as $applicant) {
            EventAttendance::factory()->for($event)->for($applicant)->create();
        }

        $cancelledUser = User::factory()->create();
        EventAttendance::factory()->for($event)->for($cancelledUser)->cancelled()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}/attendances");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }
}
```

- [ ] **Step 3: テストを実行して失敗することを確認**

```bash
php artisan test --compact --filter=test_index_returns_only_applied_attendances_for_published_event
```

期待: FAIL (404、ルート未定義)

---

## Task 4: EventAttendanceController + index 実装

**Files:**
- Create: `app/Http/Controllers/Api/V1/EventAttendanceController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Artisan で雛形作成**

```bash
php artisan make:controller Api/V1/EventAttendanceController --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Controllers/Api/V1/EventAttendanceController.php` を以下で完全に置き換える:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventAttendanceResource;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * イベント参加管理 API コントローラ（event-nested）
 */
class EventAttendanceController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * 参加者一覧（Published イベントのみ、Applied のみ、15件/ページ、applied_at 昇順）
     */
    public function index(Event $event): AnonymousResourceCollection
    {
        if ($event->status !== EventStatus::Published) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $attendances = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->with('user')
            ->orderBy('applied_at', 'asc')
            ->paginate(self::PER_PAGE);

        return EventAttendanceResource::collection($attendances);
    }
}
```

- [ ] **Step 3: routes/api.php に index ルートを追加**

`routes/api.php` ファイル先頭の use 節に以下を追加:

```php
use App\Http\Controllers\Api\V1\EventAttendanceController;
```

`Route::prefix('v1')->group(function () {` ブロック内、既存の `// イベント (認証必須)` グループの **直後** に以下を追加:

```php
    // イベント参加 (認証不要)
    Route::get('events/{event}/attendances', [EventAttendanceController::class, 'index'])
        ->name('api.v1.events.attendances.index');
```

- [ ] **Step 4: テスト実行**

```bash
php artisan test --compact --filter=test_index_returns_only_applied_attendances_for_published_event
```

期待: 1 test passed.

- [ ] **Step 5: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 5: index の追加テスト (Draft/Private 404, SoftDeleted, ページネーション, ソート)

**Files:**
- Modify: `tests/Feature/Api/V1/EventAttendanceTest.php`

- [ ] **Step 1: テスト追加**

`EventAttendanceTest` クラスの既存テスト直後に以下を追加:

```php
    /**
     * index: Draft イベントは 404
     */
    public function test_index_returns_404_for_draft_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->draft()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}/attendances");

        $response->assertNotFound();
    }

    /**
     * index: Private イベントは 404
     */
    public function test_index_returns_404_for_private_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->private()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}/attendances");

        $response->assertNotFound();
    }

    /**
     * index: SoftDeleted イベントは 404
     */
    public function test_index_returns_404_for_soft_deleted_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $event->delete();

        $response = $this->getJson("/api/v1/events/{$event->id}/attendances");

        $response->assertNotFound();
    }

    /**
     * index: ページネーション 15件/ページ
     */
    public function test_index_paginates_with_15_per_page(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $applicants = User::factory()->count(16)->create();
        foreach ($applicants as $applicant) {
            EventAttendance::factory()->for($event)->for($applicant)->create();
        }

        $response = $this->getJson("/api/v1/events/{$event->id}/attendances");

        $response->assertOk();
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.per_page', 15);
        $response->assertJsonPath('meta.total', 16);
        $response->assertJsonPath('meta.last_page', 2);
    }

    /**
     * index: applied_at 昇順
     */
    public function test_index_sorts_by_applied_at_ascending(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $userLater = User::factory()->create();
        EventAttendance::factory()->for($event)->for($userLater)->create([
            'applied_at' => now()->addHours(2),
        ]);

        $userSooner = User::factory()->create();
        EventAttendance::factory()->for($event)->for($userSooner)->create([
            'applied_at' => now()->addHours(1),
        ]);

        $response = $this->getJson("/api/v1/events/{$event->id}/attendances");

        $response->assertOk();
        $response->assertJsonPath('data.0.user.id', $userSooner->id);
        $response->assertJsonPath('data.1.user.id', $userLater->id);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_index_'
```

期待: 6 tests passed (Task 3 の1件 + Task 5 の5件)

---

## Task 6: store の最初のテスト（正常系）

**Files:**
- Modify: `tests/Feature/Api/V1/EventAttendanceTest.php`

- [ ] **Step 1: テスト追加**

`EventAttendanceTest` クラスの最後に以下を追加:

```php
    /**
     * store: 認証ユーザーが Published イベントに申し込み、201、DB に Applied で保存
     */
    public function test_store_creates_applied_attendance_for_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'capacity' => 10,
        ]);

        $applicant = User::factory()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertCreated();
        $response->assertJsonPath('data.user.id', $applicant->id);
        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Applied->value,
        ]);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter=test_store_creates_applied_attendance_for_authenticated_user
```

期待: FAIL (ルート未定義)

---

## Task 7: store 実装

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EventAttendanceController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: EventAttendanceController に store メソッドを追加**

`app/Http/Controllers/Api/V1/EventAttendanceController.php` の use 節に以下を追加:

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
```

そして `index` メソッドの直後に以下を追加:

```php
    /**
     * イベント申し込み
     *
     * - Published 以外は 404 (存在を秘匿)
     * - 過去イベントは 422
     * - 作成者本人は 422
     * - 定員オーバーは 422
     * - 重複申し込みは 422
     * - キャンセル済みからの再申し込みは update で復活
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        if ($event->status !== EventStatus::Published) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($event->event_date->isPast()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'このイベントはすでに開始しています。');
        }

        $user = $request->user();

        if ($event->user_id === $user->id) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, '作成者は自分のイベントに申し込めません。');
        }

        $existing = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null && $existing->status === AttendanceStatus::Applied) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'すでに申し込み済みです。');
        }

        $appliedCount = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->count();

        if ($appliedCount >= $event->capacity) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, '定員に達しています。');
        }

        if ($existing !== null) {
            // 再申し込み
            $existing->update([
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
                'cancelled_at' => null,
            ]);
            $attendance = $existing;
        } else {
            // 新規申し込み
            $attendance = EventAttendance::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
            ]);
        }

        $attendance->load('user');

        return (new EventAttendanceResource($attendance))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
```

- [ ] **Step 2: routes/api.php に store ルートを追加**

`routes/api.php` の `Route::prefix('v1')->group(function () {` 内、既存のイベント参加 (認証不要) グループの **直後** に以下を追加:

```php
    // イベント参加 (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('events/{event}/attendances', [EventAttendanceController::class, 'store'])
            ->name('api.v1.events.attendances.store');
    });
```

- [ ] **Step 3: テスト実行**

```bash
php artisan test --compact --filter=test_store_creates_applied_attendance_for_authenticated_user
```

期待: 1 test passed.

- [ ] **Step 4: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 8: store 追加テスト (異常系 + 再申し込み)

**Files:**
- Modify: `tests/Feature/Api/V1/EventAttendanceTest.php`

- [ ] **Step 1: テスト追加**

`EventAttendanceTest` クラスの最後に以下を追加:

```php
    /**
     * store: キャンセル済みから再申し込み (status=Applied, cancelled_at=null)
     */
    public function test_store_reapplies_when_previously_cancelled(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'capacity' => 10,
        ]);

        $applicant = User::factory()->create();
        $cancelled = EventAttendance::factory()->for($event)->for($applicant)->cancelled()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertCreated();
        $this->assertDatabaseHas('event_attendances', [
            'id' => $cancelled->id,
            'status' => AttendanceStatus::Applied->value,
            'cancelled_at' => null,
        ]);
        $this->assertDatabaseCount('event_attendances', 1);
    }

    /**
     * store: 認証なしは 401
     */
    public function test_store_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $response = $this->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertUnauthorized();
    }

    /**
     * store: 凍結ユーザーは 403
     */
    public function test_store_rejects_inactive_user(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $applicant = User::factory()->inactive()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertForbidden();
    }

    /**
     * store: Draft イベントは 404
     */
    public function test_store_returns_404_for_draft_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->draft()->create();
        $applicant = User::factory()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertNotFound();
    }

    /**
     * store: 過去イベントは 422
     */
    public function test_store_fails_for_past_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subDays(1),
        ]);
        $applicant = User::factory()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertUnprocessable();
        $response->assertJson(['message' => 'このイベントはすでに開始しています。']);
    }

    /**
     * store: 作成者本人は 422
     */
    public function test_store_fails_for_event_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $token = $owner->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertUnprocessable();
        $response->assertJson(['message' => '作成者は自分のイベントに申し込めません。']);
    }

    /**
     * store: 定員オーバーは 422
     */
    public function test_store_fails_when_capacity_is_full(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'capacity' => 2,
        ]);

        // 定員2名を埋める
        $others = User::factory()->count(2)->create();
        foreach ($others as $other) {
            EventAttendance::factory()->for($event)->for($other)->create();
        }

        $applicant = User::factory()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertUnprocessable();
        $response->assertJson(['message' => '定員に達しています。']);
    }

    /**
     * store: 重複申し込みは 422
     */
    public function test_store_fails_when_already_applied(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson("/api/v1/events/{$event->id}/attendances");

        $response->assertUnprocessable();
        $response->assertJson(['message' => 'すでに申し込み済みです。']);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_store_'
```

期待: 9 tests passed (Task 6 の1件 + Task 8 の8件)

---

## Task 9: destroy の最初のテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventAttendanceTest.php`

- [ ] **Step 1: テスト追加**

`EventAttendanceTest` クラスの最後に以下を追加:

```php
    /**
     * destroy: 正常系、status=Cancelled, cancelled_at セット、204
     */
    public function test_destroy_cancels_attendance(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
        ]);
        $applicant = User::factory()->create();
        $attendance = EventAttendance::factory()->for($event)->for($applicant)->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}/attendances");

        $response->assertNoContent();
        $this->assertDatabaseHas('event_attendances', [
            'id' => $attendance->id,
            'status' => AttendanceStatus::Cancelled->value,
        ]);
        $cancelled = EventAttendance::find($attendance->id);
        $this->assertNotNull($cancelled->cancelled_at);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter=test_destroy_cancels_attendance
```

期待: FAIL (ルート未定義)

---

## Task 10: destroy 実装

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EventAttendanceController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: EventAttendanceController に destroy メソッドを追加**

`app/Http/Controllers/Api/V1/EventAttendanceController.php` の `store` メソッドの直後に以下を追加:

```php
    /**
     * 自分の申し込みをキャンセル
     *
     * - 過去イベントは 422
     * - 自分の Applied 申し込みがなければ 404
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        if ($event->event_date->isPast()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'このイベントはすでに開始しています。');
        }

        $user = $request->user();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', AttendanceStatus::Applied)
            ->first();

        if ($attendance === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $attendance->update([
            'status' => AttendanceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
```

- [ ] **Step 2: routes/api.php に destroy ルートを追加**

`routes/api.php` の認証必須グループ（イベント参加）を以下に書き換える:

```php
    // イベント参加 (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('events/{event}/attendances', [EventAttendanceController::class, 'store'])
            ->name('api.v1.events.attendances.store');
        Route::delete('events/{event}/attendances', [EventAttendanceController::class, 'destroy'])
            ->name('api.v1.events.attendances.destroy');
    });
```

- [ ] **Step 3: テスト実行**

```bash
php artisan test --compact --filter=test_destroy_cancels_attendance
```

期待: 1 test passed.

- [ ] **Step 4: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 11: destroy 追加テスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventAttendanceTest.php`

- [ ] **Step 1: テスト追加**

`EventAttendanceTest` クラスの最後に以下を追加:

```php
    /**
     * destroy: 認証なしは 401
     */
    public function test_destroy_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $response = $this->deleteJson("/api/v1/events/{$event->id}/attendances");

        $response->assertUnauthorized();
    }

    /**
     * destroy: 凍結ユーザーは 403
     */
    public function test_destroy_rejects_inactive_user(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $applicant = User::factory()->inactive()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}/attendances");

        $response->assertForbidden();
    }

    /**
     * destroy: 申し込んでいないユーザーは 404
     */
    public function test_destroy_returns_404_when_not_applied(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
        ]);
        $applicant = User::factory()->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}/attendances");

        $response->assertNotFound();
    }

    /**
     * destroy: 過去イベントは 422
     */
    public function test_destroy_fails_for_past_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subDays(1),
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create();
        $token = $applicant->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}/attendances");

        $response->assertUnprocessable();
        $response->assertJson(['message' => 'このイベントはすでに開始しています。']);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_destroy_'
```

期待: 5 tests passed (Task 9 の1件 + Task 11 の4件)

---

## Task 12: MyAttendanceTest の雛形と index の最初のテスト

**Files:**
- Create: `tests/Feature/Api/V1/MyAttendanceTest.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:test --phpunit Api/V1/MyAttendanceTest --no-interaction
```

- [ ] **Step 2: 実装**

`tests/Feature/Api/V1/MyAttendanceTest.php` を以下で完全に置き換える:

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * index: 自分の Applied 申し込みのみ返却、event 情報を含む
     */
    public function test_index_returns_only_my_applied_attendances_with_event(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $event1 = Event::factory()->for($otherUser)->create(['status' => EventStatus::Published, 'title' => 'event1']);
        $event2 = Event::factory()->for($otherUser)->create(['status' => EventStatus::Published, 'title' => 'event2']);

        // 自分の Applied
        EventAttendance::factory()->for($event1)->for($user)->create();

        // 自分の Cancelled (除外される)
        EventAttendance::factory()->for($event2)->for($user)->cancelled()->create();

        // 他人の Applied (除外される)
        $stranger = User::factory()->create();
        EventAttendance::factory()->for($event1)->for($stranger)->create();

        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/me/attendances');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.event.id', $event1->id);
        $response->assertJsonPath('data.0.event.title', 'event1');
    }
}
```

- [ ] **Step 3: テスト実行**

```bash
php artisan test --compact --filter=test_index_returns_only_my_applied_attendances_with_event
```

期待: FAIL (ルート未定義)

---

## Task 13: MyAttendanceController + index 実装

**Files:**
- Create: `app/Http/Controllers/Api/V1/MyAttendanceController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Artisan で雛形作成**

```bash
php artisan make:controller Api/V1/MyAttendanceController --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Controllers/Api/V1/MyAttendanceController.php` を以下で完全に置き換える:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MyAttendanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 自分の申し込み一覧 API コントローラ
 */
class MyAttendanceController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * 自分の Applied 申し込み一覧（15件/ページ、applied_at 昇順）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $attendances = $request->user()
            ->eventAttendances()
            ->where('status', AttendanceStatus::Applied)
            ->with('event')
            ->orderBy('applied_at', 'asc')
            ->paginate(self::PER_PAGE);

        return MyAttendanceResource::collection($attendances);
    }
}
```

- [ ] **Step 3: routes/api.php に追加**

`routes/api.php` の先頭の use 節に以下を追加:

```php
use App\Http\Controllers\Api\V1\MyAttendanceController;
```

そして既存の認証必須グループ (イベント参加) の **直後** に以下を追加:

```php
    // 自分の申し込み一覧 (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::get('me/attendances', [MyAttendanceController::class, 'index'])
            ->name('api.v1.me.attendances.index');
    });
```

- [ ] **Step 4: テスト実行**

```bash
php artisan test --compact --filter=test_index_returns_only_my_applied_attendances_with_event
```

期待: 1 test passed.

- [ ] **Step 5: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 14: MyAttendance index 追加テスト

**Files:**
- Modify: `tests/Feature/Api/V1/MyAttendanceTest.php`

- [ ] **Step 1: テスト追加**

`MyAttendanceTest` クラスの最後に以下を追加:

```php
    /**
     * index: 認証なしは 401
     */
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/attendances');

        $response->assertUnauthorized();
    }

    /**
     * index: 凍結ユーザーは 403
     */
    public function test_index_rejects_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();
        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/me/attendances');

        $response->assertForbidden();
    }

    /**
     * index: ページネーション 15件/ページ
     */
    public function test_index_paginates_with_15_per_page(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        for ($i = 0; $i < 16; $i++) {
            $event = Event::factory()->for($otherUser)->create(['status' => EventStatus::Published]);
            EventAttendance::factory()->for($event)->for($user)->create();
        }

        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/me/attendances');

        $response->assertOk();
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.per_page', 15);
        $response->assertJsonPath('meta.total', 16);
        $response->assertJsonPath('meta.last_page', 2);
    }

    /**
     * index: applied_at 昇順
     */
    public function test_index_sorts_by_applied_at_ascending(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $event1 = Event::factory()->for($otherUser)->create(['status' => EventStatus::Published, 'title' => 'later-event']);
        EventAttendance::factory()->for($event1)->for($user)->create([
            'applied_at' => now()->addHours(2),
        ]);

        $event2 = Event::factory()->for($otherUser)->create(['status' => EventStatus::Published, 'title' => 'sooner-event']);
        EventAttendance::factory()->for($event2)->for($user)->create([
            'applied_at' => now()->addHours(1),
        ]);

        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/me/attendances');

        $response->assertOk();
        $response->assertJsonPath('data.0.event.title', 'sooner-event');
        $response->assertJsonPath('data.1.event.title', 'later-event');
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_index_' tests/Feature/Api/V1/MyAttendanceTest.php
```

期待: 5 tests passed (Task 12 の1件 + Task 14 の4件)

---

## Task 15: 全テスト & 最終整形 & TASKS.md 更新

**Files:** なし (検証 + TASKS.md 更新)

- [ ] **Step 1: 参加管理APIの全テスト**

```bash
php artisan test --compact tests/Feature/Api/V1/EventAttendanceTest.php tests/Feature/Api/V1/MyAttendanceTest.php
```

期待: 25 tests passed (EventAttendance 20件 + MyAttendance 5件)

内訳: EventAttendance = index 6 + store 9 + destroy 5 = 20件

- [ ] **Step 2: API全体テスト (回帰確認)**

```bash
php artisan test --compact tests/Feature/Api
```

期待: 18 (auth) + 27 (event) + 25 (attendance) = 70 tests passed

- [ ] **Step 3: Pint で整形違反ゼロを確認**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: TASKS.md を更新**

`TASKS.md` を編集する。

変更前の「実装中のタスク」セクション:

```markdown
## 📌 実装中のタスク

- [ ] イベント参加管理API実装：申し込み・キャンセル機能＆テスト
- [ ] 検索・フィルタリング機能実装：イベント一覧検索
```

変更後:

```markdown
## 📌 実装中のタスク

- [ ] 検索・フィルタリング機能実装：イベント一覧検索
```

「完了済み」セクション末尾に以下を追加:

```markdown
- [x] イベント参加管理API実装：申し込み・キャンセル機能＆テスト
```

---

## 受け入れ基準

- [ ] 4 エンドポイント (index/store/destroy/me-index) すべてが仕様どおりのステータスコードとレスポンスを返す
- [ ] `php artisan test --compact tests/Feature/Api/V1/EventAttendanceTest.php tests/Feature/Api/V1/MyAttendanceTest.php` で 25 件すべて PASS する
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
- [ ] 申し込み・キャンセル・再申し込みのフローが動作する
- [ ] 定員、過去イベント、作成者本人、Draft/Private、重複申し込みのいずれも適切に拒否される
- [ ] TASKS.md のイベント参加管理API実装が完了に移動している
