# イベント管理API 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** イベントの CRUD API (`/api/v1/events/*`) を実装する。

**Architecture:** 認証機能と同一の Controller + FormRequest + Policy + Resource 構成。Route Model Binding で `Event $event` を受け取る。削除は status=Private 更新と SoftDeletes(`deleted_at`) の両方を更新。閲覧系 (index/show) は認証不要、更新系 (store/update/destroy) は `auth:sanctum`+`active.user` ミドルウェアと `EventPolicy` で保護。

**Tech Stack:** Laravel 13, PHP 8.4, Laravel Sanctum, PHPUnit 12, Laravel Pint

**Spec reference:** [docs/superpowers/specs/2026-05-13-event-management-api-design.md](../specs/2026-05-13-event-management-api-design.md)

---

## ファイル構成

新規作成:

- `app/Http/Controllers/Api/V1/EventController.php` — 5メソッド (index/show/store/update/destroy)
- `app/Http/Requests/Api/V1/Event/StoreEventRequest.php` — 作成バリデーション
- `app/Http/Requests/Api/V1/Event/UpdateEventRequest.php` — 更新バリデーション
- `app/Http/Resources/Api/V1/EventResource.php` — API出力整形
- `app/Policies/EventPolicy.php` — update/delete 権限判定
- `tests/Feature/Api/V1/EventTest.php` — 全エンドポイントのFeatureテスト

変更:

- `routes/api.php` — v1/events ルート追記

---

## Task 1: EventResource を作成

**Files:**
- Create: `app/Http/Resources/Api/V1/EventResource.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:resource Api/V1/EventResource --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Resources/Api/V1/EventResource.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * イベント情報の API レスポンス整形
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category->value,
            'prefecture' => $this->prefecture,
            'location' => $this->location,
            'event_date' => $this->event_date->toIso8601ZuluString(),
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 2: EventPolicy を作成

**Files:**
- Create: `app/Policies/EventPolicy.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:policy EventPolicy --model=Event --no-interaction
```

- [ ] **Step 2: 実装**

`app/Policies/EventPolicy.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * イベントに対するアクセス制御
 *
 * 閲覧系 (view/viewAny) はコントローラ内で判定するため Policy には含めない。
 */
class EventPolicy
{
    /**
     * 更新は作成者本人のみ許可
     */
    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    /**
     * 削除は作成者本人のみ許可
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 3: StoreEventRequest を作成

**Files:**
- Create: `app/Http/Requests/Api/V1/Event/StoreEventRequest.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:request Api/V1/Event/StoreEventRequest --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Requests/Api/V1/Event/StoreEventRequest.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Http\Requests\Api\V1\Event;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * イベント作成のバリデーション
 */
class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'integer', Rule::enum(EventCategory::class)],
            'prefecture' => ['required', 'string', 'max:10'],
            'location' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after:now'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', 'integer', Rule::enum(EventStatus::class)],
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 4: UpdateEventRequest を作成

**Files:**
- Create: `app/Http/Requests/Api/V1/Event/UpdateEventRequest.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:request Api/V1/Event/UpdateEventRequest --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Requests/Api/V1/Event/UpdateEventRequest.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Http\Requests\Api\V1\Event;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * イベント更新のバリデーション
 *
 * PUT セマンティクスのため全フィールド必須。
 */
class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'integer', Rule::enum(EventCategory::class)],
            'prefecture' => ['required', 'string', 'max:10'],
            'location' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after:now'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'integer', Rule::enum(EventStatus::class)],
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 5: index エンドポイントの失敗テストを作成（TDD）

**Files:**
- Create: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: Artisan でテストファイルの雛形を作成**

```bash
php artisan make:test --phpunit Api/V1/EventTest --no-interaction
```

- [ ] **Step 2: index テストを実装**

`tests/Feature/Api/V1/EventTest.php` を以下の内容で完全に置き換える:

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * index: Published のみ返却される
     */
    public function test_index_returns_only_published_events(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'published-event']);
        Event::factory()->for($user)->draft()->create(['title' => 'draft-event']);
        Event::factory()->for($user)->private()->create(['title' => 'private-event']);

        $response = $this->getJson('/api/v1/events');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'published-event');
    }
}
```

- [ ] **Step 3: テストを実行して失敗することを確認**

```bash
php artisan test --compact --filter=test_index_returns_only_published_events
```

