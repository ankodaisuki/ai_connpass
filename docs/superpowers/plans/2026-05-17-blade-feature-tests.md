# Blade フィーチャーテスト 実装プラン

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 削除した API テストと同等のカバレッジを持つ Blade フィーチャーテスト 73 件を 4 ファイルに作成する。

**Architecture:** 既存の Blade コントローラー実装に対してテストを追加する。JSON レスポンスではなく HTML レスポンス・リダイレクト・セッション・DB 変化を検証する。各ファイルは独立して作成・実行できる。

**Tech Stack:** PHPUnit 12, Laravel 13, RefreshDatabase, Factory states (inactive / draft / private / cancelled)

---

## Task 1: AuthTest.php（14 件）

**Files:**
- Create: `tests/Feature/AuthTest.php`

- [ ] **Step 1: テストファイルを作成**

```php
<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** 登録ページが表示される */
    public function test_register_page_is_accessible(): void
    {
        $this->get(route('register'))->assertStatus(200);
    }

    /** 正常登録でユーザー作成・ログイン状態・events.index へリダイレクト */
    public function test_register_creates_user_and_logs_in(): void
    {
        $response = $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('events.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'taro@example.com', 'name' => '山田 太郎']);
    }

    /** メール形式不正 → email バリデーションエラー */
    public function test_register_fails_with_invalid_email(): void
    {
        $this->post(route('register'), [
            'email' => 'not-an-email',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertSessionHasErrors(['email']);
    }

    /** 重複メール → email バリデーションエラー */
    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertSessionHasErrors(['email']);
    }

    /** パスワード 8 文字未満 → password バリデーションエラー */
    public function test_register_fails_with_short_password(): void
    {
        $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password']);
    }

    /** パスワード確認不一致 → password バリデーションエラー */
    public function test_register_fails_with_mismatched_password(): void
    {
        $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'different12',
        ])->assertSessionHasErrors(['password']);
    }

    /** 認証済みユーザーが /register にアクセス → リダイレクト（guest ミドルウェア） */
    public function test_authenticated_user_is_redirected_from_register(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('register'))->assertRedirect();
    }

    /** ログインページが表示される */
    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))->assertStatus(200);
    }

    /** 正しい認証情報でログイン → events.index へリダイレクト・認証済み */
    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'taro@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect(route('events.index'));
        $this->assertAuthenticatedAs($user);
    }

    /** 存在しないメールでログイン → email バリデーションエラー */
    public function test_login_fails_with_unknown_email(): void
    {
        $this->post(route('login'), [
            'email' => 'unknown@example.com',
            'password' => 'secret1234',
        ])->assertSessionHasErrors(['email']);
    }

    /** パスワード不一致でログイン → email バリデーションエラー */
    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email']);
    }

    /** Inactive ユーザーのログイン → 凍結エラー・ゲスト状態維持 */
    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'email' => 'frozen@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'frozen@example.com',
            'password' => 'secret1234',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    /** 認証済みユーザーが /login にアクセス → リダイレクト（guest ミドルウェア） */
    public function test_authenticated_user_is_redirected_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect();
    }

    /** ログアウト → ゲスト状態・events.index へリダイレクト */
    public function test_logout_logs_out_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))
            ->assertRedirect(route('events.index'));

        $this->assertGuest();
    }
}
```

- [ ] **Step 2: テストを実行して全件 PASS を確認**

```bash
php artisan test --compact tests/Feature/AuthTest.php
```

期待: 14 件すべて PASS

