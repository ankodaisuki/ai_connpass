<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
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
     * index: ?q=... 該当なし
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

    /**
     * index: ?category=N でカテゴリ一致のみ
     */
    public function test_index_filters_by_category(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'frontend-event',
            'category' => EventCategory::Frontend,
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'backend-event',
            'category' => EventCategory::Backend,
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
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'same-day-afternoon',
            'event_date' => now()->addDays(5)->setTime(15, 0, 0),
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'next-day',
            'event_date' => now()->addDays(6),
        ]);

        $to = now()->addDays(5)->toDateString();

        $response = $this->getJson('/api/v1/events?to='.$to);

        $response->assertOk();
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

    /**
     * index: 複数パラメータは AND 結合
     */
    public function test_index_combines_multiple_filters_with_and(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Laravel Frontend',
            'category' => EventCategory::Frontend,
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Laravel Backend',
            'category' => EventCategory::Backend,
        ]);
        Event::factory()->for($user)->create([
            'status' => EventStatus::Published,
            'title' => 'Vue Frontend',
            'category' => EventCategory::Frontend,
        ]);

        $response = $this->getJson('/api/v1/events?q=Laravel&category=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Laravel Frontend');
    }
}
