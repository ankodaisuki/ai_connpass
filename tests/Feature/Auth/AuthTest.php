<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** 登録ページが表示される */
    public function test_register_page_is_accessible(): void
    {
        $this->get(route('register'))->assertStatus(200);
    }

    /** 正常登録でユーザー作成・ログイン状態・events.index へリダイレクト */
    public function test_register_creates_user_and_logs_in(): void
    {
        $response = $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('events.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'taro@example.com', 'name' => '山田 太郎']);
    }

    /** メール形式不正 → email バリデーションエラー */
    public function test_register_fails_with_invalid_email(): void
    {
        $this->post(route('register'), [
            'email' => 'not-an-email',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertSessionHasErrors(['email']);
    }

    /** 重複メール → email バリデーションエラー */
    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertSessionHasErrors(['email']);
    }

    /** パスワード 8 文字未満 → password バリデーションエラー */
    public function test_register_fails_with_short_password(): void
    {
        $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password']);
    }

    /** パスワード確認不一致 → password バリデーションエラー */
    public function test_register_fails_with_mismatched_password(): void
    {
        $this->post(route('register'), [
            'email' => 'taro@example.com',
            'name' => '山田 太郎',
            'password' => 'secret1234',
            'password_confirmation' => 'different12',
        ])->assertSessionHasErrors(['password']);
    }

    /** 認証済みユーザーが /register にアクセス → リダイレクト（guest ミドルウェア） */
    public function test_authenticated_user_is_redirected_from_register(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('register'))->assertRedirect();
    }

    /** ログインページが表示される */
    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))->assertStatus(200);
    }

    /** 正しい認証情報でログイン → events.index へリダイレクト・認証済み */
    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'taro@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect(route('events.index'));
        $this->assertAuthenticatedAs($user);
    }

    /** 存在しないメールでログイン → email バリデーションエラー */
    public function test_login_fails_with_unknown_email(): void
    {
        $this->post(route('login'), [
            'email' => 'unknown@example.com',
            'password' => 'secret1234',
        ])->assertSessionHasErrors(['email']);
    }

    /** パスワード不一致でログイン → email バリデーションエラー */
    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email']);
    }

    /** Inactive ユーザーのログイン → 凍結エラー・ゲスト状態維持 */
    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'email' => 'frozen@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'frozen@example.com',
            'password' => 'secret1234',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    /** 認証済みユーザーが /login にアクセス → リダイレクト（guest ミドルウェア） */
    public function test_authenticated_user_is_redirected_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect();
    }

    /** ログアウト → ゲスト状態・events.index へリダイレクト */
    public function test_logout_logs_out_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))
            ->assertRedirect(route('events.index'));

        $this->assertGuest();
    }
}
