<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Exceptions\AttendanceException;
use App\Models\Event;
use App\Models\User;
use App\Services\EventAttendanceService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EventAttendanceController extends Controller
{
    public function __construct(private readonly EventAttendanceService $attendanceService) {}

    /**
     * イベント申し込み
     */
    public function store(Event $event): RedirectResponse
    {
        if ($event->status !== EventStatus::Published) {
            abort(Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = auth()->user();

        try {
            $this->attendanceService->apply($event, $user);
        } catch (AttendanceException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        return back()->with('success', '参加申し込みが完了しました。');
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
