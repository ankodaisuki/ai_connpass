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
}