期待: FAIL（ルートが未定義のため 404）

---

## Task 6: EventController を作成し index を実装

**Files:**
- Create: `app/Http/Controllers/Api/V1/EventController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:controller Api/V1/EventController --no-interaction
```

- [ ] **Step 2: EventController を実装**

`app/Http/Controllers/Api/V1/EventController.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Event\StoreEventRequest;
use App\Http\Requests\Api\V1\Event\UpdateEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * イベント管理 API コントローラ
 */
class EventController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * イベント一覧（Published のみ、event_date 昇順、ページネーション）
     */
    public function index(): AnonymousResourceCollection
    {
        $events = Event::query()
            ->with('user')
            ->where('status', EventStatus::Published)
            ->orderBy('event_date', 'asc')
            ->paginate(self::PER_PAGE);

        return EventResource::collection($events);
    }
}
```

- [ ] **Step 3: routes/api.php に index ルートを追加**

`routes/api.php` を開き、既存の `Route::prefix('v1')->group(function () {` ブロック内に **events** プレフィックスのグループを追加する。

変更前の `Route::prefix('v1')->group(function () { ... });` 部分を以下のように書き換える:

```php
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // 認証不要
        Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');

        // 認証必須
        Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
            Route::post('refresh', [AuthController::class, 'refresh'])->name('api.v1.auth.refresh');
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });

    // イベント (認証不要)
    Route::get('events', [EventController::class, 'index'])->name('api.v1.events.index');
});
```

そしてファイル先頭の use 節に EventController を追加:

```php
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use Illuminate\Support\Facades\Route;
```

- [ ] **Step 4: テストを実行して通ることを確認**

```bash
php artisan test --compact --filter=test_index_returns_only_published_events
```

期待: 1 test passed.

- [ ] **Step 5: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 7: index のページネーション・ソート・SoftDelete除外テストを追加

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラス内の既存テストの直後に以下を追加:

```php
    /**
     * index: ページネーション 15件/ページ
     */
    public function test_index_paginates_with_15_per_page(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->count(16)->create(['status' => EventStatus::Published]);

        $response = $this->getJson('/api/v1/events');

        $response->assertOk();
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.per_page', 15);
        $response->assertJsonPath('meta.total', 16);
        $response->assertJsonPath('meta.last_page', 2);
    }

    /**
     * index: event_date 昇順
     */
    public function test_index_sorts_by_event_date_ascending(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(10),
            'title' => 'later',
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(2),
            'title' => 'sooner',
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertOk();
        $response->assertJsonPath('data.0.title', 'sooner');
        $response->assertJsonPath('data.1.title', 'later');
    }

    /**
     * index: SoftDeleted は除外
     */
    public function test_index_excludes_soft_deleted_events(): void
    {
        $user = User::factory()->create();
        $deleted = Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'should-not-appear',
        ]);
        $deleted->delete();

        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'should-appear',
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'should-appear');
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_index_'
```

期待: 4 tests passed.

---

## Task 8: show エンドポイントの最初のテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * show: Published は認証不要で取得可
     */
    public function test_show_returns_published_event_for_guests(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'public-event',
        ]);

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $event->id);
        $response->assertJsonPath('data.title', 'public-event');
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('data.user.name', $user->name);
    }
