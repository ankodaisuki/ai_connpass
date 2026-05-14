# モバイルナビゲーション実装プラン

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ヘッダーにモバイル用ハンバーガーメニューを追加し、小画面でもプロフィール・申し込み一覧・ログアウトにアクセスできるようにする。

**Architecture:** `app-layout.blade.php` のみ変更。`<details>/<summary>` を使って JS なしで実装。デスクトップ表示は既存のまま維持し、`sm:hidden` / `sm:block` で出し分ける。

**Tech Stack:** Blade, Tailwind CSS v4

---

### Task 1: フィーチャーテスト作成（モバイルメニューのリンク存在確認）

**Files:**
- Create: `tests/Feature/MobileNavigationTest.php`

- [ ] **Step 1: テストファイルを作成する**

```bash
php artisan make:test --phpunit MobileNavigationTest
```

- [ ] **Step 2: テストを記述する**

`tests/Feature/MobileNavigationTest.php` を以下の内容に置き換える：

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_mobile_menu_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('events.index'));

        $response->assertStatus(200);
        $response->assertSee(route('profile'));
        $response->assertSee(route('my.attendances'));
        $response->assertSee(route('logout'));
    }

    public function test_guest_sees_login_and_register_links(): void
    {
        $response = $this->get(route('events.index'));

        $response->assertStatus(200);
        $response->assertSee(route('login'));
        $response->assertSee(route('register'));
    }
}
```

- [ ] **Step 3: テストを実行して失敗を確認する**

```bash
php artisan test --compact tests/Feature/MobileNavigationTest.php
```

期待結果: `test_authenticated_user_sees_mobile_menu_links` が FAIL（現状はログイン済みユーザーのメニューリンクがモバイルで非表示のため）

> 注意: HTML 上はリンク自体は存在するが `hidden sm:block` で CSS 非表示になっているだけなので、`assertSee` は通る可能性がある。その場合は次のタスクへ進んで問題ない。

---

### Task 2: モバイル用ハンバーガーメニューを追加する

**Files:**
- Modify: `resources/views/components/app-layout.blade.php`

現在の nav 部分（11〜72行目あたり）を以下のように変更する。

- [ ] **Step 1: `app-layout.blade.php` を開いて nav 要素を確認する**

`resources/views/components/app-layout.blade.php` の `<nav>` タグ内を確認する。
現状: ログイン済みユーザーのドロップダウンに `hidden sm:block` がついていてモバイルで非表示。

- [ ] **Step 2: nav 要素を以下のコードに置き換える**

`<nav class="flex items-center gap-2 sm:gap-3">` から `</nav>` までを以下に置き換える：

```blade
<nav class="flex items-center gap-2 sm:gap-3">
    @auth
        <a href="{{ route('events.create') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
            ＋ イベント作成
        </a>

        {{-- デスクトップ用ドロップダウン --}}
        <details class="relative hidden sm:block group/user">
            <summary class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none list-none rounded-lg px-2 py-1 hover:bg-slate-100 dark:hover:bg-[#1a1a18] transition-colors">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                </span>
                {{ auth()->user()->name }}
                <svg class="h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </summary>
            <div class="absolute right-0 mt-1 w-48 rounded-xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-lg py-1 z-50">
                <a href="{{ route('profile') }}"
                    class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                    プロフィール
                </a>
                <a href="{{ route('my.attendances') }}"
                    class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                    申し込み一覧
                </a>
                <div class="my-1 border-t border-slate-100 dark:border-[#3E3E3A]"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                        ログアウト
                    </button>
                </form>
            </div>
        </details>

        {{-- モバイル用ハンバーガーメニュー --}}
        <details class="relative sm:hidden group/mobile">
            <summary class="flex items-center justify-center h-9 w-9 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-[#1a1a18] cursor-pointer list-none transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </summary>
            <div class="absolute right-0 mt-1 w-56 rounded-xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] shadow-lg py-1 z-50">
                <div class="px-4 py-2 border-b border-slate-100 dark:border-[#3E3E3A]">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                </div>
                <a href="{{ route('profile') }}"
                    class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                    プロフィール
                </a>
                <a href="{{ route('my.attendances') }}"
                    class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                    申し込み一覧
                </a>
                <div class="my-1 border-t border-slate-100 dark:border-[#3E3E3A]"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-[#1a1a18] transition-colors">
                        ログアウト
                    </button>
                </form>
            </div>
        </details>
    @else
        @if (Route::has('login'))
            <a href="{{ route('login') }}"
                class="inline-flex items-center px-3 py-2 rounded-lg text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 text-sm font-medium transition-colors">
                ログイン
            </a>
        @endif
        @if (Route::has('register'))
            <a href="{{ route('register') }}"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
                新規登録
            </a>
        @endif
    @endauth
</nav>
```

- [ ] **Step 3: テストを実行してパスを確認する**

```bash
php artisan test --compact tests/Feature/MobileNavigationTest.php
```

期待結果: 2テストとも PASS

- [ ] **Step 4: Pint でコードスタイルを整える**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: コミットする**

```bash
git add resources/views/components/app-layout.blade.php tests/Feature/MobileNavigationTest.php
git commit -m "モバイル用ハンバーガーメニューをヘッダーに追加"
```

---

### Task 3: ブラウザで動作確認する

- [ ] **Step 1: 開発サーバーが起動していることを確認する**

```bash
php artisan route:list --name=events.index
```

`GET` で `events/` が存在することを確認。

- [ ] **Step 2: ブラウザで確認する**

開発サーバー URL をブラウザで開き、以下を確認：

1. デスクトップ幅（768px以上）: 従来のユーザー名ドロップダウンが表示される
2. モバイル幅（767px以下）: ハンバーガーアイコン（☰）が表示される
3. ハンバーガーアイコンをタップ: メニューが展開してプロフィール・申し込み一覧・ログアウトが表示される
4. ログアウトをタップ: ログアウトされる

> ブラウザの DevTools で「Responsive Design Mode」を使うと手軽に確認できる。
