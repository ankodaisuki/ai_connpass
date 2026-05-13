# イベント検索・フィルタリング機能 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 既存の `GET /api/v1/events` に検索・フィルタリング機能 (キーワード/カテゴリ/都道府県/日付範囲) を追加する。

**Architecture:** 既存の `EventController::index` を拡張し、新規 `IndexEventRequest` でクエリパラメータを検証。複数パラメータは AND 結合。`to` の日付のみ指定は endOfDay 補完で当日含む。

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 12, Laravel Pint

**Spec reference:** [docs/superpowers/specs/2026-05-13-event-search-api-design.md](../specs/2026-05-13-event-search-api-design.md)

---

## ファイル構成

新規:
- `app/Http/Requests/Api/V1/Event/IndexEventRequest.php` — クエリパラメータ検証

変更:
- `app/Http/Controllers/Api/V1/EventController.php` — index メソッドを拡張
- `tests/Feature/Api/V1/EventTest.php` — 検索テスト追加

---

## Task 1: IndexEventRequest を作成

**Files:**
- Create: `app/Http/Requests/Api/V1/Event/IndexEventRequest.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:request Api/V1/Event/IndexEventRequest --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Requests/Api/V1/Event/IndexEventRequest.php` を以下で完全に置き換える:

```php
<?php

namespace App\Http\Requests\Api\V1\Event;

use App\Enums\EventCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * イベント一覧の検索・フィルタパラメータ検証
 */
class IndexEventRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', Rule::enum(EventCategory::class)],
            'prefecture' => ['nullable', 'string', 'max:10'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 2: 検索テスト (キーワード q) を追加 — TDD で失敗を確認

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`tests/Feature/Api/V1/EventTest.php` の `EventTest` クラスの最後 (`}` 直前) に以下を追加:

```php
    /**
     * index: ?q=... で title 部分一致
     */
    public function test_index_filters_by_keyword_in_title(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Laravel 勉強会',
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Vue.js ハンズオン',
        ]);

        $response = $this->getJson('/api/v1/events?q=Laravel');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Laravel 勉強会');
    }
```

- [ ] **Step 2: テスト実行して失敗を確認**

```bash
php artisan test --compact --filter=test_index_filters_by_keyword_in_title
```

期待: FAIL (?q を無視して両方のイベントが返るため `assertJsonCount(1, 'data')` で失敗)

---

## Task 3: EventController::index を IndexEventRequest 受け取りに変更し、q フィルタを実装

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EventController.php`

- [ ] **Step 1: use 節に必要な import を追加**

`app/Http/Controllers/Api/V1/EventController.php` の use 節に以下を追加 (アルファベット順を維持):

```php
use App\Enums\EventCategory;
use App\Http\Requests\Api\V1\Event\IndexEventRequest;
use Illuminate\Support\Carbon;
```

(`App\Enums\EventStatus` は既存なので、`EventCategory` をその直後に追加)

- [ ] **Step 2: index メソッドを書き換え**

既存の `index` メソッド全体を以下で完全に置き換える:

```php
    /**
     * イベント一覧（Published のみ、event_date 昇順、ページネーション、検索フィルタ対応）
     *
     * 検索パラメータ:
     * - q: title/description の部分一致
     * - category: EventCategory 値で完全一致
     * - prefecture: 完全一致
     * - from: event_date >= from
     * - to: event_date <= to (日付のみは endOfDay 補完)
     */
    public function index(IndexEventRequest $request): AnonymousResourceCollection
    {
        $query = Event::query()
            ->with('user')
            ->where('status', EventStatus::Published);

        if ($q = $request->validated('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        if ($category = $request->validated('category')) {
            $query->where('category', EventCategory::from($category));
        }

        if ($prefecture = $request->validated('prefecture')) {
            $query->where('prefecture', $prefecture);
        }

        if ($from = $request->validated('from')) {
            $query->where('event_date', '>=', Carbon::parse($from));
        }

        if ($to = $request->validated('to')) {
            $toDate = Carbon::parse($to);
            if ($toDate->hour === 0 && $toDate->minute === 0 && $toDate->second === 0) {
                $toDate = $toDate->endOfDay();
            }
            $query->where('event_date', '<=', $toDate);
        }

        $events = $query->orderBy('event_date', 'asc')->paginate(self::PER_PAGE);

        return EventResource::collection($events);
    }
```

