# my/attendances キャンセル待ちタブ実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/my/attendances` に「申込一覧」と「キャンセル待ち」タブを追加し、`?tab=waitlist` クエリパラメータで切り替える。

**Architecture:** `EventAttendance` にスコープを追加し、コントローラーで `?tab` パラメータを受け取って適切なデータを取得する。ビューは `$tab` 変数でタブのアクティブ状態と表示内容を制御する。

**Tech Stack:** Laravel 13, Blade, Tailwind CSS v4, Pest v4

---

## ファイル一覧

| 操作 | ファイル |
|---|---|
| 修正 | `app/Models/EventAttendance.php` |
| 修正 | `app/Http/Controllers/MyAttendanceController.php` |
| 修正 | `resources/views/my/attendances.blade.php` |
| 修正（テスト追加） | `tests/Feature/MyAttendanceTest.php` |

---

### Task 1: `EventAttendance` に `waitlistedToPublishedEvent` スコープを追加

**Files:**
- Modify: `app/Models/EventAttendance.php`
- Test: `tests/Feature/MyAttendanceTest.php`

- [ ] **Step 1: テストを書く**

`tests/Feature/MyAttendanceTest.php` の末尾（最後の `}` の前）に追加する:

```php
// ==========================================
// waitlist tab
// ==========================================

/** ?tab=waitlist でキャンセル待ちのみ表示される */
public function test_waitlist_tab_shows_only_own_waitlisted_attendances(): void
{
    $user = User::factory()->create();
    $organizer = User::factory()->create();

    $waitlistedEvent = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'waitlisted-event']);
    $appliedEvent = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'applied-event']);

    EventAttendance::factory()->for($waitlistedEvent)->for($user)->waitlisted()->create();
    EventAttendance::factory()->for($appliedEvent)->for($user)->create();

    $response = $this->actingAs($user)->get(route('my.attendances', ['tab' => 'waitlist']));

    $response->assertSee('waitlisted-event');
    $response->assertDontSee('applied-event');
}

/** ?tab=waitlist で非公開イベントのキャンセル待ちは除外される */
public function test_waitlist_tab_excludes_non_published_events(): void
{
    $user = User::factory()->create();
    $organizer = User::factory()->create();

    $published = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'waitlist-published']);
    $draft = Event::factory()->for($organizer)->create(['status' => EventStatus::Draft, 'title' => 'waitlist-draft']);

    EventAttendance::factory()->for($published)->for($user)->waitlisted()->create();
    EventAttendance::factory()->for($draft)->for($user)->waitlisted()->create();

    $response = $this->actingAs($user)->get(route('my.attendances', ['tab' => 'waitlist']));

    $response->assertSee('waitlist-published');
    $response->assertDontSee('waitlist-draft');
}

/** デフォルト（パラメータなし）では Applied タブが表示される */
public function test_default_tab_shows_applied_not_waitlisted(): void
{
    $user = User::factory()->create();
    $organizer = User::factory()->create();

    $appliedEvent = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'my-applied']);
    $waitlistedEvent = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'my-waitlisted']);

    EventAttendance::factory()->for($appliedEvent)->for($user)->create();
    EventAttendance::factory()->for($waitlistedEvent)->for($user)->waitlisted()->create();

    $response = $this->actingAs($user)->get(route('my.attendances'));

    $response->assertSee('my-applied');
    $response->assertDontSee('my-waitlisted');
}
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
php artisan test --compact --filter="test_waitlist_tab_shows_only_own_waitlisted_attendances|test_waitlist_tab_excludes_non_published_events|test_default_tab_shows_applied_not_waitlisted"
```

期待: FAIL（スコープ未定義のため）

- [ ] **Step 3: `waitlistedToPublishedEvent` スコープを追加**

`app/Models/EventAttendance.php` の `attendedPastPublishedEvent` スコープの直後に追加する:

```php
/**
 * キャンセル待ち（Waitlisted）かつ公開中（Published）イベントの参加レコードに限定する。
 * マイページのキャンセル待ち一覧用のスコープ。
 */
#[Scope]
protected function waitlistedToPublishedEvent(Builder $query): void
{
    $query->where('status', AttendanceStatus::Waitlisted)
        ->whereHas('event', function (Builder $query): void {
            $query->where('status', EventStatus::Published);
        });
}
```

- [ ] **Step 4: テストが通ることを確認**

```bash
php artisan test --compact --filter="test_waitlist_tab_shows_only_own_waitlisted_attendances|test_waitlist_tab_excludes_non_published_events|test_default_tab_shows_applied_not_waitlisted"
```

期待: FAIL（コントローラーがまだ `?tab` を処理しないため `test_waitlist_tab_shows_only_own_waitlisted_attendances` は失敗継続）

メモ: スコープ自体は正しく追加されている。コントローラー修正後に再確認する。

- [ ] **Step 5: Pint でフォーマット**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: コミット**

```bash
git add app/Models/EventAttendance.php tests/Feature/MyAttendanceTest.php
git commit -m "feat: EventAttendance に waitlistedToPublishedEvent スコープを追加"
```

---

### Task 2: コントローラーを `?tab` パラメータ対応に更新

**Files:**
- Modify: `app/Http/Controllers/MyAttendanceController.php`

- [ ] **Step 1: `index()` メソッドを書き換える**

`app/Http/Controllers/MyAttendanceController.php` の `index()` メソッド全体を以下に置き換える:

```php
public function index(Request $request): View
{
    /** @var User $user */
    $user = auth()->user();
    $tab = $request->query('tab') === 'waitlist' ? 'waitlist' : 'applied';

    if ($tab === 'waitlist') {
        $attendances = $user->eventAttendances()
            ->waitlistedToPublishedEvent()
            ->with('event.user')
            ->orderBy('waitlisted_at', 'asc')
            ->paginate(15);
    } else {
        $attendances = $user->eventAttendances()
            ->appliedToPublishedEvent()
            ->with('event.user')
            ->orderBy('applied_at', 'asc')
            ->paginate(15);
    }

    return view('my.attendances', compact('attendances', 'tab'));
}
```

`use Illuminate\Http\Request;` の import が必要なので、ファイル上部に追加する（既に `use Illuminate\Contracts\View\View;` がある）:

```php
use Illuminate\Http\Request;
```

- [ ] **Step 2: テストが通ることを確認**

```bash
php artisan test --compact --filter="test_waitlist_tab_shows_only_own_waitlisted_attendances|test_waitlist_tab_excludes_non_published_events|test_default_tab_shows_applied_not_waitlisted"
```

期待: 3件 PASS

- [ ] **Step 3: 既存テストが壊れていないことを確認**

```bash
php artisan test --compact tests/Feature/MyAttendanceTest.php
```

期待: 全件 PASS

- [ ] **Step 4: Pint でフォーマット**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: コミット**

```bash
git add app/Http/Controllers/MyAttendanceController.php
git commit -m "feat: MyAttendanceController に tab クエリパラメータ対応を追加"
```

---

### Task 3: ビューにタブUIと waitlist 表示を追加

**Files:**
- Modify: `resources/views/my/attendances.blade.php`

- [ ] **Step 1: ビューを更新する**

`resources/views/my/attendances.blade.php` の全体を以下に置き換える:

