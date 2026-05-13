# 認証機能 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Laravel Sanctum を用いた API トークン認証機能（登録・ログイン・更新・ログアウト・自身情報取得）を実装する。

**Architecture:** Sanctum の personal access token + 24時間有効期限。`/api/v1/auth/*` 配下に5エンドポイントを配置し、`status=0`（凍結アカウント）はミドルウェア `EnsureUserIsActive` で全認証必須リクエストから弾く。

**Tech Stack:** Laravel 13, PHP 8.4, Laravel Sanctum, PHPUnit 12, Laravel Pint

**Spec reference:** [docs/superpowers/specs/2026-05-13-authentication-design.md](../specs/2026-05-13-authentication-design.md)

---

## ファイル構成

新規作成:

- `routes/api.php`  — `php artisan install:api` で自動生成、その後 v1/auth ルートを追記
- `config/sanctum.php`  — `install:api` で自動生成、`expiration` を編集
- `database/migrations/YYYY_MM_DD_HHMMSS_create_personal_access_tokens_table.php`  — `install:api` で生成
- `app/Http/Controllers/Api/V1/Auth/AuthController.php`  — 5メソッド集約
- `app/Http/Requests/Api/V1/Auth/RegisterRequest.php`  — 登録バリデーション
- `app/Http/Requests/Api/V1/Auth/LoginRequest.php`  — ログインバリデーション
- `app/Http/Resources/Api/V1/UserResource.php`  — ユーザー情報の API 出力整形
- `app/Http/Middleware/EnsureUserIsActive.php`  — status=1 のみ通すミドルウェア
- `tests/Feature/Api/V1/Auth/AuthTest.php`  — 全エンドポイントの Feature テスト

修正:

- `composer.json`  — `composer require` で自動更新
- `app/Models/User.php`  — `HasApiTokens` trait を追加
- `bootstrap/app.php`  — `active.user` ミドルウェアエイリアスを登録

---

## Task 1: Sanctum パッケージのインストールと初期セットアップ

**Files:**
- Create: `routes/api.php` (Artisan が自動生成)
- Create: `config/sanctum.php` (Artisan が自動生成)
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_personal_access_tokens_table.php` (Artisan が自動生成)
- Modify: `composer.json` (composer が自動更新)
- Modify: `bootstrap/app.php` (Artisan が自動更新)

- [ ] **Step 1: Sanctum を composer で追加**

```bash
composer require laravel/sanctum
```

Expected: `composer.json` の `require` セクションに `"laravel/sanctum": "..."` が追加される。

- [ ] **Step 2: install:api を実行**

```bash
php artisan install:api --no-interaction
```

Expected:
- `routes/api.php` が生成される（中身に `Route::get('/user', ...)` のサンプル付き）
- `config/sanctum.php` が生成される
- `database/migrations/` 配下に `*_create_personal_access_tokens_table.php` が生成される
- `bootstrap/app.php` が更新され、`api: __DIR__.'/../routes/api.php'` が `withRouting()` に追加される

- [ ] **Step 3: マイグレーション実行**

```bash
php artisan migrate --no-interaction
```

Expected: `personal_access_tokens` テーブルが作成される。

- [ ] **Step 4: コミット**

```bash
git add composer.json composer.lock config/sanctum.php routes/api.php database/migrations/ bootstrap/app.php
git commit -m "Sanctumパッケージを導入：install:apiで初期セットアップ"
```

---

## Task 2: Sanctum トークン期限を 24時間に設定

**Files:**
- Modify: `config/sanctum.php` (expiration 行)

- [ ] **Step 1: 設定変更**

`config/sanctum.php` を開き、以下の行を探す:

```php
'expiration' => null,
```

これを以下に変更する:

```php
'expiration' => 60 * 24,
```

`60 * 24` = 1440分（24時間）。

- [ ] **Step 2: 動作確認**

```bash
php artisan config:show sanctum.expiration
```

Expected: `1440` と表示される。

- [ ] **Step 3: コミット**

```bash
git add config/sanctum.php
git commit -m "Sanctumトークンの有効期限を24時間に設定"
```

---

## Task 3: User モデルに HasApiTokens trait を追加

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: trait をインポートして use 節に追加**

`app/Models/User.php` を以下のとおり編集する。

変更前の use 節（11〜12行目付近）:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
```