- [ ] **Step 3: Pint を実行してフォーマット**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add tests/Feature/AuthTest.php
git commit -m "テスト: 認証フィーチャーテストを追加（14件）"
```

---

## Task 2: EventTest.php（42 件）

**Files:**
- Create: `tests/Feature/EventTest.php`

- [ ] **Step 1: テストファイルを作成**

```php
<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // index
    // ==========================================

    /** イベント一覧ページが 200 を返す */
    public function test_index_returns_200(): void
    {
        $this->get(route('events.index'))->assertStatus(200);
    }

    /** Published のイベントのみ表示（Draft・Private は除外） */
    public function test_index_shows_only_published_events(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'published-event']);
        Event::factory()->for($user)->draft()->create(['title' => 'draft-event']);
        Event::factory()->for($user)->private()->create(['title' => 'private-event']);

        $response = $this->get(route('events.index'));

        $response->assertSee('published-event');
        $response->assertDontSee('draft-event');
        $response->assertDontSee('private-event');
    }

    /** ソフトデリート済みは表示されない */
    public function test_index_excludes_soft_deleted_events(): void
    {
        $user = User::factory()->create();
        $deleted = Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'deleted-event']);
        $deleted->delete();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'active-event']);

        $response = $this->get(route('events.index'));

        $response->assertSee('active-event');
        $response->assertDontSee('deleted-event');
    }

    /** event_date 昇順で表示（近い順） */
    public function test_index_sorts_by_event_date_ascending(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'later-event', 'event_date' => now()->addDays(10)]);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'sooner-event', 'event_date' => now()->addDays(2)]);

        $response = $this->get(route('events.index'));
        $content = $response->getContent();

        $this->assertLessThan(strpos($content, 'later-event'), strpos($content, 'sooner-event'));
    }

    /** 12 件/ページ（13件 → 1ページ目に 12件・2ページ目に 1件） */
    public function test_index_paginates_with_12_per_page(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 12; $i++) {
            Event::factory()->for($user)->create([
                'status' => EventStatus::Published,
                'title' => "event-{$i}",
                'event_date' => now()->addDays($i),
            ]);
        }
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'event-page-2',
            'event_date' => now()->addDays(13),
        ]);

        $this->get(route('events.index'))->assertDontSee('event-page-2');
        $this->get(route('events.index', ['page' => 2]))->assertSee('event-page-2');
    }

    /** ?q= でタイトル部分一致フィルタ */
    public function test_index_filters_by_keyword_in_title(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'Laravel 勉強会']);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'Vue.js ハンズオン']);

        $response = $this->get(route('events.index', ['q' => 'Laravel']));

        $response->assertSee('Laravel 勉強会');
        $response->assertDontSee('Vue.js ハンズオン');
    }

    /** ?q= で description 部分一致フィルタ */
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

        $response = $this->get(route('events.index', ['q' => 'Laravel']));

        $response->assertSee('もくもく会');
        $response->assertDontSee('一般イベント');
    }

    /** ?q= にマッチしない場合は結果なし */
    public function test_index_returns_empty_when_keyword_does_not_match(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'Vue.js']);

        $this->get(route('events.index', ['q' => 'NotMatchingKeyword']))
            ->assertDontSee('Vue.js');
    }

    /** ?category=N でカテゴリフィルタ */
    public function test_index_filters_by_category(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'frontend-event', 'category' => EventCategory::Frontend]);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'backend-event', 'category' => EventCategory::Backend]);

        $response = $this->get(route('events.index', ['category' => EventCategory::Frontend->value]));

        $response->assertSee('frontend-event');
        $response->assertDontSee('backend-event');
    }

    /** ?category=99 は無効値 → バリデーションエラー */
    public function test_index_rejects_invalid_category(): void
    {
        $this->get(route('events.index', ['category' => 99]))
            ->assertSessionHasErrors(['category']);
    }

    /** ?prefecture= で都道府県フィルタ */
    public function test_index_filters_by_prefecture(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => '東京イベント', 'prefecture' => '東京都']);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => '大阪イベント', 'prefecture' => '大阪府']);

        $response = $this->get(route('events.index', ['prefecture' => '東京都']));

        $response->assertSee('東京イベント');
        $response->assertDontSee('大阪イベント');
    }

    /** ?from= で指定日以降のみ表示 */
    public function test_index_filters_by_from_date(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'sooner', 'event_date' => now()->addDays(3)]);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'later', 'event_date' => now()->addDays(10)]);

        $response = $this->get(route('events.index', ['from' => now()->addDays(5)->toIso8601String()]));

        $response->assertSee('later');
        $response->assertDontSee('sooner');
    }

    /** ?to= に日付のみ指定すると endOfDay 補完が効く */
    public function test_index_filters_by_to_date_with_end_of_day_completion(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'same-day-afternoon',
            'event_date' => now()->addDays(5)->setTime(15, 0, 0),
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'next-day',
            'event_date' => now()->addDays(6)->setTime(9, 0, 0),
        ]);

        $response = $this->get(route('events.index', ['to' => now()->addDays(5)->toDateString()]));

        $response->assertSee('same-day-afternoon');
        $response->assertDontSee('next-day');
    }

    /** ?from=...&to=... で日付範囲フィルタ */
    public function test_index_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'before-range', 'event_date' => now()->addDays(1)]);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'in-range', 'event_date' => now()->addDays(5)]);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'after-range', 'event_date' => now()->addDays(10)]);

        $response = $this->get(route('events.index', [
            'from' => now()->addDays(3)->toIso8601String(),
            'to' => now()->addDays(7)->toIso8601String(),
        ]));

        $response->assertSee('in-range');
        $response->assertDontSee('before-range');
        $response->assertDontSee('after-range');
    }

    /** ?from=not-a-date は無効値 → バリデーションエラー */
    public function test_index_rejects_invalid_from_date(): void
    {
        $this->get(route('events.index', ['from' => 'not-a-date']))
            ->assertSessionHasErrors(['from']);
    }

    /** ?to が ?from より前 → バリデーションエラー */
    public function test_index_rejects_to_before_from(): void
    {
        $this->get(route('events.index', [
            'from' => now()->addDays(7)->toIso8601String(),
            'to' => now()->addDays(3)->toIso8601String(),
        ]))->assertSessionHasErrors(['to']);
    }

    /** 複数フィルタは AND 結合 */
    public function test_index_combines_multiple_filters_with_and(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'Laravel Frontend', 'category' => EventCategory::Frontend]);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'Laravel Backend', 'category' => EventCategory::Backend]);
        Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => 'Vue Frontend', 'category' => EventCategory::Frontend]);

        $response = $this->get(route('events.index', ['q' => 'Laravel', 'category' => EventCategory::Frontend->value]));

        $response->assertSee('Laravel Frontend');
        $response->assertDontSee('Laravel Backend');
        $response->assertDontSee('Vue Frontend');
    }

    // ==========================================
    // show
    // ==========================================

    /** Published イベントはゲストでも表示される */
    public function test_show_returns_200_for_published_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => EventStatus::Published]);

        $this->get(route('events.show', $event))->assertStatus(200);
    }

    /** イベント詳細ページにタイトルと主催者名が表示される */
    public function test_show_displays_event_info(): void
    {
        $user = User::factory()->create(['name' => '主催者']);
        $event = Event::factory()->for($user)->create(['status' => EventStatus::Published, 'title' => '表示テストイベント']);

        $response = $this->get(route('events.show', $event));

        $response->assertSee('表示テストイベント');
        $response->assertSee('主催者');
    }

    /** Draft イベントはゲストには 404 */
    public function test_show_returns_404_for_draft_to_guest(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->draft()->create();

        $this->get(route('events.show', $event))->assertNotFound();
    }

    /** Draft イベントはオーナーは閲覧可 */
    public function test_show_allows_owner_to_view_draft(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->draft()->create();

        $this->actingAs($owner)->get(route('events.show', $event))->assertStatus(200);
    }

    /** Private イベントは他人には 404 */
    public function test_show_returns_404_for_private_to_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($owner)->private()->create();

        $this->actingAs($other)->get(route('events.show', $event))->assertNotFound();
    }

    /** ソフトデリート済みイベントは 404 */
    public function test_show_returns_404_for_soft_deleted_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => EventStatus::Published]);
        $event->delete();

        $this->get(route('events.show', $event))->assertNotFound();
    }

    // ==========================================
    // create / store
    // ==========================================

    /** ゲストはイベント作成ページにアクセスできない */
    public function test_create_page_requires_auth(): void
    {
        $this->get(route('events.create'))->assertRedirect(route('login'));
    }

    /** 認証済みユーザーはイベント作成ページにアクセスできる */
    public function test_create_page_is_accessible_for_auth_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('events.create'))->assertStatus(200);
    }

    /** 認証済みユーザーがイベントを作成し show へリダイレクト */
    public function test_store_creates_event_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'テストイベント',
            'description' => 'テスト用です',
            'category' => EventCategory::Frontend->value,
            'prefecture' => '東京都',
            'location' => '渋谷区テスト1-1-1',
            'event_date' => now()->addDays(5)->toDateTimeString(),
            'capacity' => 30,
            'status' => EventStatus::Published->value,
        ];

        $response = $this->actingAs($user)->post(route('events.store'), $payload);

        $event = Event::where('title', 'テストイベント')->firstOrFail();
        $response->assertRedirect(route('events.show', $event));
        $this->assertDatabaseHas('events', ['title' => 'テストイベント', 'user_id' => $user->id]);
    }

    /** status 省略時は Draft で作成される */
    public function test_store_defaults_status_to_draft_when_omitted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'title' => 'ドラフトイベント',
            'category' => EventCategory::Frontend->value,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toDateTimeString(),
            'capacity' => 10,
        ]);

        $this->assertDatabaseHas('events', [
            'title' => 'ドラフトイベント',
            'status' => EventStatus::Draft->value,
        ]);
    }

    /** ゲストはイベント作成できない */
    public function test_store_requires_auth(): void
    {
        $this->post(route('events.store'), [])->assertRedirect(route('login'));
    }

    /** title 未入力 → title バリデーションエラー */
    public function test_store_fails_when_title_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'category' => EventCategory::Frontend->value,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toDateTimeString(),
            'capacity' => 10,
        ])->assertSessionHasErrors(['title']);
    }

    /** event_date が過去 → event_date バリデーションエラー */
    public function test_store_fails_when_event_date_is_in_the_past(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'title' => 'タイトル',
            'category' => EventCategory::Frontend->value,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->subDays(1)->toDateTimeString(),
            'capacity' => 10,
        ])->assertSessionHasErrors(['event_date']);
    }

    /** capacity が 0 以下 → capacity バリデーションエラー */
    public function test_store_fails_when_capacity_is_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'title' => 'タイトル',
            'category' => EventCategory::Frontend->value,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toDateTimeString(),
            'capacity' => 0,
        ])->assertSessionHasErrors(['capacity']);
    }

    /** 無効な category 値 → category バリデーションエラー */
    public function test_store_fails_when_category_is_invalid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'title' => 'タイトル',
            'category' => 99,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toDateTimeString(),
            'capacity' => 10,
        ])->assertSessionHasErrors(['category']);
    }

    // ==========================================
    // edit / update
    // ==========================================

    /** ゲストはイベント編集ページにアクセスできない */
    public function test_edit_page_requires_auth(): void
    {
        $event = Event::factory()->for(User::factory())->create();

        $this->get(route('events.edit', $event))->assertRedirect(route('login'));
    }

    /** 非オーナーはイベント編集ページにアクセスできない */
    public function test_edit_page_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('events.edit', $event))->assertForbidden();
    }

    /** オーナーはイベント編集ページにアクセスできる */
    public function test_edit_page_is_accessible_for_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('events.edit', $event))->assertStatus(200);
    }

    /** オーナーがイベントを更新し show へリダイレクト */
    public function test_update_succeeds_for_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->put(route('events.update', $event), [
            'title' => '更新後のタイトル',
            'description' => '更新後の説明',
            'category' => EventCategory::Backend->value,
            'prefecture' => '大阪府',
            'location' => '大阪市テスト1-1-1',
            'event_date' => now()->addDays(7)->toDateTimeString(),
            'capacity' => 50,
            'status' => EventStatus::Published->value,
        ]);

        $response->assertRedirect(route('events.show', $event));
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => '更新後のタイトル', 'prefecture' => '大阪府']);
    }

    /** 非オーナーは更新できない */
    public function test_update_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($other)->put(route('events.update', $event), [
            'title' => 'タイトル',
            'category' => EventCategory::Frontend->value,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toDateTimeString(),
            'capacity' => 10,
            'status' => EventStatus::Published->value,
        ])->assertForbidden();
    }

    /** ゲストは更新できない */
    public function test_update_requires_auth(): void
    {
        $event = Event::factory()->for(User::factory())->create();

        $this->put(route('events.update', $event), [])->assertRedirect(route('login'));
    }

    /** ソフトデリート済みイベントの更新は 404 */
    public function test_update_returns_404_for_soft_deleted_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();
        $event->delete();

        $this->actingAs($owner)->put(route('events.update', $event), [
            'title' => 'タイトル',
            'category' => EventCategory::Frontend->value,
            'prefecture' => '東京都',
            'location' => '渋谷区',
            'event_date' => now()->addDays(5)->toDateTimeString(),
            'capacity' => 10,
            'status' => EventStatus::Published->value,
        ])->assertNotFound();
    }

    // ==========================================
    // destroy
    // ==========================================

    /** オーナーが削除 → soft delete + status=Private・index へリダイレクト */
    public function test_destroy_soft_deletes_and_sets_status_to_private(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->delete(route('events.destroy', $event));

        $response->assertRedirect(route('events.index'));
        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => EventStatus::Private->value]);
    }

    /** ゲストはイベントを削除できない */
    public function test_destroy_requires_auth(): void
    {
        $event = Event::factory()->for(User::factory())->create();

        $this->delete(route('events.destroy', $event))->assertRedirect(route('login'));
    }

    /** 非オーナーはイベントを削除できない */
    public function test_destroy_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($other)->delete(route('events.destroy', $event))->assertForbidden();
    }
}
```

- [ ] **Step 2: テストを実行して全件 PASS を確認**

```bash
php artisan test --compact tests/Feature/EventTest.php
```

期待: 42 件すべて PASS

- [ ] **Step 3: Pint を実行してフォーマット**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add tests/Feature/EventTest.php
git commit -m "テスト: イベントフィーチャーテストを追加（42件）"
```

