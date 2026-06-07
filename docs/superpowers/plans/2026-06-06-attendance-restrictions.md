# 出席管理・キャンセル制限 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** イベント終了後の出欠管理をブロックし、キャンセル待ちユーザーのキャンセルを禁止する。

**Architecture:** サービス層・コントローラー層でサーバーサイド保護を追加し、UIは補助的な表示制御を行う。ページが古い状態でも、サーバー側チェックが全リクエストを正しく弾く。

**Tech Stack:** Laravel 13 / PHP 8.4 / Blade / Pest v4

---

## ファイル一覧

| 操作 | ファイル |
|---|---|
| 修正 | `app/Http/Controllers/EventAttendanceController.php` |
| 修正 | `app/Services/EventAttendanceService.php` |
| 修正 | `resources/views/events/show.blade.php` |
| 修正（テスト追加） | `tests/Feature/EventAttendanceTest.php` |

---

### Task 1: 終了後の出欠管理ブロック（サービス保護 + テスト）

**Files:**
- Modify: `app/Http/Controllers/EventAttendanceController.php`
- Test: `tests/Feature/EventAttendanceTest.php`

- [ ] **Step 1: テストを書く**

`tests/Feature/EventAttendanceTest.php` の `// update - 出欠管理（UC5）` セクションの末尾（最後の update テストの後）に追加:

```php
/** イベント終了後は主催者でも出欠を記録できない */
public function test_organizer_cannot_mark_attendance_after_event_ends(): void
{
    $organizer = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create([
        'user_id' => $organizer->id,
        'event_date' => now()->subHours(3),
        'end_date' => now()->subHour(),
    ]);
    $attendance = EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
        'attended_at' => null,
    ]);

    $response = $this->actingAs($organizer)
        ->from(route('events.show', $event))
        ->patch(route('events.attendances.update', [$event, $attendance]), [
            'attended_at' => now()->format('Y-m-d H:i:s'),
        ]);

    $response->assertRedirect(route('events.show', $event));
    $response->assertSessionHasErrors(['attendance']);
    $this->assertNull($attendance->refresh()->attended_at);
}
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_organizer_cannot_mark_attendance_after_event_ends"
```

期待: FAIL（現在は終了後でも更新できるため）

- [ ] **Step 3: `update()` に終了後ブロックを追加**

`app/Http/Controllers/EventAttendanceController.php` の `update()` メソッドで、既存の「開始前ブロック」の**前**に終了後ブロックを追加する。

変更前（該当箇所）:
```php
if (! $event->event_date->isPast()) {
    return back()->withErrors(['attendance' => 'イベント開始前は出欠を記録できません。']);
}
```

変更後:
```php
if ($event->end_date->isPast()) {
    return back()->withErrors(['attendance' => 'イベントが終了しているため出欠を記録できません。']);
}

if (! $event->event_date->isPast()) {
    return back()->withErrors(['attendance' => 'イベント開始前は出欠を記録できません。']);
}
```

- [ ] **Step 4: テストが通ることを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_organizer_cannot_mark_attendance_after_event_ends"
```

期待: PASS

- [ ] **Step 5: 既存テストが壊れていないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact tests/Feature/EventAttendanceTest.php
```

期待: 全件 PASS

- [ ] **Step 6: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add app/Http/Controllers/EventAttendanceController.php tests/Feature/EventAttendanceTest.php
git commit -m "feat: イベント終了後の出欠管理をブロック"
```

---

### Task 2: キャンセル待ちのキャンセル禁止（サービス保護 + テスト）

**Files:**
- Modify: `app/Services/EventAttendanceService.php`
- Test: `tests/Feature/EventAttendanceTest.php`

- [ ] **Step 1: テストを書く**

`tests/Feature/EventAttendanceTest.php` の `// destroy - キャンセル` セクションの末尾に追加:

```php
/** キャンセル待ちユーザーはキャンセルできない */
public function test_destroy_fails_for_waitlisted_user(): void
{
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner)->create([
        'status' => EventStatus::Published,
    ]);
    $applicant = User::factory()->create();
    EventAttendance::factory()->for($event)->for($applicant)->waitlisted()->create();

    $this->actingAs($applicant)
        ->from(route('events.show', $event))
        ->delete(route('events.attendances.destroy', $event))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHasErrors(['attendance']);
}
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_destroy_fails_for_waitlisted_user"
```

期待: FAIL（現在はキャンセル待ちもキャンセルできてしまうため）

