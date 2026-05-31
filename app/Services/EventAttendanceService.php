<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use App\Mail\WaitlistConfirmationMail;
use App\Mail\WaitlistPromotedMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EventAttendanceService
{
    public function __construct(private readonly GoogleCalendarService $googleCalendarService) {}

    /**
     * イベントへの参加申し込み
     *
     * @throws AttendanceException
     */
    public function apply(Event $event, User $user): AttendanceStatus
    {
        if ($event->end_date->isPast()) {
            throw new AttendanceException('このイベントはすでに終了しています。');
        }

        if ($event->user_id === $user->id) {
            throw new AttendanceException('自分のイベントには申し込めません。');
        }

        return DB::transaction(function () use ($event, $user) {
            // イベント行をロックして同時申し込みを直列化する
            $event = Event::where('id', $event->id)->lockForUpdate()->first();

            $existing = EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing !== null && $existing->status === AttendanceStatus::Applied) {
                throw new AttendanceException('すでに申し込み済みです。');
            }

            if ($existing !== null && $existing->status === AttendanceStatus::Waitlisted) {
                throw new AttendanceException('すでにキャンセル待ちに登録されています。');
            }

            $appliedCount = EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('status', AttendanceStatus::Applied)
                ->count();

            if ($appliedCount >= $event->capacity) {
                return $this->waitlistApply($event, $user, $existing);
            }

            if ($existing !== null) {
                $existing->update([
                    'status' => AttendanceStatus::Applied,
                    'applied_at' => now(),
                    'cancelled_at' => null,
                    'waitlisted_at' => null,
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

            return AttendanceStatus::Applied;
        });
    }

    /**
     * キャンセル待ちに登録する
     *
     * @throws AttendanceException
     */
    private function waitlistApply(Event $event, User $user, ?EventAttendance $existing): AttendanceStatus
    {
        $waitlistedCount = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Waitlisted)
            ->count();

        if ($waitlistedCount >= $event->capacity) {
            throw new AttendanceException('キャンセル待ちも満員です。');
        }

        if ($existing !== null) {
            $existing->update([
                'status' => AttendanceStatus::Waitlisted,
                'waitlisted_at' => now(),
                'applied_at' => null,
                'cancelled_at' => null,
            ]);
            $attendance = $existing;
        } else {
            $attendance = EventAttendance::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => AttendanceStatus::Waitlisted,
                'waitlisted_at' => now(),
            ]);
        }

        $position = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Waitlisted)
            ->where('waitlisted_at', '<=', $attendance->waitlisted_at)
            ->count();

        Mail::to($user)->send(new WaitlistConfirmationMail($event, $position));

        return AttendanceStatus::Waitlisted;
    }

    /**
     * イベント参加のキャンセル（Applied / Waitlisted どちらも対応）
     *
     * @throws AttendanceException
     */
    public function cancel(Event $event, User $user): void
    {
        DB::transaction(function () use ($event, $user) {
            $attendance = EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->whereIn('status', [AttendanceStatus::Applied, AttendanceStatus::Waitlisted])
                ->lockForUpdate()
                ->first();

            if ($attendance === null) {
                throw new AttendanceException('申し込みが見つかりません。');
            }

            $wasApplied = $attendance->status === AttendanceStatus::Applied;

            if ($wasApplied) {
                if ($event->event_date->isPast() && $attendance->attended_at !== null) {
                    throw new AttendanceException('出席済みのためキャンセルできません。');
                }

                if ($event->event_date->isPast()) {
                    throw new AttendanceException('このイベントはすでに開始しています。');
                }
            }

            $attendance->update([
                'status' => AttendanceStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            if ($wasApplied) {
                $this->syncCalendarOnCancel($user, $attendance);
                $this->promoteFromWaitlist($event);
            }
        });
    }

    /**
     * キャンセル待ち最古のユーザーを Applied に昇格する
     */
    private function promoteFromWaitlist(Event $event): void
    {
        $waitlisted = EventAttendance::query()
            ->with('user')
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Waitlisted)
            ->orderBy('waitlisted_at', 'asc')
            ->lockForUpdate()
            ->first();

        if ($waitlisted === null) {
            return;
        }

        $waitlisted->update([
            'status' => AttendanceStatus::Applied,
            'applied_at' => now(),
            'waitlisted_at' => null,
        ]);

        Mail::to($waitlisted->user)->send(new WaitlistPromotedMail($event));
        $this->syncCalendarOnApply($event, $waitlisted->user, $waitlisted);
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