---

## Task 3: EventAttendanceTest.php（12 件）

**Files:**
- Create: `tests/Feature/EventAttendanceTest.php`

- [ ] **Step 1: テストファイルを作成**

```php
<?php

namespace Tests\Feature;

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

    // ==========================================
    // store - 申し込み
    // ==========================================

    /** 認証ユーザーが申し込むと Applied で DB に保存・success フラッシュ */
    public function test_store_creates_applied_attendance(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'capacity' => 10,
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Applied->value,
        ]);
    }

    /** キャンセル後の再申し込みは既存レコードを Applied に更新（新規作成しない） */
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

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event));

        $this->assertDatabaseHas('event_attendances', [
            'id' => $cancelled->id,
            'status' => AttendanceStatus::Applied->value,
            'cancelled_at' => null,
        ]);
        $this->assertDatabaseCount('event_attendances', 1);
    }

    /** ゲストは申し込みできない（login へリダイレクト） */
    public function test_store_requires_auth(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $this->post(route('events.attendances.store', $event))
            ->assertRedirect(route('login'));
    }

    /** Draft イベントへの申し込みは 404 */
    public function test_store_returns_404_for_draft_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->draft()->create();
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->post(route('events.attendances.store', $event))
            ->assertNotFound();
    }

    /** 過去イベントへの申し込みはエラー */
    public function test_store_fails_for_past_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subDays(1),
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }

    /** イベント作成者本人は申し込みできない */
    public function test_store_fails_for_event_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
        ]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }

    /** 定員オーバーは申し込みエラー */
    public function test_store_fails_when_capacity_is_full(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'capacity' => 2,
        ]);
        $others = User::factory()->count(2)->create();
        foreach ($others as $other) {
            EventAttendance::factory()->for($event)->for($other)->create();
        }
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }

    /** 重複申し込みはエラー */
    public function test_store_fails_when_already_applied(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }

    // ==========================================
    // destroy - キャンセル
    // ==========================================

    /** キャンセルすると Cancelled・cancelled_at がセットされ success フラッシュ */
    public function test_destroy_cancels_attendance(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
        ]);
        $applicant = User::factory()->create();
        $attendance = EventAttendance::factory()->for($event)->for($applicant)->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'id' => $attendance->id,
            'status' => AttendanceStatus::Cancelled->value,
        ]);
        $this->assertNotNull($attendance->fresh()->cancelled_at);
    }

    /** ゲストはキャンセルできない（login へリダイレクト） */
    public function test_destroy_requires_auth(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $this->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('login'));
    }

    /** 申し込みしていないユーザーがキャンセルするとエラー */
    public function test_destroy_fails_when_not_applied(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }

    /** 過去イベントのキャンセルはエラー */
    public function test_destroy_fails_for_past_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subDays(1),
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }
}
```

