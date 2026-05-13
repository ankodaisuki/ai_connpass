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
}
