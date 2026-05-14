<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Http\Requests\Api\V1\Event\IndexEventRequest;
use App\Http\Requests\Api\V1\Event\StoreEventRequest;
use App\Http\Requests\Api\V1\Event\UpdateEventRequest;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    use AuthorizesRequests;

    private const int PER_PAGE = 12;

    /**
     * イベント一覧（公開済みのみ、event_date昇順、検索・フィルタ対応）
     */
    public function index(IndexEventRequest $request): View
    {
        $query = Event::query()
            ->with('user')
            ->withCount('attendances')
            ->where('status', EventStatus::Published);

        if ($q = $request->validated('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        if ($category = $request->validated('category')) {
            $query->where('category', EventCategory::from((int) $category));
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

        $events = $query->orderBy('event_date', 'asc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $filters = $request->validated();

        return view('events.index', compact('events', 'filters'));
    }

    /**
     * イベント作成フォーム
     */
    public function create(): View
    {
        return view('events.create', [
            'categories' => EventCategory::cases(),
            'statuses' => EventStatus::cases(),
        ]);
    }

    /**
     * イベント保存
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $event = Event::create([
            ...$request->validated(),
            'user_id' => $user->id,
            'status' => $request->integer('status', EventStatus::Draft->value),
        ]);

        return redirect()->route('events.show', $event)->with('success', 'イベントを作成しました。');
    }

    /**
     * イベント編集フォーム
     */
    public function edit(Event $event): View
    {
        $this->authorize('update', $event);

        return view('events.edit', [
            'event' => $event,
            'categories' => EventCategory::cases(),
            'statuses' => EventStatus::cases(),
        ]);
    }

    /**
     * イベント更新
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update($request->validated());

        return redirect()->route('events.show', $event)->with('success', 'イベントを更新しました。');
    }

    /**
     * イベント削除
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->update(['status' => EventStatus::Private]);
        $event->delete();

        return redirect()->route('events.index')->with('success', 'イベントを削除しました。');
    }

    /**
     * イベント詳細
     */
    public function show(Event $event): View
    {
        if ($event->status !== EventStatus::Published) {
            /** @var User|null $user */
            $user = auth()->user();
            if ($user === null || $user->id !== $event->user_id) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        $event->loadCount('attendances');
        $event->load(['user', 'attendances' => function ($query) {
            $query->where('status', AttendanceStatus::Applied)
                ->with('user')
                ->orderBy('applied_at', 'asc');
        }]);

        /** @var User|null $authUser */
        $authUser = auth()->user();

        $myAttendance = $authUser !== null
            ? EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('user_id', $authUser->id)
                ->where('status', AttendanceStatus::Applied)
                ->first()
            : null;

        return view('events.show', compact('event', 'myAttendance'));
    }
}
