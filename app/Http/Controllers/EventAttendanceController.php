<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EventAttendanceController extends Controller
{
    /**
     * イベント申し込み
     */
    public function store(Event $event): RedirectResponse
    {
        if ($event->status !== EventStatus::Published) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($event->event_date->isPast()) {
            return back()->withErrors(['attendance' => 'このイベントはすでに開始しています。']);
        }

        /** @var User $user */
        $user = auth()->user();

        if ($event->user_id === $user->id) {
            return back()->withErrors(['attendance' => '自分のイベントには申し込めません。']);
        }

        $existing = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null && $existing->status === AttendanceStatus::Applied) {
            return back()->withErrors(['attendance' => 'すでに申し込み済みです。']);
        }

        $appliedCount = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->count();

        if ($appliedCount >= $event->capacity) {
            return back()->withErrors(['attendance' => '定員に達しています。']);
        }

        if ($existing !== null) {
            $existing->update([
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
                'cancelled_at' => null,
            ]);
        } else {
            EventAttendance::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => AttendanceStatus::Applied,
                'applied_at' => now(),
            ]);
        }

        return back()->with('success', '参加申し込みが完了しました。');
    }

    /**
     * 申し込みキャンセル
     */
    public function destroy(Event $event): RedirectResponse
    {
        if ($event->event_date->isPast()) {
            return back()->withErrors(['attendance' => 'このイベントはすでに開始しています。']);
        }

        /** @var User $user */
        $user = auth()->user();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', AttendanceStatus::Applied)
            ->first();

        if ($attendance === null) {
            return back()->withErrors(['attendance' => '申し込みが見つかりません。']);
        }

        $attendance->update([
            'status' => AttendanceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return back()->with('success', '参加をキャンセルしました。');
    }
}
