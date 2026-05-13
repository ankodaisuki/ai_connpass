<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Event\IndexEventRequest;
use App\Http\Requests\Api\V1\Event\StoreEventRequest;
use App\Http\Requests\Api\V1\Event\UpdateEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * イベント管理 API コントローラ
 */
class EventController extends Controller
{
    use AuthorizesRequests;

    private const int PER_PAGE = 15;

    /**
     * イベント一覧（Published のみ、event_date 昇順、ページネーション、検索フィルタ対応）
     *
     * 検索パラメータ:
     * - q: title/description の部分一致
     * - category: EventCategory 値で完全一致
     * - prefecture: 完全一致
     * - from: event_date >= from
     * - to: event_date <= to (日付のみは endOfDay 補完)
     */
    public function index(IndexEventRequest $request): AnonymousResourceCollection
    {
        $query = Event::query()
            ->with('user')
            ->where('status', EventStatus::Published);

        if ($q = $request->validated('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        if ($category = $request->validated('category')) {
            $query->where('category', EventCategory::from($category));
        }

        if ($prefecture = $request->validated('prefecture')) {
            $query->where('prefecture', $prefecture);
        }

        if ($from = $request->validated('from')) {
            $query->where('event_date', '>=', Carbon::parse($from));
        }

        if ($to = $request->validated('to')) {
            $toDate = Carbon::parse($to);
            if ($toDate->hour === 0 && $toDate->minute === 0 && $toDate->second === 0) {
                $toDate = $toDate->endOfDay();
            }
            $query->where('event_date', '<=', $toDate);
        }

        $events = $query->orderBy('event_date', 'asc')->paginate(self::PER_PAGE);

        return EventResource::collection($events);
    }

    /**
     * イベント詳細
     *
     * Published は誰でも、Draft/Private は作成者本人のみ取得可。
     * 他人の Draft/Private は存在を秘匿するため 404。
     */
    public function show(Request $request, Event $event): EventResource
    {
        if ($event->status !== EventStatus::Published) {
            $user = $request->user('sanctum');
            if ($user === null || $user->id !== $event->user_id) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        $event->load('user');

        return new EventResource($event);
    }

    /**
     * イベント作成
     *
     * status 未指定なら Draft で作成される。user_id は認証ユーザーから自動設定。
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['status'] = isset($data['status'])
            ? EventStatus::from($data['status'])
            : EventStatus::Draft;

        $event = Event::create($data);
        $event->load('user');

        return (new EventResource($event))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * イベント更新（本人のみ）
     */
    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $data = $request->validated();
        $data['status'] = EventStatus::from($data['status']);

        $event->update($data);
        $event->load('user');

        return new EventResource($event);
    }

    /**
     * イベント削除（本人のみ）
     *
     * status=Private に更新したうえで SoftDeletes により deleted_at をセット。
     */
    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->update(['status' => EventStatus::Private]);
        $event->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
