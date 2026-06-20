<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\AdminService;
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
