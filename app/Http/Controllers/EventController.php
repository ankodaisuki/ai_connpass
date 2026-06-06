<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Http\Requests\Event\IndexEventRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    use AuthorizesRequests;

    private const int PER_PAGE = 12;

    public function __construct(private readonly EventService $eventService) {}

    /**
     * イベント一覧（公開済みのみ、event_date昇順、検索・フィルタ対応）
     */
    public function index(IndexEventRequest $request): View
    {
        $filters = $request->validated();

        $events = $this->eventService
            ->filteredQuery($filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

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

        $event->loadCount(['appliedAttendances as attendances_count']);
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

        $myWaitlist = $authUser !== null
            ? EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('user_id', $authUser->id)
                ->where('status', AttendanceStatus::Waitlisted)
                ->first()
            : null;

        $myWaitlistPosition = $myWaitlist !== null
            ? EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('status', AttendanceStatus::Waitlisted)
                ->where('waitlisted_at', '<=', $myWaitlist->waitlisted_at)
                ->count()
            : null;

        $waitlistCount = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Waitlisted)
            ->count();

        $isWaitlistFull = $waitlistCount >= $event->capacity;

        return view('events.show', compact(
            'event',
            'myAttendance',
            'myWaitlist',
            'myWaitlistPosition',
            'isWaitlistFull',
        ));
    }
}
