<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Exceptions\AttendanceException;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\EventAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class EventAttendanceController extends Controller
{
    public function __construct(private readonly EventAttendanceService $attendanceService) {}

    /**
     * イベント申し込み
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        if ($event->status !== EventStatus::Published) {
            abort(Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = auth()->user();

        $attendanceMode = match ($event->prefecture) {
            'オンライン' => AttendanceMode::Online,
            'ハイブリッド' => AttendanceMode::from(
                $request->validate([
                    'attendance_mode' => ['required', Rule::enum(AttendanceMode::class)],
                ])['attendance_mode']
            ),
            default => AttendanceMode::InPerson,
        };

        try {
            $result = $this->attendanceService->apply($event, $user, $attendanceMode);
        } catch (AttendanceException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        $message = $result === AttendanceStatus::Waitlisted
            ? 'キャンセル待ちに登録しました。'
            : '参加申し込みが完了しました。';

        return back()->with('success', $message);
    }

    /**
     * 出欠記録の更新（トグル）
     */
    public function update(Request $request, Event $event, EventAttendance $attendance): RedirectResponse
    {
        Gate::authorize('updateAttendance', $event);

        if ($attendance->event_id !== $event->id) {
            abort(404);
        }

        if ($event->end_date->isPast()) {
            return back()->withErrors(['attendance' => 'イベントが終了しているため出欠を記録できません。']);
        }

        if (! $event->event_date->isPast()) {
            return back()->withErrors(['attendance' => 'イベント開始前は出欠を記録できません。']);
        }

        $attendedAt = $request->input('attended_at');

        if ($attendedAt === 'null') {
            $attendance->update(['attended_at' => null]);
        } else {
            $attendance->update(['attended_at' => now()]);
        }

        return redirect()->route('events.show', $event)
            ->with('success', '出欠を記録しました');
    }

    /**
     * 申し込みキャンセル
     */
    public function destroy(Event $event): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $this->attendanceService->cancel($event, $user);
        } catch (AttendanceException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        return back()->with('success', '参加をキャンセルしました。');
    }
}
