<?php

namespace App\Http\Controllers;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Http\Requests\Api\V1\Event\StoreEventRequest;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    private const int PER_PAGE = 12;

    /**
     * イベント一覧（公開済みのみ、event_date昇順）
     */
    public function index(): View
    {
        $events = Event::query()
            ->with('user')
            ->withCount('attendances')
            ->where('status', EventStatus::Published)
            ->orderBy('event_date', 'asc')
            ->paginate(self::PER_PAGE);

        return view('events.index', compact('events'));
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
        $event = Event::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
            'status' => $request->integer('status', EventStatus::Draft->value),
        ]);

        return redirect()->route('events.show', $event)->with('success', 'イベントを作成しました。');
    }

    /**
     * イベント詳細
     */
    public function show(Event $event): View
    {
        if ($event->status !== EventStatus::Published) {
            $user = auth()->user();
            if ($user === null || $user->id !== $event->user_id) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        $event->loadCount('attendances');
        $event->load('user');

        return view('events.show', compact('event'));
    }
}
