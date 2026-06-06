<?php

namespace Tests\Feature;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Mail\WaitlistConfirmationMail;
use App\Mail\WaitlistPromotedMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    /** 定員オーバー時はキャンセル待ちに登録される */
    public function test_store_registers_waitlist_when_capacity_is_full(): void
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
            ->assertSessionHas('success', 'キャンセル待ちに登録しました。');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Waitlisted->value,
        ]);
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

    /** 出席済み（attended_at あり）の場合はイベント終了前でもキャンセルできない */
    public function test_destroy_fails_when_attended_after_event_start(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create([
            'attended_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);
    }

    /** イベント終了後はキャンセル待ちユーザーもキャンセルできない */
    public function test_destroy_fails_for_waitlisted_user_after_event_ends(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->subHours(3),
            'end_date' => now()->subHour(),
        ]);
        $applicant = User::factory()->create();
        $attendance = EventAttendance::factory()->for($event)->for($applicant)->waitlisted()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors(['attendance']);

        $this->assertSame(AttendanceStatus::Waitlisted, $attendance->fresh()->status);
    }

    /** イベント終了前はキャンセル待ちユーザーもキャンセルできる */
    public function test_destroy_succeeds_for_waitlisted_user_before_event_ends(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
        ]);
        $applicant = User::factory()->create();
        $attendance = EventAttendance::factory()->for($event)->for($applicant)->waitlisted()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $this->assertSame(AttendanceStatus::Cancelled, $attendance->fresh()->status);
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

    /** イベント終了後は主催者でも出欠を記録できない */
    public function test_organizer_cannot_mark_attendance_after_event_ends(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $organizer->id,
            'event_date' => now()->subHours(3),
            'end_date' => now()->subHour(),
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

    // ==========================================
    // waitlist - キャンセル待ち登録
    // ==========================================

    /** 満員時に申し込むとキャンセル待ちに登録され flash に success が入る */
    public function test_store_registers_waitlist_when_event_is_full(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 2,
        ]);
        EventAttendance::factory()->for($event)->count(2)->create(['status' => AttendanceStatus::Applied]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
            'status' => AttendanceStatus::Waitlisted->value,
        ]);
    }

    /** flash メッセージが Applied と Waitlisted で異なる */
    public function test_store_flash_message_differs_between_applied_and_waitlisted(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 1,
        ]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHas('success', '参加申し込みが完了しました。');

        $this->actingAs($secondUser)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHas('success', 'キャンセル待ちに登録しました。');
    }

    /** キャンセル待ちも満員の場合は登録拒否（エラー表示） */
    public function test_store_rejects_when_waitlist_is_also_full(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 2,
        ]);
        EventAttendance::factory()->for($event)->count(2)->create(['status' => AttendanceStatus::Applied]);
        EventAttendance::factory()->for($event)->count(2)->waitlisted()->create();
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasErrors('attendance');

        $this->assertDatabaseMissing('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $applicant->id,
        ]);
    }

    /** すでにキャンセル待ち登録済みの場合は重複登録拒否 */
    public function test_store_rejects_duplicate_waitlist(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 1,
        ]);
        EventAttendance::factory()->for($event)->create(['status' => AttendanceStatus::Applied]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->waitlisted()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasErrors('attendance');
    }

    // ==========================================
    // waitlist - 自動昇格
    // ==========================================

    /** Applied キャンセル時にキャンセル待ち最古のユーザーが Applied に昇格する */
    public function test_cancel_promotes_oldest_waitlisted_user_when_applied_user_cancels(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 1,
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create(['status' => AttendanceStatus::Applied]);

        $firstWaiter = User::factory()->create();
        $secondWaiter = User::factory()->create();
        EventAttendance::factory()->for($event)->for($firstWaiter)->create([
            'status' => AttendanceStatus::Waitlisted,
            'waitlisted_at' => now()->subMinutes(10),
        ]);
        EventAttendance::factory()->for($event)->for($secondWaiter)->create([
            'status' => AttendanceStatus::Waitlisted,
            'waitlisted_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event));

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $firstWaiter->id,
            'status' => AttendanceStatus::Applied->value,
        ]);
        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $secondWaiter->id,
            'status' => AttendanceStatus::Waitlisted->value,
        ]);
    }

    /** キャンセル待ちが存在しない場合は昇格処理が何も起こさない */
    public function test_cancel_does_nothing_when_no_waitlist_exists(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 2,
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create(['status' => AttendanceStatus::Applied]);

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event))
            ->assertRedirect();

        $this->assertDatabaseCount('event_attendances', 1);
    }

    /** Waitlisted キャンセル時は自動昇格が発生しない */
    public function test_cancel_waitlist_does_not_trigger_promotion(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 1,
        ]);
        EventAttendance::factory()->for($event)->create(['status' => AttendanceStatus::Applied]);
        $waiter1 = User::factory()->create();
        $waiter2 = User::factory()->create();
        EventAttendance::factory()->for($event)->for($waiter1)->create([
            'status' => AttendanceStatus::Waitlisted,
            'waitlisted_at' => now()->subMinutes(10),
        ]);
        EventAttendance::factory()->for($event)->for($waiter2)->create([
            'status' => AttendanceStatus::Waitlisted,
            'waitlisted_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($waiter1)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event));

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $waiter2->id,
            'status' => AttendanceStatus::Waitlisted->value,
        ]);
    }

    // ==========================================
    // waitlist - メール通知
    // ==========================================

    /** キャンセル待ち登録時に WaitlistConfirmationMail が送信される */
    public function test_waitlist_confirmation_mail_is_sent_on_registration(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 1,
        ]);
        EventAttendance::factory()->for($event)->create(['status' => AttendanceStatus::Applied]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event));

        Mail::assertSent(WaitlistConfirmationMail::class, function (WaitlistConfirmationMail $mail) use ($applicant, $event): bool {
            return $mail->hasTo($applicant->email)
                && $mail->event->id === $event->id
                && $mail->position === 1;
        });
    }

    /** Applied キャンセル時に昇格したユーザーへ WaitlistPromotedMail が送信される */
    public function test_waitlist_promoted_mail_is_sent_on_promotion(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 1,
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create(['status' => AttendanceStatus::Applied]);
        $waiter = User::factory()->create();
        EventAttendance::factory()->for($event)->for($waiter)->waitlisted()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event));

        Mail::assertSent(WaitlistPromotedMail::class, function (WaitlistPromotedMail $mail) use ($waiter, $event): bool {
            return $mail->hasTo($waiter->email)
                && $mail->event->id === $event->id;
        });
    }

    /** Applied キャンセルでキャンセル待ちがいない場合はメールが送信されない */
    public function test_no_promotion_mail_when_no_waitlist(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 2,
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create(['status' => AttendanceStatus::Applied]);

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event));

        Mail::assertNotSent(WaitlistPromotedMail::class);
    }

    // ==========================================
    // waitlist - Google カレンダー連携
    // ==========================================

    /** ハイブリッドイベントで attendance_mode を指定して申し込めば attendance_mode が保存される */
    public function test_store_saves_attendance_mode_for_hybrid_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->hybrid()->for($owner)->create([
            'status' => EventStatus::Published,
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event), [
                'attendance_mode' => 'online',
            ])
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame(AttendanceMode::Online, $attendance->attendance_mode);
    }

    /** ハイブリッドイベントで attendance_mode なしの申し込みは失敗する */
    public function test_store_fails_without_attendance_mode_for_hybrid_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->hybrid()->for($owner)->create([
            'status' => EventStatus::Published,
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasErrors(['attendance_mode']);
    }

    /** 対面イベントの申し込みは attendance_mode が in_person に自動セットされる */
    public function test_store_auto_sets_in_person_mode_for_in_person_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'prefecture' => '東京都',
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasNoErrors();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertSame(AttendanceMode::InPerson, $attendance->attendance_mode);
    }

    /** オンラインイベントの申し込みは attendance_mode が online に自動セットされる */
    public function test_store_auto_sets_online_mode_for_online_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->online()->for($owner)->create([
            'status' => EventStatus::Published,
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasNoErrors();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertSame(AttendanceMode::Online, $attendance->attendance_mode);
    }

    /** 昇格時に Google カレンダーへ予定が追加される */
    public function test_promotion_adds_event_to_google_calendar(): void
    {
        Mail::fake();

        $calendarService = $this->mock(GoogleCalendarService::class);
        $calendarService->shouldReceive('createEvent')
            ->once()
            ->andReturn('google-event-id-123');

        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'event_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHours(2),
            'capacity' => 1,
        ]);
        $applicant = User::factory()->create();
        EventAttendance::factory()->for($event)->for($applicant)->create(['status' => AttendanceStatus::Applied]);

        $waiter = User::factory()->create();
        EventAttendance::factory()->for($event)->for($waiter)->waitlisted()->create();

        // waiter が Google カレンダー連携済みであることを設定
        GoogleCalendarToken::factory()->for($waiter)->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->delete(route('events.attendances.destroy', $event));

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'user_id' => $waiter->id,
            'status' => AttendanceStatus::Applied->value,
            'google_calendar_event_id' => 'google-event-id-123',
        ]);
    }
}