```

- [ ] **Step 2: テスト実行して落ちることを確認**

```bash
php artisan test --compact --filter=test_show_returns_published_event_for_guests
```

期待: FAIL（ルートが未定義のため 404）

---

## Task 9: show エンドポイントを実装

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EventController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: EventController に show メソッドを追加**

`app/Http/Controllers/Api/V1/EventController.php` の `index` メソッドの直後に以下を追加:

```php
    /**
     * イベント詳細
     *
     * Published は誰でも、Draft/Private は作成者本人のみ取得可。
     * 他人の Draft/Private は存在を秘匿するため 404。
     */
    public function show(Request $request, Event $event): EventResource
    {
        if ($event->status !== EventStatus::Published) {
            $user = $request->user();
            if ($user === null || $user->id !== $event->user_id) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        $event->load('user');

        return new EventResource($event);
    }
```

- [ ] **Step 2: routes/api.php に show ルートを追加**

`routes/api.php` の `// イベント (認証不要)` ブロックを以下に書き換える:

```php
    // イベント (認証不要)
    Route::get('events', [EventController::class, 'index'])->name('api.v1.events.index');
    Route::get('events/{event}', [EventController::class, 'show'])->name('api.v1.events.show');
```

- [ ] **Step 3: テスト実行**

```bash
php artisan test --compact --filter=test_show_returns_published_event_for_guests
```

期待: 1 test passed.

- [ ] **Step 4: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 10: show の追加テスト（Draft/Private本人/他人/SoftDeleted）

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * show: Draft を本人が取得可
     */
    public function test_show_returns_draft_event_for_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->draft()->create();
        $token = $owner->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson("/api/v1/events/{$event->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $event->id);
    }

    /**
     * show: Private を本人が取得可
     */
    public function test_show_returns_private_event_for_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->private()->create();
        $token = $owner->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson("/api/v1/events/{$event->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $event->id);
    }

    /**
     * show: Draft を非認証ユーザーが取得 → 404
     */
    public function test_show_returns_404_for_draft_to_guest(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->draft()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertNotFound();
    }

    /**
     * show: Private を他人が取得 → 404
     */
    public function test_show_returns_404_for_private_to_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($owner)->private()->create();
        $token = $other->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson("/api/v1/events/{$event->id}");

        $response->assertNotFound();
    }

    /**
     * show: SoftDeleted は 404
     */
    public function test_show_returns_404_for_soft_deleted_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $event->delete();

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertNotFound();
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_show_'
```

期待: 6 tests passed.

---

## Task 11: store エンドポイントの最初のテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * store: 認証ユーザーがイベント作成、user_id 自動設定
     */
    public function test_store_creates_event_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $payload = [
            'title' => 'テストイベント',
            'description' => 'テスト用です',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区テスト1-1-1',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 30,
            'status' => 1,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/events', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'テストイベント');
        $response->assertJsonPath('data.user.id', $user->id);
        $this->assertDatabaseHas('events', [
            'title' => 'テストイベント',
            'user_id' => $user->id,
            'status' => EventStatus::Published->value,
        ]);
    }
```

- [ ] **Step 2: テスト実行して落ちることを確認**

```bash
php artisan test --compact --filter=test_store_creates_event_for_authenticated_user
```

期待: FAIL（ルート未定義による 404 or method not allowed）

---

## Task 12: store エンドポイントを実装

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EventController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: EventController に store メソッドを追加**

`app/Http/Controllers/Api/V1/EventController.php` の `show` メソッドの直後に以下を追加:

```php
    /**
     * イベント作成
     *
     * status 未指定なら Draft で作成される。user_id は認証ユーザーから自動設定。
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['status'] = isset($data['status'])
            ? EventStatus::from($data['status'])
            : EventStatus::Draft;

        $event = Event::create($data);
        $event->load('user');

        return (new EventResource($event))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
```

- [ ] **Step 2: routes/api.php に認証必須グループ + store ルートを追加**

`routes/api.php` の `// イベント (認証不要)` ブロックの直後に以下を追加する:

```php
    // イベント (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('events', [EventController::class, 'store'])->name('api.v1.events.store');
    });
```

- [ ] **Step 3: テスト実行**

```bash
php artisan test --compact --filter=test_store_creates_event_for_authenticated_user
```

期待: 1 test passed.

