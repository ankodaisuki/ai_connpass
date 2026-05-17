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
