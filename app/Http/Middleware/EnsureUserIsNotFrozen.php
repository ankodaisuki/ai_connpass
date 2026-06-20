<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotFrozen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isFrozen()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $reason = $user->frozen_reason ? "（理由：{$user->frozen_reason}）" : '';

            return redirect()->route('login')->withErrors([
                'email' => "このアカウントは凍結されています。{$reason}",
            ]);
        }

        return $next($request);
    }
}
