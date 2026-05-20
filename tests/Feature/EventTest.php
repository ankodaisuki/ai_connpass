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
        ])->assertSessionHasErrors(['title' => 'タイトルは必須です。']);
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
