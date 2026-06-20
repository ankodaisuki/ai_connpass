<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function freeze(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $this->adminService->freezeUser($user, $admin, $validated['reason']);

        return redirect()->route('admin.users.index')->with('success', "{$user->name} を凍結しました。");
    }

    public function unfreeze(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $this->adminService->unfreezeUser($user, $admin, $validated['reason']);

        return redirect()->route('admin.users.index')->with('success', "{$user->name} の凍結を解除しました。");
    }
}
