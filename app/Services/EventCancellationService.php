<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EventCancellationService
{
    /**
     * イベントを中止扱いにする。
     * Private 化 → ソフトデリート → 申込中/キャンセル待ちの参加者へ中止メール。
     */
    public function cancel(Event $event): void
    {
        $attendees = $event->attendances()
            ->with('user')
            ->whereIn('status', [AttendanceStatus::Applied, AttendanceStatus::Waitlisted])
            ->get()
            ->map(fn (EventAttendance $a) => $a->user);

        $event->update(['status' => EventStatus::Private]);
        $event->delete();

        foreach ($attendees as $attendee) {
            try {
                Mail::to($attendee->email)->send(new EventCancelledMail($event));
            } catch (\Throwable $e) {
                Log::warning('イベント中止通知メール送信に失敗', [
                    'user_id' => $attendee->id,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
