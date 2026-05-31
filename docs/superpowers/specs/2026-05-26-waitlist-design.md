# キャンセル待ち機能 設計仕様

作成日: 2026-05-26

## 概要

満員のイベントに対してキャンセル待ち登録を可能にする。空きが生じた場合は登録順（先着順）に自動昇格し、メール通知と Google カレンダー登録を行う。

---

## 要件

| # | 要件 |
|---|---|
| 1 | 定員満のイベントでキャンセル待ち登録ができる |
| 2 | キャンセル待ちの上限は定員（`capacity`）と同数 |
| 3 | キャンセルが発生した際、キャンセル待ち最古のユーザーを自動昇格する |
| 4 | 自動昇格時に Google カレンダーへ予定を自動追加する（ベストエフォート） |
| 5 | キャンセル待ち登録時に確認メールを送信する |
| 6 | 自動昇格時に昇格通知メールを送信する |
| 7 | 主催者はイベント詳細ページの別タブでキャンセル待ち一覧を確認できる |
| 8 | キャンセル待ちユーザーは自分でキャンセルできる |

---

## データ層

### `AttendanceStatus` Enum

```php
enum AttendanceStatus: int
{
    case Applied    = 0;
    case Cancelled  = 1;
    case Waitlisted = 2; // 追加
}
```

### `event_attendances` テーブル 追加カラム

| カラム | 型 | NULL | 説明 |
|---|---|---|---|
| `waitlisted_at` | `datetime` | YES | キャンセル待ち登録日時。昇順で先着順を管理 |

### ステータス遷移

```
申し込み（定員あり）          → Applied
申し込み（定員満・待ち枠あり） → Waitlisted
申し込み（定員満・待ち枠も満） → エラー「キャンセル待ちも満員です」
キャンセル（Applied）         → Cancelled → promoteFromWaitlist() を呼ぶ
キャンセル（Waitlisted）      → Cancelled（順番の繰り下げは不要、時刻順で自動管理）
自動昇格                      → Waitlisted（最古）→ Applied + メール + Google Calendar
```

---

## サービス層

### `EventAttendanceService` 変更点

#### `apply(Event $event, User $user): void`

1. 終了済み・自分のイベント・重複申し込みチェック（既存通り）
2. `Applied` カウント < `capacity` → 既存通り Applied で登録
3. `Applied` カウント >= `capacity` かつ `Waitlisted` カウント < `capacity` → `waitlistApply()` に委譲
4. 両方満員 → `AttendanceException('キャンセル待ちも満員です。')`

#### `waitlistApply(Event $event, User $user): void` （新規）

1. 既存レコードがあれば `Waitlisted` に更新、なければ新規作成
2. `waitlisted_at = now()` をセット
3. `WaitlistConfirmationMail` を送信（現在の待ち番号を含む）

#### `cancel(Event $event, User $user): void`

- Applied・Waitlisted どちらのキャンセルも受け付ける
- Applied キャンセル後に `promoteFromWaitlist($event)` を呼ぶ
- Waitlisted キャンセルは `promoteFromWaitlist()` を呼ばない

#### `promoteFromWaitlist(Event $event): void` （新規）

1. `Waitlisted` かつ `waitlisted_at` 昇順で最古のレコードを取得
2. 存在しなければ即リターン
3. `Applied` に昇格（`status = Applied`, `applied_at = now()`, `waitlisted_at = null`）
4. `WaitlistPromotedMail` を送信
5. Google カレンダーに予定追加（`syncCalendarOnApply()`、ベストエフォート）

---

## メール層

### `WaitlistConfirmationMail`

- **送信タイミング**: キャンセル待ち登録時
- **宛先**: 登録したユーザー
- **内容**: イベント名、現在のキャンセル待ち番号（N番目）、キャンセル方法

### `WaitlistPromotedMail`

- **送信タイミング**: 自動昇格時
- **宛先**: 昇格したユーザー
- **内容**: イベント名、申し込み確定の旨、イベント詳細ページへのリンク

---

## UI層

### イベント詳細ページ（参加者向け）

| ユーザー状態 | 表示内容 |
|---|---|
| 未申し込み・定員あり | 「参加申し込みをする」ボタン（既存） |
| 未申し込み・定員満・待ち枠あり | 「キャンセル待ちに登録する」ボタン |
| 未申し込み・定員満・待ち枠も満 | 「満員です」テキスト（ボタンなし） |
| キャンセル待ち登録中 | 「キャンセル待ち中（N番目）」バッジ + 「キャンセルする」ボタン |
| 申し込み済み | 既存通り |

### イベント詳細ページ（主催者向け）

- 参加者一覧の隣に「キャンセル待ち」タブを追加
- キャンセル待ちユーザーを `waitlisted_at` 昇順（登録順）で表示
- カラム: 番号、ユーザー名、登録日時

---

## コントローラー

`EventAttendanceController::store()` は軽微な変更のみ。`apply()` が結果の `AttendanceStatus`（Applied または Waitlisted）を返すようにし、コントローラーがその値に応じてフラッシュメッセージを切り替える。

- Applied → `'参加申し込みが完了しました。'`
- Waitlisted → `'キャンセル待ちに登録しました。'`

`destroy()` は Applied・Waitlisted どちらのキャンセルも同じ処理で受け付けるため変更なし。

---

## テスト（`EventAttendanceTest.php` 追記）

| # | テスト内容 |
|---|---|
| 1 | 満員時にキャンセル待ち登録できる |
| 2 | キャンセル待ちも満員の場合は登録拒否 |
| 3 | Applied キャンセル時に自動昇格が発生する |
| 4 | Waitlisted キャンセル時は自動昇格が発生しない |
| 5 | 昇格時に昇格通知メールが送信される |
| 6 | 昇格時に Google カレンダーへ予定が追加される |
| 7 | キャンセル待ち登録時に確認メールが送信される |
| 8 | 確認メール・昇格通知メールの内容検証 |
| 9 | キャンセル待ちが存在しない場合は自動昇格が何も起こさない |
| 10 | 自動昇格は waitlisted_at 昇順（先着順）で行われる |
