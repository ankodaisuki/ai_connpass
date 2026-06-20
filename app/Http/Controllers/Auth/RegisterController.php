<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $existing = User::where('email', $request->validated('email'))
            ->where('status', UserStatus::Inactive)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'name' => $request->validated('name'),
                'password' => bcrypt($request->validated('password')),
                'status' => UserStatus::Active,
            ]);
            $user = $existing;
        } else {
            $user = User::create([
                'email' => $request->validated('email'),
                'name' => $request->validated('name'),
                'password' => $request->validated('password'),
                'status' => UserStatus::Active,
            ]);
        }

        Auth::login($user);

        return redirect()->route('events.index');
    }
}
