<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventAttendanceResource;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * イベント参加管理 API コントローラ（event-nested）
 */
class EventAttendanceController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * 参加者一覧（Published イベントのみ、Applied のみ、15件/ページ、applied_at 昇順）
     */
    public function index(Event $event): AnonymousResourceCollection
    {
        if ($event->status !== EventStatus::Published) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $attendances = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->with('user')
            ->orderBy('applied_at', 'asc')
            ->paginate(self::PER_PAGE);

        return EventAttendanceResource::collection($attendances);
    }

    /**
     * イベント申し込み
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        if ($event->status !== EventStatus::Published) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($event->event_date->isPast()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'このイベントはすでに開始しています。');
        }

        $user = $request->user();

        if ($event->user_id === $user->id) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, '作成者は自分のイベントに申し込めません。');
        }

        $existing = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null && $existing->status === AttendanceStatus::Applied) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'すでに申し込み済みです。');
        }

        $appliedCount = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->count();

        if ($appliedCount >= $event->capacity) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, '定員に達しています。');
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

        $attendance->load('user');

        return (new EventAttendanceResource($attendance))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * 自分の申し込みをキャンセル
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        if ($event->event_date->isPast()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'このイベントはすでに開始しています。');
        }

        $user = $request->user();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', AttendanceStatus::Applied)
            ->first();

        if ($attendance === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $attendance->update([
            'status' => AttendanceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