- [ ] **Step 3: テスト実行**

```bash
php artisan test --compact --filter=test_index_filters_by_keyword_in_title
```

期待: 1 test passed.

- [ ] **Step 4: 既存テストの回帰確認**

```bash
php artisan test --compact --filter='test_index_' tests/Feature/Api/V1/EventTest.php
```

期待: 既存 4 件 + 新規 1 件 = 5 tests passed (後続タスクでさらに増える)

- [ ] **Step 5: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 4: キーワード検索の追加テスト (description マッチ、ヒットなし)

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * index: ?q=... で description 部分一致
     */
    public function test_index_filters_by_keyword_in_description(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'もくもく会',
            'description' => 'Laravel について語ろう',
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => '一般イベント',
            'description' => '何かを学ぶ',
        ]);

        $response = $this->getJson('/api/v1/events?q=Laravel');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'もくもく会');
    }

    /**
     * index: ?q=... title と description どちらも該当しない
     */
    public function test_index_returns_empty_when_keyword_does_not_match(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Vue.js',
            'description' => 'Vue について',
        ]);

        $response = $this->getJson('/api/v1/events?q=NotMatchingKeyword');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_index_filters_by_keyword_in_description|test_index_returns_empty_when_keyword'
```

期待: 2 tests passed.

---

## Task 5: カテゴリフィルタのテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * index: ?category=N でカテゴリ一致のみ
     */
    public function test_index_filters_by_category(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'frontend-event',
            'category' => \App\Enums\EventCategory::Frontend,
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'backend-event',
            'category' => \App\Enums\EventCategory::Backend,
        ]);

        $response = $this->getJson('/api/v1/events?category=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'frontend-event');
    }

    /**
     * index: ?category=99 は 422
     */
    public function test_index_rejects_invalid_category(): void
    {
        $response = $this->getJson('/api/v1/events?category=99');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['category']);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_index_filters_by_category|test_index_rejects_invalid_category'
```

期待: 2 tests passed.

---

