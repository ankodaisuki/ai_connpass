# イベント削除通知メール 実装プラン

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 主催者がイベントを削除した際、Applied・Waitlisted 全参加者にメール通知を送る。

**Architecture:** 既存の `WaitlistPromotedMail` パターンに準拠した Mailable クラスを新規作成し、`EventController::destroy()` でソフトデリート後に同期送信する。送信失敗は try/catch + `Log::warning` で握りつぶす。

**Tech Stack:** Laravel 13 / PHP 8.4 / Blade / Pest v4

---

## ファイル一覧

| 操作 | ファイル |
|---|---|
| 新規作成 | `app/Mail/EventCancelledMail.php` |
| 新規作成 | `resources/views/mail/event-cancelled.blade.php` |
| 修正 | `app/Http/Controllers/EventController.php` |
| 修正（テスト追加） | `tests/Feature/EventTest.php` |

---

### Task 1: EventCancelledMail クラス + テンプレート作成

**Files:**
- Create: `app/Mail/EventCancelledMail.php`
- Create: `resources/views/mail/event-cancelled.blade.php`

このタスクはメールクラスとテンプレートの追加のみ。テストは Task 2 で実施。

- [ ] **Step 1: Mailable クラスを作成**

`app/Mail/EventCancelledMail.php` を新規作成:

```php
<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【イベント中止】{$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-cancelled');
    }
}
```

- [ ] **Step 2: メールテンプレートを作成**

`resources/views/mail/event-cancelled.blade.php` を新規作成:

```blade
<p>{{ $event->title }} は中止になりました。</p>
<p>開催予定日時: {{ $event->event_date->format('Y年m月d日 H:i') }} 〜 {{ $event->end_date->format('H:i') }}</p>
<p>開催予定場所: {{ $event->prefecture }} {{ $event->location }}</p>
<p>ご不便をおかけして申し訳ありません。</p>
```

- [ ] **Step 3: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add app/Mail/EventCancelledMail.php resources/views/mail/event-cancelled.blade.php
git commit -m "feat: EventCancelledMail クラスとテンプレートを追加"
```

---

### Task 2: EventController::destroy() の更新 + テスト

**Files:**
- Modify: `app/Http/Controllers/EventController.php` (destroy メソッド: 100〜108行)
- Test: `tests/Feature/EventTest.php` (destroy セクション末尾: 630行以降)

- [ ] **Step 1: テストを書く**

`tests/Feature/EventTest.php` の `// destroy` セクション末尾（`test_destroy_returns_403_for_non_owner` の後）に追加:

まず先頭の `use` に以下を追加（既存の use 文の後）:

```php
use App\Mail\EventCancelledMail;
use App\Models\EventAttendance;
use Illuminate\Support\Facades\Mail;
```

次にテストを追加（`}` 最後の閉じブレースの前）:

```php
    /** 削除時に Applied 参加者へ中止メールが送信される */
    public function test_destroy_sends_cancellation_email_to_applied_attendees(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $attendee = User::factory()->create();
        EventAttendance::factory()->for($event)->for($attendee)->create();

        $this->actingAs($owner)->delete(route('events.destroy', $event));

        Mail::assertSent(EventCancelledMail::class, function (EventCancelledMail $mail) use ($attendee, $event) {
            return $mail->hasTo($attendee->email)
                && $mail->event->id === $event->id;
        });
    }

    /** 削除時に Waitlisted 参加者へも中止メールが送信される */
    public function test_destroy_sends_cancellation_email_to_waitlisted_attendees(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);
        $waitlisted = User::factory()->create();
        EventAttendance::factory()->for($event)->for($waitlisted)->waitlisted()->create();

        $this->actingAs($owner)->delete(route('events.destroy', $event));

        Mail::assertSent(EventCancelledMail::class, function (EventCancelledMail $mail) use ($waitlisted, $event) {
            return $mail->hasTo($waitlisted->email)
                && $mail->event->id === $event->id;
        });
    }

    /** 参加者がいない場合はメールを送信しない */
    public function test_destroy_sends_no_email_when_no_attendees(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

        $this->actingAs($owner)->delete(route('events.destroy', $event));

        Mail::assertNothingSent();
    }
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_destroy_sends"
```

期待: FAIL（現在はメール送信処理がないため）

- [ ] **Step 3: destroy() を更新**

`app/Http/Controllers/EventController.php` の先頭 `use` ブロックに追加:

```php
use App\Mail\EventCancelledMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
```

`destroy()` メソッドを以下に置き換え（100〜108行）:

```php
    /**
     * イベント削除
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $attendees = $event->attendances()
            ->with('user')
            ->whereIn('status', [AttendanceStatus::Applied, AttendanceStatus::Waitlisted])
            ->get()
            ->map(fn (EventAttendance $a) => $a->user);

        $event->update(['status' => EventStatus::Private]);
        $event->delete();

        foreach ($attendees as $attendee) {
            try {
                Mail::to($attendee->email)->send(new EventCancelledMail($event));
            } catch (\Throwable $e) {
                Log::warning('イベント中止通知メール送信に失敗', [
                    'user_id' => $attendee->id,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('events.index')->with('success', 'イベントを削除しました。');
    }
```

- [ ] **Step 4: 追加したテストが通ることを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_destroy_sends"
```

期待: 3件 PASS

- [ ] **Step 5: destroy セクションの全テストが通ることを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_destroy"
```

期待: 全件 PASS

- [ ] **Step 6: EventTest 全体が壊れていないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact tests/Feature/EventTest.php
```

期待: 全件 PASS

- [ ] **Step 7: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add app/Http/Controllers/EventController.php tests/Feature/EventTest.php
git commit -m "feat: イベント削除時に参加者へ中止メールを送信"
```