これに `HasApiTokens` の import を加える:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
```

そして、クラスの trait use 行（19行目付近）:

```php
use HasFactory, Notifiable;
```

を以下に変更する:

```php
use HasApiTokens, HasFactory, Notifiable;
```

- [ ] **Step 2: 確認のために tinker で API トークン発行を試す**

```bash
php artisan tinker --execute 'echo \App\Models\User::factory()->create()->createToken("test")->plainTextToken;'
```

Expected: `1|abcdef...` の形式のプレーントークンが標準出力される。

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add app/Models/User.php
git commit -m "UserモデルにHasApiTokens traitを追加"
```

---

## Task 4: EnsureUserIsActive ミドルウェアを作成

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsActive.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Artisan でミドルウェアの雛形を作成**

```bash
php artisan make:middleware EnsureUserIsActive --no-interaction
```

Expected: `app/Http/Middleware/EnsureUserIsActive.php` が作成される。

- [ ] **Step 2: ミドルウェアの実装**

`app/Http/Middleware/EnsureUserIsActive.php` を以下の内容で完全に置き換える:

```php
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
```

- [ ] **Step 3: bootstrap/app.php にエイリアスを登録**

`bootstrap/app.php` の `->withMiddleware(function (Middleware $middleware): void {` ブロックを以下に置き換える:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
```

- [ ] **Step 4: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: コミット**

```bash
git add app/Http/Middleware/EnsureUserIsActive.php bootstrap/app.php
git commit -m "EnsureUserIsActiveミドルウェアを追加：凍結アカウントを拒否"
```

---

## Task 5: RegisterRequest を作成

**Files:**
- Create: `app/Http/Requests/Api/V1/Auth/RegisterRequest.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:request Api/V1/Auth/RegisterRequest --no-interaction
```

Expected: `app/Http/Requests/Api/V1/Auth/RegisterRequest.php` が作成される。

- [ ] **Step 2: 実装**

`app/Http/Requests/Api/V1/Auth/RegisterRequest.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * ユーザー登録のバリデーション
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add app/Http/Requests/Api/V1/Auth/RegisterRequest.php
git commit -m "RegisterRequestを追加：登録時のバリデーション定義"
```

---

## Task 6: LoginRequest を作成

**Files:**
- Create: `app/Http/Requests/Api/V1/Auth/LoginRequest.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:request Api/V1/Auth/LoginRequest --no-interaction
```

- [ ] **Step 2: 実装**

`app/Http/Requests/Api/V1/Auth/LoginRequest.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ログインのバリデーション
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add app/Http/Requests/Api/V1/Auth/LoginRequest.php
git commit -m "LoginRequestを追加：ログイン時のバリデーション定義"
```

---

## Task 7: UserResource を作成

**Files:**
- Create: `app/Http/Resources/Api/V1/UserResource.php`

- [ ] **Step 1: Artisan で雛形を作成**

```bash
php artisan make:resource Api/V1/UserResource --no-interaction
```

Expected: `app/Http/Resources/Api/V1/UserResource.php` が作成される。

- [ ] **Step 2: 実装**

`app/Http/Resources/Api/V1/UserResource.php` を以下の内容で完全に置き換える:

```php
<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ユーザー情報の API レスポンス整形
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
}
```

- [ ] **Step 3: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add app/Http/Resources/Api/V1/UserResource.php
git commit -m "UserResourceを追加：ユーザー情報のAPI出力整形"
```

---

## Task 8: 認証テストの雛形と register の失敗テストを作成（TDD）

**Files:**
- Create: `tests/Feature/Api/V1/Auth/AuthTest.php`

- [ ] **Step 1: テストファイルの雛形を作成**

```bash
php artisan make:test --phpunit Api/V1/Auth/AuthTest --no-interaction
```

Expected: `tests/Feature/Api/V1/Auth/AuthTest.php` が作成される。

- [ ] **Step 2: register エンドポイントの最初のテスト（まだ存在しないので落ちる）**

`tests/Feature/Api/V1/Auth/AuthTest.php` を以下の内容で完全に置き換える:

```php
<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * register: 正常系
     */
    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'email', 'name'],
                'token',
                'token_type',
                'expires_at',
            ],
        ]);
        $response->assertJsonPath('data.user.email', 'taro@example.com');
        $response->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('users', [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'status' => UserStatus::Active->value,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
```

- [ ] **Step 3: 実行してエラーを確認**

```bash
php artisan test --compact --filter=test_register_creates_user_and_returns_token
```

Expected: FAIL（ルートが未定義のため 404、もしくは `NotFoundHttpException`）。これが赤になることを確認する。

---

## Task 9: AuthController と register エンドポイントを実装（緑にする）

**Files:**
- Create: `app/Http/Controllers/Api/V1/Auth/AuthController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: コントローラーの雛形を作成**

```bash
php artisan make:controller Api/V1/Auth/AuthController --no-interaction
```

Expected: `app/Http/Controllers/Api/V1/Auth/AuthController.php` が作成される。

- [ ] **Step 2: コントローラーを実装**

`app/Http/Controllers/Api/V1/Auth/AuthController.php` を以下の内容で完全に置き換える:

```php
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
```

- [ ] **Step 3: routes/api.php にルートを追加**

`routes/api.php` を開き、既存の内容（`install:api` が生成した `Route::get('/user', ...)` サンプル）を以下に完全に置き換える:

```php
<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // 認証不要
        Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');

        // 認証必須
        Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
            Route::post('refresh', [AuthController::class, 'refresh'])->name('api.v1.auth.refresh');
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });
});
```

- [ ] **Step 4: テストを実行して緑になることを確認**

```bash
php artisan test --compact --filter=test_register_creates_user_and_returns_token
```

Expected: 1 test passed.

- [ ] **Step 5: Pint で整形**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: コミット**

```bash
git add app/Http/Controllers/Api/V1/Auth/AuthController.php routes/api.php
git commit -m "AuthController実装と/api/v1/auth/registerルート追加"
```

---

## Task 10: register のバリデーション失敗テストを追加

**Files:**
- Modify: `tests/Feature/Api/V1/Auth/AuthTest.php`

- [ ] **Step 1: テストを追加**

`AuthTest` クラス内の既存テストの直後に、以下の 4 メソッドを追加する:

```php
    /**
     * register: email 形式不正
     */
    public function test_register_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'not-an-email',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * register: email 重複
     */
    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * register: password 8文字未満
     */
    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * register: password_confirmation 不一致
     */
    public function test_register_fails_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'different12',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_register_fails_with_'
```

Expected: 4 tests passed.

- [ ] **Step 3: コミット**

```bash
git add tests/Feature/Api/V1/Auth/AuthTest.php
git commit -m "register異常系テストを追加（4件）"
```

---

## Task 11: login のテストを追加

**Files:**
- Modify: `tests/Feature/Api/V1/Auth/AuthTest.php`

- [ ] **Step 1: テストを追加**

`AuthTest` クラスの最後に以下のメソッドを追加する:

```php
    /**
     * login: 正常系
     */
    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'taro@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'email', 'name'],
                'token',
                'token_type',
                'expires_at',
            ],
        ]);
        $response->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * login: 存在しないメール
     */
    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * login: パスワード不一致
     */
    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * login: 凍結ユーザーは403
     */
    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'email' => 'frozen@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'frozen@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertForbidden();
        $response->assertJson(['message' => 'アカウントが凍結されています。']);
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_login_'
```

Expected: 4 tests passed.

- [ ] **Step 3: コミット**

```bash
git add tests/Feature/Api/V1/Auth/AuthTest.php
git commit -m "loginテストを追加（正常系+異常系3件）"
```

---

## Task 12: refresh のテストを追加

**Files:**
- Modify: `tests/Feature/Api/V1/Auth/AuthTest.php`

- [ ] **Step 1: テストを追加**

`AuthTest` クラスの最後に以下のメソッドを追加する:

```php
    /**
     * refresh: 新トークン発行 + 旧トークン削除
     */
    public function test_refresh_issues_new_token_and_revokes_old(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('api-token');
        $oldTokenId = $oldToken->accessToken->id;

        $response = $this->withHeader('Authorization', 'Bearer '.$oldToken->plainTextToken)
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['user', 'token', 'token_type', 'expires_at'],
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldTokenId]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /**
     * refresh: 認証なしは401
     */
    public function test_refresh_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertUnauthorized();
    }

    /**
     * refresh: 期限切れトークンは401
     */
    public function test_refresh_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        // 25時間先まで時を進める（有効期限24時間を超える）
        Carbon::setTestNow(now()->addHours(25));

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/refresh');

        $response->assertUnauthorized();

        Carbon::setTestNow();
    }

    /**
     * refresh: 凍結ユーザーは403
     */
    public function test_refresh_rejects_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();
        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/refresh');

        $response->assertForbidden();
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_refresh_'
```

Expected: 4 tests passed.

- [ ] **Step 3: コミット**

```bash
git add tests/Feature/Api/V1/Auth/AuthTest.php
git commit -m "refreshテストを追加（正常系+異常系3件）"
```

---

## Task 13: logout のテストを追加

**Files:**
- Modify: `tests/Feature/Api/V1/Auth/AuthTest.php`

- [ ] **Step 1: テストを追加**

`AuthTest` クラスの最後に以下のメソッドを追加する:

```php
    /**
     * logout: トークン削除 + 204
     */
    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');
        $tokenId = $token->accessToken->id;

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    /**
     * logout: 認証なしは401
     */
    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_logout_'
