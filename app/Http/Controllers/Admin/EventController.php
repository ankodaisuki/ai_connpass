<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Event;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    public function index(Request $request): View
    {
        $events = Event::query()
            ->with('user')
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.events.index', compact('events'));
    }

    public function trashed(): View
    {
        $events = Event::onlyTrashed()
            ->whereIn('id', $this->adminDeletedEventIds())
            ->with('user')
            ->orderByDesc('deleted_at')
            ->paginate(30);

        return view('admin.events.trashed', compact('events'));
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $event = Event::onlyTrashed()
            ->whereIn('id', $this->adminDeletedEventIds())
            ->findOrFail($id);

        /** @var User $admin */
        $admin = $request->user();
        $this->adminService->restoreEvent($event, $admin, $validated['reason']);

        return redirect()->route('admin.events.trashed')->with('success', "「{$event->title}」を復元しました。");
    }

    /**
     * 管理者削除（delete_event 監査ログあり）されたイベントIDのサブクエリ。
     */
    private function adminDeletedEventIds(): Builder
    {
        return AdminAuditLog::query()
            ->where('action', 'delete_event')
            ->where('target_type', 'event')
            ->select('target_id')
            ->toBase();
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $this->adminService->deleteEvent($event, $admin, $validated['reason']);

        return redirect()->route('admin.events.index')->with('success', "「{$event->title}」を削除しました。");
    }
}