## Task 6: 都道府県フィルタのテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * index: ?prefecture=... で都道府県一致のみ
     */
    public function test_index_filters_by_prefecture(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => '東京イベント',
            'prefecture' => '東京都',
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => '大阪イベント',
            'prefecture' => '大阪府',
        ]);

        $response = $this->getJson('/api/v1/events?prefecture='.urlencode('東京都'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', '東京イベント');
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter=test_index_filters_by_prefecture
```

期待: 1 test passed.

---

## Task 7: 日付範囲フィルタのテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * index: ?from=... で指定日以降のイベントのみ
     */
    public function test_index_filters_by_from_date(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'sooner',
            'event_date' => now()->addDays(3),
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'later',
            'event_date' => now()->addDays(10),
        ]);

        $from = now()->addDays(5)->toIso8601String();

        $response = $this->getJson('/api/v1/events?from='.urlencode($from));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'later');
    }

    /**
     * index: ?to=... (日付のみ) で endOfDay 補完が効く
     */
    public function test_index_filters_by_to_date_with_end_of_day_completion(): void
    {
        $user = User::factory()->create();
        // to で指定する日の 15:00 開催のイベント
        $event = Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'same-day-afternoon',
            'event_date' => now()->addDays(5)->setTime(15, 0, 0),
        ]);
        // to の翌日のイベント
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'next-day',
            'event_date' => now()->addDays(6),
        ]);

        $to = now()->addDays(5)->toDateString(); // 日付のみ (YYYY-MM-DD)

        $response = $this->getJson('/api/v1/events?to='.$to);

        $response->assertOk();
        // endOfDay 補完が効けば same-day-afternoon (15:00) も含まれる
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'same-day-afternoon');
    }

    /**
     * index: ?from=...&to=... 範囲フィルタ
     */
    public function test_index_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'before-range',
            'event_date' => now()->addDays(1),
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'in-range',
            'event_date' => now()->addDays(5),
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'after-range',
            'event_date' => now()->addDays(10),
        ]);

        $from = now()->addDays(3)->toIso8601String();
        $to = now()->addDays(7)->toIso8601String();

        $response = $this->getJson('/api/v1/events?from='.urlencode($from).'&to='.urlencode($to));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'in-range');
    }

    /**
     * index: ?from=invalid は 422
     */
    public function test_index_rejects_invalid_from_date(): void
    {
        $response = $this->getJson('/api/v1/events?from=not-a-date');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['from']);
    }

    /**
     * index: to が from より前は 422
     */
    public function test_index_rejects_to_before_from(): void
    {
        $from = now()->addDays(7)->toIso8601String();
        $to = now()->addDays(3)->toIso8601String();

        $response = $this->getJson('/api/v1/events?from='.urlencode($from).'&to='.urlencode($to));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['to']);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_index_filters_by_from_date|test_index_filters_by_to_date_with_end_of_day_completion|test_index_filters_by_date_range|test_index_rejects_invalid_from_date|test_index_rejects_to_before_from'
```

期待: 5 tests passed.

---

## Task 8: 複数パラメータ AND 結合のテスト

**Files:**
- Modify: `tests/Feature/Api/V1/EventTest.php`

- [ ] **Step 1: テスト追加**

`EventTest` クラスの最後に以下を追加:

```php
    /**
     * index: 複数パラメータは AND 結合
     */
    public function test_index_combines_multiple_filters_with_and(): void
    {
        $user = User::factory()->create();
        // q と category 両方マッチ
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Laravel Frontend',
            'category' => \App\Enums\EventCategory::Frontend,
        ]);
        // q だけマッチ
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Laravel Backend',
            'category' => \App\Enums\EventCategory::Backend,
        ]);
        // category だけマッチ
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Vue Frontend',
            'category' => \App\Enums\EventCategory::Frontend,
        ]);

        $response = $this->getJson('/api/v1/events?q=Laravel&category=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Laravel Frontend');
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter=test_index_combines_multiple_filters_with_and
```

期待: 1 test passed.

---

## Task 9: 全テスト & 最終整形 & TASKS.md 更新

**Files:** なし (検証 + TASKS.md 更新)

- [ ] **Step 1: EventTest 全テストを通す**

```bash
php artisan test --compact tests/Feature/Api/V1/EventTest.php
```

期待: 38 tests passed (既存 27 + 検索 11)

内訳の検索系: q キーワード3 + category 2 + prefecture 1 + 日付5 + AND 1 = 12 ケース... 数え直し:

- Task 2: 1 (title マッチ)
- Task 4: 2 (description マッチ、ヒットなし)
- Task 5: 2 (category 一致、不正カテゴリ 422)
- Task 6: 1 (prefecture)
- Task 7: 5 (from / to-endOfDay / range / invalid-from / to-before-from)
- Task 8: 1 (AND 結合)

合計 = 1 + 2 + 2 + 1 + 5 + 1 = **12 件**

既存 27 + 検索 12 = **39 tests passed**

- [ ] **Step 2: API 全体テスト (回帰確認)**

```bash
php artisan test --compact tests/Feature/Api
```

期待: 18 (auth) + 39 (event: 27 既存 + 12 検索) + 25 (attendance) = **82 tests passed**

- [ ] **Step 3: Pint で整形違反ゼロを確認**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: TASKS.md を更新**

`TASKS.md` を編集する。

変更前の「実装中のタスク」セクション:

```markdown
## 📌 実装中のタスク

- [ ] 検索・フィルタリング機能実装：イベント一覧検索
```

変更後 (実装中のタスクは空に):

```markdown
## 📌 実装中のタスク

(全タスク完了)
```

「完了済み」セクション末尾に以下を追加:

```markdown
- [x] 検索・フィルタリング機能実装：イベント一覧検索
```

---

## 受け入れ基準

- [ ] `GET /api/v1/events` に q/category/prefecture/from/to の5パラメータが追加されている
- [ ] パラメータ未指定時は既存挙動と完全に同じ (回帰なし)
- [ ] 複数パラメータが AND 結合される
- [ ] 不正なパラメータは 422 で拒否される
- [ ] `to` の日付のみ指定は endOfDay 補完される
- [ ] `php artisan test --compact tests/Feature/Api/V1/EventTest.php` で 39 件すべて PASS する
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
- [ ] TASKS.md の検索・フィルタリング機能実装が完了に移動している
