<?php

use App\Enums\AttendanceStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAttendance(Event $event, AttendanceStatus $status): EventAttendance
{
    return EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'status' => $status,
        'applied_at' => $status === AttendanceStatus::Applied ? now() : null,
        'waitlisted_at' => $status === AttendanceStatus::Waitlisted ? now() : null,
    ]);
}

test('整合な状態なら成功する（定員ちょうど＋キャンセル待ち）', function () {
    $event = Event::factory()->create(['capacity' => 2]);
    makeAttendance($event, AttendanceStatus::Applied);
    makeAttendance($event, AttendanceStatus::Applied);
    makeAttendance($event, AttendanceStatus::Waitlisted);

    $this->artisan('perf:verify', ['event' => $event->id])
        ->expectsOutputToContain('整合性OK')
        ->assertSuccessful();
});

test('定員超過を検出して失敗する', function () {
    $event = Event::factory()->create(['capacity' => 1]);
    makeAttendance($event, AttendanceStatus::Applied);
    makeAttendance($event, AttendanceStatus::Applied); // 超過

    $this->artisan('perf:verify', ['event' => $event->id])
        ->expectsOutputToContain('定員超過')
        ->assertFailed();
});

test('空席があるのにキャンセル待ちが残っていれば繰り上げ漏れとして失敗する', function () {
    $event = Event::factory()->create(['capacity' => 2]);
    makeAttendance($event, AttendanceStatus::Applied); // 空席1
    makeAttendance($event, AttendanceStatus::Waitlisted);

    $this->artisan('perf:verify', ['event' => $event->id])
        ->expectsOutputToContain('繰り上げ漏れ')
        ->assertFailed();
});