- [ ] **Step 3: `cancel()` に Waitlisted チェックを追加**

`app/Services/EventAttendanceService.php` の `cancel()` メソッド内、DB トランザクションの `$attendance` 取得直後（`if ($attendance === null)` チェックの直後）に追加する。

変更前（該当箇所）:
```php
if ($attendance === null) {
    throw new AttendanceException('申し込みが見つかりません。');
}

$wasApplied = $attendance->status === AttendanceStatus::Applied;
```

変更後:
```php
if ($attendance === null) {
    throw new AttendanceException('申し込みが見つかりません。');
}

if ($attendance->status === AttendanceStatus::Waitlisted) {
    throw new AttendanceException('キャンセル待ちの申し込みはキャンセルできません。');
}

$wasApplied = $attendance->status === AttendanceStatus::Applied;
```

- [ ] **Step 4: テストが通ることを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_destroy_fails_for_waitlisted_user"
```

期待: PASS

- [ ] **Step 5: 既存テストが壊れていないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact tests/Feature/EventAttendanceTest.php
```

期待: 全件 PASS

- [ ] **Step 6: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add app/Services/EventAttendanceService.php tests/Feature/EventAttendanceTest.php
git commit -m "feat: キャンセル待ちユーザーのキャンセルを禁止"
```

---

### Task 3: UIの補助的表示制御（ビュー更新）

**Files:**
- Modify: `resources/views/events/show.blade.php`

このタスクにテストはない（UIの補助制御はサービス層で保護済み。ビューの変更は目視確認で足りる）。

- [ ] **Step 1: 出欠ボタンを終了後 disabled にする**

`resources/views/events/show.blade.php` の出欠管理セクション（約 332〜355 行）で、`「参加」ボタン` と `「未参加」ボタン` の両方に対して `$hasStarted` のみで制御している箇所を `$hasStarted && ! $hasEnded` に変更する。

変更前（2か所、参加ボタンと未参加ボタン）:
```blade
@disabled(! $hasStarted)
class="... {{ ! $hasStarted ? 'opacity-50 cursor-not-allowed ' : '' }}..."
```

変更後（2か所とも同じ変更）:
```blade
@disabled(! $hasStarted || $hasEnded)
class="... {{ (! $hasStarted || $hasEnded) ? 'opacity-50 cursor-not-allowed ' : '' }}..."
```

また、出欠管理セクション冒頭の「イベント開始前は...」メッセージの表示条件を更新する。

変更前:
```blade
@unless ($hasStarted)
    <p class="mb-4 rounded-xl bg-amber-50 ...">
        出欠の記録はイベント開始時刻（{{ $event->event_date->format('Y/m/d H:i') }}）以降に可能になります。
    </p>
@endunless
```

変更後:
```blade
@if (! $hasStarted)
    <p class="mb-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
        出欠の記録はイベント開始時刻（{{ $event->event_date->format('Y/m/d H:i') }}）以降に可能になります。
    </p>
@elseif ($hasEnded)
    <p class="mb-4 rounded-xl bg-slate-50 dark:bg-slate-900/20 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
        イベントが終了したため出欠を記録できません。
    </p>
@endif
```

- [ ] **Step 2: キャンセル待ちのキャンセルフォームを削除する**

`resources/views/events/show.blade.php` の `@elseif ($myWaitlist !== null)` セクション（約 233〜247 行）から、キャンセルフォームを削除する。

変更前:
```blade
@elseif ($myWaitlist !== null)
    <div class="space-y-2">
        <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 text-sm font-semibold">
            キャンセル待ち中（{{ $myWaitlistPosition }}番目）
        </div>
        <form method="POST" action="{{ route('events.attendances.destroy', $event) }}"
            onsubmit="return confirm('キャンセル待ちを取り消してもよいですか？')">
            @csrf
            @method('DELETE')
            <button type="submit"
                class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-300 dark:border-[#3E3E3A] text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 text-sm transition">
                キャンセルする
            </button>
        </form>
    </div>
```

変更後（フォームを削除、バッジのみ残す）:
```blade
@elseif ($myWaitlist !== null)
    <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 text-sm font-semibold">
        キャンセル待ち中（{{ $myWaitlistPosition }}番目）
    </div>
```

- [ ] **Step 3: 全テストを実行して回帰がないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact
```

期待: 全件 PASS

- [ ] **Step 4: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add resources/views/events/show.blade.php
git commit -m "feat: 終了後の出欠ボタン無効化・キャンセル待ちキャンセルボタンを削除"
```