```

Expected: 2 tests passed.

- [ ] **Step 3: コミット**

```bash
git add tests/Feature/Api/V1/Auth/AuthTest.php
git commit -m "logoutテストを追加（正常系+異常系1件）"
```

---

## Task 14: me のテストを追加

**Files:**
- Modify: `tests/Feature/Api/V1/Auth/AuthTest.php`

- [ ] **Step 1: テストを追加**

`AuthTest` クラスの最後に以下のメソッドを追加する:

```php
    /**
     * me: 認証ユーザー情報を返す
     */
    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
        ]);
        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $user->id,
                'email' => 'taro@example.com',
                'name' => '山田 太郎',
            ],
        ]);
    }

    /**
     * me: 認証なしは401
     */
    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    /**
     * me: 凍結ユーザーは403
     */
    public function test_me_rejects_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();
        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertForbidden();
    }
```

- [ ] **Step 2: テスト実行**

```bash
php artisan test --compact --filter='test_me_'
```

Expected: 3 tests passed.

- [ ] **Step 3: コミット**

```bash
git add tests/Feature/Api/V1/Auth/AuthTest.php
git commit -m "meテストを追加（正常系+異常系2件）"
```

---

## Task 15: 全テスト＆最終整形

**Files:** なし（検証のみ）

- [ ] **Step 1: 認証関連の全テストを通す**

```bash
php artisan test --compact tests/Feature/Api/V1/Auth/AuthTest.php
```

Expected: 18 tests passed（register 5, login 4, refresh 4, logout 2, me 3）。

- [ ] **Step 2: 全テストスイートを通して既存テストへの影響を確認**

```bash
php artisan test --compact
```

Expected: 全 PASS（`ExampleTest` を含む）。

- [ ] **Step 3: Pint で整形違反がないことを確認**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: 整形対象ファイルなし、もしくは自動修正のみ。修正が走った場合は次のステップで追加コミット。

- [ ] **Step 4: TASKS.md を更新**

`TASKS.md` の「実装中のタスク」セクションを開き、次の行を:

```markdown
- [ ] 認証機能を実装：ユーザー登録・ログイン・トークン更新
```

`## ✅ 完了済み` セクションへ以下のとおり移動する:

```markdown
- [x] 認証機能を実装：ユーザー登録・ログイン・トークン更新
```

- [ ] **Step 5: 残作業があればコミット**

```bash
git status
# 修正があれば
git add -A
git commit -m "TASKS.md更新：認証機能を完了に移動"
```

---

## 受け入れ基準

- [ ] `composer require laravel/sanctum` と `php artisan install:api` が完了している
- [ ] 5 エンドポイント (`register`, `login`, `refresh`, `logout`, `me`) すべてが仕様どおりのステータスコードとレスポンスを返す
- [ ] `php artisan test --compact tests/Feature/Api/V1/Auth/AuthTest.php` で 18 件すべて PASS する
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
- [ ] 凍結ユーザー (`status=0`) はログイン拒否＆既存トークンでもアクセス拒否される
- [ ] TASKS.md の認証タスクが完了に移動している
