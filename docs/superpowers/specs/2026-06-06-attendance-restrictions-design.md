# 出席管理・キャンセル制限 設計

## 概要

2つの制限を追加する。

1. **終了後の出席管理ブロック**: 主催者は `end_date` を過ぎたイベントの出欠を記録・変更できない
2. **キャンセル待ちのキャンセル禁止**: `Waitlisted` ステータスのユーザーは申し込みをキャンセルできない

## 設計方針

サービス層チェックを本質的な保護とし、UIは補助的な表示制御とする。ページを読み込んだまま状態が変わった場合でも、サービス層が全リクエストを弾く。

---

## 変更 1: 終了後の出席管理ブロック

### サービス保護

`app/Http/Controllers/EventAttendanceController.php` の `update()` メソッドに、既存の「開始前ブロック」と並べて「終了後ブロック」を追加する。

```php
if ($event->end_date->isPast()) {
    return back()->withErrors(['attendance' => 'イベントが終了しているため出欠を記録できません。']);
}
```

チェック順: 終了後 → 開始前 の順で判定する（終了後は開始前より具体的なメッセージのため先に評価）。

### UI 制御

`resources/views/events/show.blade.php` の出欠ボタン（「参加」「未参加」）に `$hasEnded`（既に計算済み）でのガードを追加する。

- `$hasEnded` が true のとき: ボタンを `disabled` + `opacity-50 cursor-not-allowed` スタイル（既存の `$hasStarted` パターンに合わせる）
- form の `action` は残すが、`disabled` 属性でクリックを防ぐ

---

## 変更 2: キャンセル待ちのキャンセル禁止

### サービス保護

`app/Services/EventAttendanceService.php` の `cancel()` メソッドの先頭（DB トランザクション内）に Waitlisted チェックを追加する。

```php
if ($attendance->status === AttendanceStatus::Waitlisted) {
    throw new AttendanceException('キャンセル待ちの申し込みはキャンセルできません。');
}
```

### UI 制御

`resources/views/events/show.blade.php` の `@elseif ($myWaitlist !== null)` セクションのキャンセルボタン（form）を非表示にする。キャンセル待ち中であることを示すバッジのみ残す。

---

## テスト

`tests/Feature/EventAttendanceTest.php` に以下を追加する:

- 終了後に主催者が出欠更新を試みると 422/リダイレクトでエラーになる
- キャンセル待ちユーザーがキャンセルを試みると AttendanceException でリダイレクトエラーになる

---

## 変更ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `app/Http/Controllers/EventAttendanceController.php` | `update()` に終了後ブロックを追加 |
| `app/Services/EventAttendanceService.php` | `cancel()` に Waitlisted ブロックを追加 |
| `resources/views/events/show.blade.php` | 出欠ボタンの終了後 disabled・waitlist キャンセルボタン非表示 |
| `tests/Feature/EventAttendanceTest.php` | 上記2制限のテストを追加 |
