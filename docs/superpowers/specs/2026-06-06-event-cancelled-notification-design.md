# イベント削除通知メール 設計

## 概要

主催者がイベントを削除した際、参加申し込み済み（Applied）およびキャンセル待ち（Waitlisted）の全ユーザーにメールで通知する。

## 設計方針

既存の `WaitlistPromotedMail` / `WaitlistConfirmationMail` のパターンに準拠する。同期送信、try/catch + `Log::warning` で失敗を握りつぶす（キュー未使用）。

---

## 変更 1: EventCancelledMail

`app/Mail/EventCancelledMail.php` を新規作成する。

```php
class EventCancelledMail extends Mailable
{
    public function __construct(public readonly Event $event) {}

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

---

## 変更 2: メールテンプレート

`resources/views/mail/event-cancelled.blade.php` を新規作成する。

内容:
- イベントが中止になった旨のメッセージ
- イベント名・開催日時・場所

---

## 変更 3: EventController::destroy()

削除前に参加者を取得し、削除後にメールを送信する。

```php
public function destroy(Event $event): RedirectResponse
{
    $this->authorize('delete', $event);

    // 削除前に参加者を取得
    $attendees = $event->attendances()
        ->with('user')
        ->whereIn('status', [AttendanceStatus::Applied, AttendanceStatus::Waitlisted])
        ->get()
        ->map(fn ($a) => $a->user);

    $event->update(['status' => EventStatus::Private]);
    $event->delete();

    // 削除後にメール送信
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

---

## テスト

`tests/Feature/EventTest.php` に追加:

- 主催者が削除すると Applied 参加者にメールが送信される
- 主催者が削除すると Waitlisted 参加者にメールが送信される
- 参加者がいない場合はメール送信なし

---

## 変更ファイル一覧

| 操作 | ファイル |
|---|---|
| 新規作成 | `app/Mail/EventCancelledMail.php` |
| 新規作成 | `resources/views/mail/event-cancelled.blade.php` |
| 修正 | `app/Http/Controllers/EventController.php` |
| 修正（テスト追加） | `tests/Feature/EventTest.php` |