```blade
@php
    use App\Enums\EventCategory;

    $categoryStyles = [
        EventCategory::Frontend->value => ['label' => 'フロントエンド', 'class' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300', 'gradient' => 'from-sky-500 to-blue-600'],
        EventCategory::Backend->value => ['label' => 'バックエンド', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', 'gradient' => 'from-emerald-500 to-green-600'],
        EventCategory::Database->value => ['label' => 'データベース', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', 'gradient' => 'from-amber-500 to-orange-600'],
        EventCategory::Mobile->value => ['label' => 'モバイル', 'class' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300', 'gradient' => 'from-pink-500 to-rose-600'],
        EventCategory::Ai->value => ['label' => 'AI', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300', 'gradient' => 'from-violet-500 to-purple-600'],
        EventCategory::Other->value => ['label' => 'その他', 'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300', 'gradient' => 'from-slate-500 to-slate-600'],
    ];
@endphp

<x-app-layout>
    <x-slot:title>申し込み一覧 - AI Connpass</x-slot:title>

    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">申し込み一覧</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">申し込み済み・キャンセル待ちのイベント一覧です</p>
        </div>

        {{-- タブナビゲーション --}}
        <div class="flex gap-1 mb-6 border-b border-slate-200 dark:border-[#3E3E3A]">
            <a href="{{ route('my.attendances') }}"
                class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                    {{ $tab === 'applied'
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                申込一覧
            </a>
            <a href="{{ route('my.attendances', ['tab' => 'waitlist']) }}"
                class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                    {{ $tab === 'waitlist'
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
                キャンセル待ち
            </a>
        </div>

        @if ($attendances->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-[#0a0a0a] mb-4">
                    <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </div>
                @if ($tab === 'waitlist')
                    <p class="text-base font-medium text-slate-700 dark:text-slate-300">キャンセル待ち中のイベントはありません</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">満員のイベントに申し込むとキャンセル待ちに登録されます。</p>
                @else
                    <p class="text-base font-medium text-slate-700 dark:text-slate-300">申し込み済みのイベントはありません</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">気になるイベントに参加してみましょう。</p>
                @endif
                <a href="{{ route('events.index') }}"
                    class="mt-5 inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm transition-colors">
                    イベントを探す
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($attendances as $attendance)
                    @php
                        $event = $attendance->event;
                        if ($event === null) { continue; }
                        $cat = $categoryStyles[$event->category->value] ?? ['label' => $event->category->name, 'class' => 'bg-slate-100 text-slate-600', 'gradient' => 'from-slate-500 to-slate-600'];
                        $isPast = $event->end_date->isPast();
                    @endphp
                    <a href="{{ route('events.show', $event) }}"
                        class="flex items-start gap-4 rounded-xl bg-white dark:bg-[#161615] border {{ $isPast ? 'border-slate-200 dark:border-[#3E3E3A] opacity-60' : 'border-slate-200 dark:border-[#3E3E3A] hover:border-indigo-300 dark:hover:border-indigo-700' }} p-4 transition-colors group">

                        <!-- カテゴリカラーバー -->
                        <div class="w-1 self-stretch rounded-full bg-gradient-to-b {{ $cat['gradient'] }} shrink-0"></div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $cat['class'] }}">
                                    {{ $cat['label'] }}
                                </span>
                                @if ($isPast)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                                        終了
                                    </span>
                                @endif
                            </div>
                            <p class="font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                                {{ $event->title }}
                            </p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    {{ $event->event_date->format('Y/m/d H:i') }}〜{{ $event->end_date->isSameDay($event->event_date) ? $event->end_date->format('H:i') : $event->end_date->format('m/d H:i') }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    {{ $event->prefecture }} · {{ $event->location }}
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            @if ($tab === 'waitlist')
                                <p class="text-xs text-slate-400 dark:text-slate-500">登録日</p>
                                <p class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-0.5">
                                    {{ $attendance->waitlisted_at->format('Y/m/d') }}
                                </p>
                            @else
                                <p class="text-xs text-slate-400 dark:text-slate-500">申し込み日</p>
                                <p class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-0.5">
                                    {{ $attendance->applied_at->format('Y/m/d') }}
                                </p>
                            @endif
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-indigo-500 transition-colors mt-2 ml-auto" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- ページネーション -->
            @if ($attendances->hasPages())
                <div class="mt-8">
                    {{ $attendances->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 2: 全テストを実行して確認**

```bash
php artisan test --compact tests/Feature/MyAttendanceTest.php
```

期待: 全件 PASS

- [ ] **Step 3: Pint でフォーマット**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
git add resources/views/my/attendances.blade.php
git commit -m "feat: my/attendances にキャンセル待ちタブを追加"
```

---

### Task 4: 全テスト実行で回帰がないことを確認

- [ ] **Step 1: 全テストを実行**

```bash
php artisan test --compact
```

期待: 全件 PASS（既存テストに回帰なし）
