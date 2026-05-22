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

    /** 開催中（開始済み・終了前）のイベントには途中参加できる */
    public function test_store_allows_application_during_ongoing_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subHour(),
            'end_date' => now()->addHour(),
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

    /** 出席済み（attended_at あり）の場合は開始後にキャンセルできない */
    public function test_destroy_fails_when_attended_after_event_start(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subDays(1),
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create([
            'attended_at' => now()->subDays(1),
        ]);

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }

    // ==========================================
    // update - 出欠管理（UC5）
    // ==========================================

    /** 主催者が参加者を「参加済み」にマークできる */
    public function test_organizer_can_mark_attendance_as_attended(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $organizer->id,
            'event_date' => now()->subHour(),
        ]);
        $attendance = EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'attended_at' => null,
        ]);

        $response = $this->actingAs($organizer)
            ->patch(route('events.attendances.update', [$event, $attendance]), [
                'attended_at' => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect(route('events.show', $event));
        $this->assertNotNull($attendance->refresh()->attended_at);
    }

    /** 主催者が参加済みを「未参加」にクリアできる */
    public function test_organizer_can_clear_attendance(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $organizer->id,
            'event_date' => now()->subHour(),
        ]);
        $attendance = EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'attended_at' => now(),
        ]);

        $response = $this->actingAs($organizer)
            ->patch(route('events.attendances.update', [$event, $attendance]), [
                'attended_at' => 'null',
            ]);

        $response->assertRedirect(route('events.show', $event));
        $this->assertNull($attendance->refresh()->attended_at);
    }

    /** 主催者以外は出欠記録を変更できない */
    public function test_non_organizer_cannot_mark_attendance(): void
    {
        $organizer = User::factory()->create();
        $other_user = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);
        $attendance = EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'attended_at' => null,
        ]);

        $response = $this->actingAs($other_user)
            ->patch(route('events.attendances.update', [$event, $attendance]), [
                'attended_at' => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertForbidden();
        $this->assertNull($attendance->refresh()->attended_at);
    }

    /** イベント開始前は主催者でも出欠を記録できない */
    public function test_organizer_cannot_mark_attendance_before_event_start(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $organizer->id,
            'event_date' => now()->addDays(3),
        ]);
        $attendance = EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'attended_at' => null,
        ]);

        $response = $this->actingAs($organizer)
            ->from(route('events.show', $event))
            ->patch(route('events.attendances.update', [$event, $attendance]), [
                'attended_at' => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect(route('events.show', $event));
        $response->assertSessionHasErrors(['attendance']);
        $this->assertNull($attendance->refresh()->attended_at);
    }

    /** 主催者はイベント詳細に出欠管理セクションが表示される */
    public function test_organizer_sees_attendance_section_on_event_detail(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
        ]);

        $response = $this->actingAs($organizer)
            ->get(route('events.show', $event));

        $response->assertSee('出欠管理');
        $response->assertSee($attendee->name);
    }

    /** 主催者以外には出欠管理セクションが表示されない */
    public function test_non_organizer_does_not_see_attendance_section(): void
    {
        $organizer = User::factory()->create();
        $other_user = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
        ]);

        $response = $this->actingAs($other_user)
            ->get(route('events.show', $event));

        $response->assertDontSee('出欠管理');
    }

    /** キャンセルした参加者はキャンセル一覧に表示される */
    public function test_cancelled_attendee_appears_in_cancelled_list(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $organizer->id]);
        $attendance = EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
        ]);

        $attendance->update(['status' => AttendanceStatus::Cancelled]);

        $response = $this->actingAs($organizer)
            ->get(route('events.show', $event));

        $response->assertSee('キャンセル一覧');
    }
}
