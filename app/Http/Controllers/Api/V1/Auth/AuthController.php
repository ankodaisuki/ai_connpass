<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * 認証 API コントローラ
 */
class AuthController extends Controller
{
    private const string TOKEN_NAME = 'api-token';

    /**
     * ユーザー登録 + トークン発行
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'email' => $request->validated('email'),
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
            'status' => UserStatus::Active,
        ]);

        return $this->respondWithToken($user, Response::HTTP_CREATED);
    }

    /**
     * ログイン + トークン発行
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => ['提供された認証情報は正しくありません。'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->status !== UserStatus::Active) {
            Auth::logout();

            return response()->json([
                'message' => 'アカウントが凍結されています。',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->respondWithToken($user);
    }

    /**
     * トークン更新（現トークン削除 + 新トークン発行）
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PersonalAccessToken $current */
        $current = $request->user()->currentAccessToken();
        $current->delete();

        return $this->respondWithToken($user);
    }

    /**
     * ログアウト（現トークンのみ削除）
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $current */
        $current = $request->user()->currentAccessToken();
        $current->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * 認証ユーザー情報
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    /**
     * トークン付きレスポンスを返す共通処理
     */
    private function respondWithToken(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        $token = $user->createToken(self::TOKEN_NAME);
        /** @var PersonalAccessToken $accessToken */
        $accessToken = $token->accessToken;

        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = $expirationMinutes !== null
            ? Carbon::parse($accessToken->created_at)->addMinutes((int) $expirationMinutes)->toIso8601ZuluString()
            : null;

        return response()->json([
            'data' => [
                'user' => (new UserResource($user))->resolve(),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt,
            ],
        ], $status);
    }
}
