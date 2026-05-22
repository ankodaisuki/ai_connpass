<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class EventAttendanceService
{
    public function __construct(private readonly GoogleCalendarService $googleCalendarService) {}

    /**
     * イベントへの参加申し込み
     *
     * @throws AttendanceException
     */
    public function apply(Event $event, User $user): void
    {
        if ($event->end_date->isPast()) {
            throw new AttendanceException('このイベントはすでに終了しています。');
        }

        if ($event->user_id === $user->id) {
            throw new AttendanceException('自分のイベントには申し込めません。');
        }

        $existing = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null && $existing->status === AttendanceStatus::Applied) {
            throw new AttendanceException('すでに申し込み済みです。');
        }

        $appliedCount = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->count();

        if ($appliedCount >= $event->capacity) {
            throw new AttendanceException('定員に達しています。');
        }

        if ($existing !== null) {
            $existing->update([
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
                'cancelled_at' => null,
            ]);
            $attendance = $existing;
        } else {
            $attendance = EventAttendance::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
            ]);
        }

        $this->syncCalendarOnApply($event, $user, $attendance);
    }

    /**
     * イベント参加のキャンセル
     *
     * @throws AttendanceException
     */
    public function cancel(Event $event, User $user): void
    {
        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', AttendanceStatus::Applied)
            ->first();

        if ($attendance === null) {
            throw new AttendanceException('申し込みが見つかりません。');
        }

        if ($event->event_date->isPast() && $attendance->attended_at !== null) {
            throw new AttendanceException('出席済みのためキャンセルできません。');
        }

        if ($event->event_date->isPast()) {
            throw new AttendanceException('このイベントはすでに開始しています。');
        }

        $attendance->update([
            'status' => AttendanceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->syncCalendarOnCancel($user, $attendance);
    }

    private function syncCalendarOnApply(Event $event, User $user, EventAttendance $attendance): void
    {
        if (! $user->hasGoogleCalendarConnected()) {
            return;
        }

        try {
            $googleEventId = $this->googleCalendarService->createEvent($user, $event);
            if ($googleEventId !== null) {
                $attendance->update(['google_calendar_event_id' => $googleEventId]);
            }
        } catch (\Throwable $e) {
            Log::warning('Googleカレンダー登録に失敗', ['user_id' => $user->id, 'event_id' => $event->id, 'error' => $e->getMessage()]);
        }
    }

    private function syncCalendarOnCancel(User $user, EventAttendance $attendance): void
    {
        $googleEventId = $attendance->google_calendar_event_id;
        if ($googleEventId === null || ! $user->hasGoogleCalendarConnected()) {
            return;
        }

        try {
            $this->googleCalendarService->deleteEvent($user, $googleEventId);
            $attendance->update(['google_calendar_event_id' => null]);
        } catch (\Throwable $e) {
            Log::warning('Googleカレンダー削除に失敗', ['user_id' => $user->id, 'attendance_id' => $attendance->id, 'error' => $e->getMessage()]);
        }
    }
}
