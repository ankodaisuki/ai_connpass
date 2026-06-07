# my/attendances キャンセル待ちタブ設計

## 概要

`/my/attendances` ページに「申込一覧」と「キャンセル待ち」のタブを追加する。
タブ切り替えはURLクエリパラメータ（`?tab=waitlist`）で行う。

## 変更ファイル

### 1. `app/Models/EventAttendance.php`

`waitlistedToPublishedEvent()` スコープを追加する。

```php
#[Scope]
protected function waitlistedToPublishedEvent(Builder $query): void
{
    $query->where('status', AttendanceStatus::Waitlisted)
        ->whereHas('event', function (Builder $query): void {
            $query->where('status', EventStatus::Published);
        });
}
```

### 2. `app/Http/Controllers/MyAttendanceController.php`

`index()` メソッドを更新し、`?tab` クエリパラメータを受け取る。

- `tab=waitlist`: `waitlistedToPublishedEvent` スコープ + `waitlisted_at` 昇順
- それ以外（デフォルト `applied`）: 現状どおり `appliedToPublishedEvent` + `applied_at` 昇順

ビューに `$tab`（文字列）と `$attendances` を渡す。

### 3. `resources/views/my/attendances.blade.php`

- ページタイトル下にタブUI（申込一覧 / キャンセル待ち）を追加
- アクティブタブは `$tab` で判定
- カード右端の日付表示:
  - `applied` タブ: 「申し込み日」＋ `applied_at`
  - `waitlist` タブ: 「登録日」＋ `waitlisted_at`
- 空状態のメッセージも各タブに合わせて変更

## テスト

`tests/Feature/MyAttendanceTest.php` に以下を追加:

- `?tab=waitlist` でキャンセル待ちのみ表示される
- `?tab=waitlist` で Applied の申込が表示されない
- デフォルト（パラメータなし）で Applied のみ表示される（既存テスト維持）
