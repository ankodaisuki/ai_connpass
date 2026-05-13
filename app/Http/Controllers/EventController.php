<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Contracts\View\View;
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
