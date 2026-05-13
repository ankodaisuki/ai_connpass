<?php

namespace Tests\Feature\Api\V1;

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
}