- [ ] **Step 4: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 13: store の追加テスト (デフォルトstatus・認証・凍結・バリデーション)

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * store: status 未指定なら Draft で作成される
     */
    public function test_store_defaults_status_to_draft_when_omitted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $payload = [
            'title' => 'タイトル',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 10,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/events', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.status', EventStatus::Draft->value);
    }

    /**
     * store: 認証なしは 401
     */
    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/events', []);

        $response->assertUnauthorized();
    }

    /**
     * store: 凍結ユーザーは 403
     */
    public function test_store_rejects_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();
        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/events', []);

        $response->assertForbidden();
    }

    /**
     * store: title 欠如は 422
     */
    public function test_store_fails_when_title_is_missing(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $payload = [
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 10,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/events', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title']);
    }

    /**
     * store: event_date が過去は 422
     */
    public function test_store_fails_when_event_date_is_in_the_past(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $payload = [
            'title' => 'タイトル',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->subDays(1)->toIso8601String(),
            'capacity' => 10,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/events', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['event_date']);
    }

    /**
     * store: capacity が 0 以下は 422
     */
    public function test_store_fails_when_capacity_is_zero(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $payload = [
            'title' => 'タイトル',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 0,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/events', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['capacity']);
    }

    /**
     * store: category が範囲外は 422
     */
    public function test_store_fails_when_category_is_invalid(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $payload = [
            'title' => 'タイトル',
            'category' => 99,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 10,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/events', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['category']);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_store_'
```

期待: 8 tests passed (Task 11 の1件 + Task 13 の7件)

---

## Task 14: update エンドポイントの最初のテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * update: 本人が更新可
     */
    public function test_update_succeeds_for_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();
        $token = $owner->createToken('api-token');

        $payload = [
            'title' => '更新後のタイトル',
            'description' => '更新後の説明',
            'category' => 2,
            'prefecture' => '大阪府',
            'location' => '大阪市',
            'event_date' => now()->addDays(7)->toIso8601String(),
            'capacity' => 50,
            'status' => 1,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->putJson("/api/v1/events/{$event->id}", $payload);

        $response->assertOk();
        $response->assertJsonPath('data.title', '更新後のタイトル');
        $response->assertJsonPath('data.prefecture', '大阪府');
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => '更新後のタイトル',
            'prefecture' => '大阪府',
        ]);
    }
```

- [ ] **Step 2: テスト実行して落ちることを確認**

```bash
php artisan test --compact --filter=test_update_succeeds_for_owner
```

期待: FAIL（ルート未定義）

---

## Task 15: update エンドポイントを実装

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EventController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: EventController に update メソッドを追加**

`app/Http/Controllers/Api/V1/EventController.php` の `store` メソッドの直後に以下を追加:

```php
    /**
     * イベント更新（本人のみ）
     */
    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $data = $request->validated();
        $data['status'] = EventStatus::from($data['status']);

        $event->update($data);
        $event->load('user');

        return new EventResource($event);
    }
```

- [ ] **Step 2: クラス先頭に AuthorizesRequests trait を追加**

`app/Http/Controllers/Api/V1/EventController.php` の use 節と class の trait 利用を以下のように修正:

use 節に追加:

```php
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
```

そして `class EventController extends Controller` 直後に以下を追加:

```php
class EventController extends Controller
{
    use AuthorizesRequests;

    private const int PER_PAGE = 15;
```

- [ ] **Step 3: routes/api.php に update ルートを追加**

`routes/api.php` の認証必須グループに以下を追加:

```php
    // イベント (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('events', [EventController::class, 'store'])->name('api.v1.events.store');
        Route::put('events/{event}', [EventController::class, 'update'])->name('api.v1.events.update');
    });
```

- [ ] **Step 4: テスト実行**

```bash
php artisan test --compact --filter=test_update_succeeds_for_owner
```

期待: 1 test passed.

- [ ] **Step 5: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 16: update の追加テスト (認証・他人・バリデーション・SoftDeleted)

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * update: 認証なしは 401
     */
    public function test_update_requires_authentication(): void
    {
        $event = Event::factory()->for(User::factory())->create();

        $response = $this->putJson("/api/v1/events/{$event->id}", []);

        $response->assertUnauthorized();
    }

    /**
     * update: 他人は 403
     */
    public function test_update_returns_403_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($owner)->create();
        $token = $other->createToken('api-token');

        $payload = [
            'title' => 'タイトル',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 10,
            'status' => 1,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->putJson("/api/v1/events/{$event->id}", $payload);

        $response->assertForbidden();
    }

    /**
     * update: バリデーション失敗は 422
     */
    public function test_update_fails_when_validation_fails(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();
        $token = $owner->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->putJson("/api/v1/events/{$event->id}", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title', 'category', 'prefecture', 'location', 'event_date', 'capacity', 'status']);
    }

    /**
     * update: SoftDeleted は 404
     */
    public function test_update_returns_404_for_soft_deleted_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();
        $event->delete();
        $token = $owner->createToken('api-token');

        $payload = [
            'title' => 'タイトル',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 10,
            'status' => 1,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->putJson("/api/v1/events/{$event->id}", $payload);

        $response->assertNotFound();
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_update_'
```

期待: 5 tests passed (Task 14 の1件 + Task 16 の4件)

---

## Task 17: destroy エンドポイントの最初のテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * destroy: 本人が削除、status=Private + deleted_at セット、204
     */
    public function test_destroy_soft_deletes_and_sets_status_to_private(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $token = $owner->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => EventStatus::Private->value,
        ]);
    }
```

- [ ] **Step 2: テスト実行して落ちることを確認**

```bash
php artisan test --compact --filter=test_destroy_soft_deletes_and_sets_status_to_private
```

期待: FAIL（ルート未定義）

---

## Task 18: destroy エンドポイントを実装

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EventController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: EventController に destroy メソッドを追加**

`app/Http/Controllers/Api/V1/EventController.php` の `update` メソッドの直後に以下を追加:

```php
    /**
     * イベント削除（本人のみ）
     *
     * status=Private に更新したうえで SoftDeletes により deleted_at をセット。
     */
    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->update(['status' => EventStatus::Private]);
        $event->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
```

- [ ] **Step 2: routes/api.php に destroy ルートを追加**

`routes/api.php` の認証必須グループを以下に書き換える:

```php
    // イベント (認証必須)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('events', [EventController::class, 'store'])->name('api.v1.events.store');
        Route::put('events/{event}', [EventController::class, 'update'])->name('api.v1.events.update');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('api.v1.events.destroy');
    });
```

- [ ] **Step 3: テスト実行**

```bash
php artisan test --compact --filter=test_destroy_soft_deletes_and_sets_status_to_private
```

期待: 1 test passed.

- [ ] **Step 4: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 19: destroy の追加テスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * destroy: 削除後の GET/PUT/DELETE は 404
     */
    public function test_destroy_makes_event_inaccessible_afterwards(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $token = $owner->createToken('api-token');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}")
            ->assertNoContent();

        // GET show: 404
        $this->getJson("/api/v1/events/{$event->id}")->assertNotFound();

        // PUT: 404
        $payload = [
            'title' => 'タイトル',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toIso8601String(),
            'capacity' => 10,
            'status' => 1,
        ];
        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->putJson("/api/v1/events/{$event->id}", $payload)
            ->assertNotFound();

        // DELETE: 404
        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}")
            ->assertNotFound();
    }

    /**
     * destroy: 認証なしは 401
     */
    public function test_destroy_requires_authentication(): void
    {
        $event = Event::factory()->for(User::factory())->create();

        $response = $this->deleteJson("/api/v1/events/{$event->id}");

        $response->assertUnauthorized();
    }

    /**
     * destroy: 他人は 403
     */
    public function test_destroy_returns_403_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($owner)->create();
        $token = $other->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertForbidden();
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_destroy_'
```

期待: 4 tests passed.

---

## Task 20: 全テスト＆最終整形

**Files:** なし (検証のみ)

- [ ] **Step 1: イベント関連の全テストを通す**

```bash
php artisan test --compact tests/Feature/Api/V1/EventTest.php
```

期待: 27 tests passed (index 4, show 6, store 8, update 5, destroy 4)

- [ ] **Step 2: 認証機能 + イベント機能の全テストを通して回帰確認**

```bash
php artisan test --compact tests/Feature/Api
```

期待: 18 (auth) + 27 (event) = 45 tests passed

- [ ] **Step 3: Pint で整形違反ゼロを確認**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: TASKS.md を更新**

`TASKS.md` を編集する。

変更前の「実装中のタスク」セクション:

```markdown
## 📌 実装中のタスク

- [ ] イベント管理API実装：CRUD操作＆テスト
- [ ] イベント参加管理API実装：申し込み・キャンセル機能＆テスト
- [ ] 検索・フィルタリング機能実装：イベント一覧検索
```

変更後:

```markdown
## 📌 実装中のタスク

- [ ] イベント参加管理API実装：申し込み・キャンセル機能＆テスト
- [ ] 検索・フィルタリング機能実装：イベント一覧検索
```

「完了済み」セクション末尾に以下を追加:

```markdown
- [x] イベント管理API実装：CRUD操作＆テスト
```

---

## 受け入れ基準

- [ ] 5 エンドポイント (index/show/store/update/destroy) すべてが仕様どおりのステータスコードとレスポンスを返す
- [ ] `php artisan test --compact tests/Feature/Api/V1/EventTest.php` で 27 件すべて PASS する
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
- [ ] Policy が他人による更新・削除を防ぐ
- [ ] DELETE が status=Private と deleted_at の両方を更新する
- [ ] 凍結ユーザー (`status=0`) は store/update/destroy で 403
- [ ] TASKS.md のイベント管理API実装が完了に移動している