- [ ] **Step 2: テストを実行して全件 PASS を確認**

```bash
php artisan test --compact tests/Feature/EventAttendanceTest.php
```

期待: 12 件すべて PASS

- [ ] **Step 3: Pint を実行してフォーマット**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add tests/Feature/EventAttendanceTest.php
git commit -m "テスト: イベント参加フィーチャーテストを追加（12件）"
```

---

## Task 4: MyAttendanceTest.php（5 件）

**Files:**
- Create: `tests/Feature/MyAttendanceTest.php`

- [ ] **Step 1: テストファイルを作成**

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** ゲストはマイページにアクセスできない（login へリダイレクト） */
    public function test_index_requires_auth(): void
    {
        $this->get(route('my.attendances'))->assertRedirect(route('login'));
    }

    /** 認証済みユーザーはマイページに 200 でアクセスできる */
    public function test_index_returns_200_for_auth_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('my.attendances'))->assertStatus(200);
    }

    /** 自分の Applied 参加のみ表示（Cancelled・他人の参加は除外） */
    public function test_index_shows_only_own_applied_attendances(): void
    {
        $user = User::factory()->create();
        $organizer = User::factory()->create();
        $other = User::factory()->create();

        $event1 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'my-applied-event']);
        $event2 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'my-cancelled-event']);
        $event3 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'others-event']);

        // 自分の Applied
        EventAttendance::factory()->for($event1)->for($user)->create();
        // 自分の Cancelled（除外される）
        EventAttendance::factory()->for($event2)->for($user)->cancelled()->create();
        // 他人の Applied（除外される）
        EventAttendance::factory()->for($event3)->for($other)->create();

        $response = $this->actingAs($user)->get(route('my.attendances'));

        $response->assertSee('my-applied-event');
        $response->assertDontSee('my-cancelled-event');
        $response->assertDontSee('others-event');
    }

    /** 15 件/ページ（16件 → 1ページ目に 15件・2ページ目に 1件） */
    public function test_index_paginates_with_15_per_page(): void
    {
        $user = User::factory()->create();
        $organizer = User::factory()->create();

        for ($i = 1; $i <= 15; $i++) {
            $event = Event::factory()->for($organizer)->create([
                'status' => EventStatus::Published,
                'title' => "event-{$i}",
            ]);
            EventAttendance::factory()->for($event)->for($user)->create([
                'applied_at' => now()->addMinutes($i),
            ]);
        }

        $event16 = Event::factory()->for($organizer)->create([
            'status' => EventStatus::Published,
            'title' => 'event-page-2',
        ]);
        EventAttendance::factory()->for($event16)->for($user)->create([
            'applied_at' => now()->addMinutes(16),
        ]);

        $this->actingAs($user)->get(route('my.attendances'))->assertDontSee('event-page-2');
        $this->actingAs($user)->get(route('my.attendances', ['page' => 2]))->assertSee('event-page-2');
    }

    /** applied_at 昇順で表示（早い申込が先頭） */
    public function test_index_sorts_by_applied_at_ascending(): void
    {
        $user = User::factory()->create();
        $organizer = User::factory()->create();

        $event1 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'later-applied']);
        EventAttendance::factory()->for($event1)->for($user)->create(['applied_at' => now()->addHours(2)]);

        $event2 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'sooner-applied']);
        EventAttendance::factory()->for($event2)->for($user)->create(['applied_at' => now()->addHours(1)]);

        $response = $this->actingAs($user)->get(route('my.attendances'));
        $content = $response->getContent();

        $this->assertLessThan(strpos($content, 'later-applied'), strpos($content, 'sooner-applied'));
    }
}
```

- [ ] **Step 2: テストを実行して全件 PASS を確認**

```bash
php artisan test --compact tests/Feature/MyAttendanceTest.php
```

期待: 5 件すべて PASS

- [ ] **Step 3: Pint を実行してフォーマット**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add tests/Feature/MyAttendanceTest.php
git commit -m "テスト: マイ参加一覧フィーチャーテストを追加（5件）"
```

---

## Task 5: 全テスト実行・TASKS.md 更新

- [ ] **Step 1: 全テストスイートを実行**

```bash
php artisan test --compact
```

期待: 既存 10 件 + 新規 73 件 = 合計 83 件すべて PASS

- [ ] **Step 2: TASKS.md の Blade テストタスクを完了に更新**

`TASKS.md` の `🧪 Blade フィーチャーテスト新規作成` セクションの各チェックボックスを `[x]` に変更し、完了済みセクションに追記する。

- [ ] **Step 3: コミット**

```bash
git add TASKS.md
git commit -m "TASKS.md更新：Bladeフィーチャーテスト完了（73件）"
```
