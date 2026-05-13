<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 認証済みユーザーが有効状態（status=Active）であることを保証するミドルウェア
 */
class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->status !== UserStatus::Active) {
            return response()->json([
                'message' => 'アカウントが凍結されています。',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
