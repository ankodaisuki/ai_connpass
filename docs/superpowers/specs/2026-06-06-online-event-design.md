# オンライン・ハイブリッドイベント対応 設計

## 概要

イベントに「オンライン参加」と「ハイブリッド（対面＋オンライン）」を追加する。
定員は対面・オンライン合算で1つ。申し込み時に参加者が参加モードを選択する。
オンラインURLは申し込み済み（Applied）ユーザーのみに表示する。

---

## データモデル

### `events` テーブルに追加

| カラム | 型 | 制約 | 説明 |
|---|---|---|---|
| `online_url` | string | nullable | ZoomやGoogle MeetなどのURL（プラットフォーム自由） |
| `online_password` | string | nullable | ミーティングパスワード（任意） |

### `event_attendances` テーブルに追加

| カラム | 型 | 制約 | 説明 |
|---|---|---|---|
| `attendance_mode` | string | nullable | `'online'` または `'in_person'` |

### 新規 Enum `App\Enums\AttendanceMode`

```php
enum AttendanceMode: string
{
    case Online = 'online';
    case InPerson = 'in_person';

    public function label(): string
    {
        return match($this) {
            self::Online => 'オンライン',
            self::InPerson => '対面',
        };
    }
}
```

### `prefecture` 選択肢（ビューのみ変更）

既存リスト末尾の `'オンライン'` の後に `'ハイブリッド'` を追加する。

```blade
// 変更前
['北海道', ..., '沖縄県', 'オンライン']
// 変更後
['北海道', ..., '沖縄県', 'オンライン', 'ハイブリッド']
```

---

## バリデーション

`StoreEventRequest` / `UpdateEventRequest` を更新する。

```php
'online_url' => [
    Rule::when(
        in_array($this->prefecture, ['オンライン', 'ハイブリッド']),
        ['required', 'url', 'max:2048'],
        ['nullable', 'url', 'max:2048']
    ),
],
'online_password' => ['nullable', 'string', 'max:255'],
'location' => [
    Rule::when(
        $this->prefecture !== 'オンライン',
        ['required', 'string', 'max:255'],
        ['nullable', 'string', 'max:255']
    ),
],
```

バリデーションメッセージ（`attributes`）:
- `online_url` → 'オンラインURL'
- `online_password` → 'パスワード'

---

## 申し込みフロー

`EventAttendanceController::store()` に `attendance_mode` パラメータを追加する。

### attendance_mode の決定ロジック

| event.prefecture | attendance_mode |
|---|---|
| 「オンライン」 | `online`（自動セット） |
| 「ハイブリッド」 | リクエストの `attendance_mode`（必須バリデーション） |
| それ以外（対面） | `in_person`（自動セット） |

### EventAttendanceService::apply() の変更

`AttendanceMode $attendanceMode` を引数に追加し、`EventAttendance::create()` / `update()` に含める。

```php
public function apply(Event $event, User $user, AttendanceMode $attendanceMode): AttendanceStatus
```

---

## UI

### 作成・編集フォーム（`events/create.blade.php` / `events/edit.blade.php`）

- prefecture が「オンライン」または「ハイブリッド」に変わると `online_url`・`online_password` フィールドを JS で表示
- prefecture が「オンライン」の場合は `location` フィールドを非表示（バリデーションも nullable）
- ハイブリッドの場合は `location`（対面会場）と `online_url` を両方表示

### イベント詳細ページ（`events/show.blade.php`）

#### 申し込みボタン周辺

ハイブリッドイベントの場合、申し込みボタンの上に参加モード選択ラジオボタンを追加する。

```blade
@if ($event->prefecture === 'ハイブリッド')
    <div class="space-y-2">
        <label><input type="radio" name="attendance_mode" value="in_person"> 対面で参加</label>
        <label><input type="radio" name="attendance_mode" value="online"> オンラインで参加</label>
    </div>
@endif
```

対面のみ・オンラインのみの場合はラジオ不要（hidden inputで自動セット）。

#### 申し込み済みバッジ

参加モードを表示する。

```blade
参加申し込み済み（{{ $myAttendance->attendance_mode?->label() }}）
```

#### オンライン参加情報カード（Applied ユーザーのみ表示）

オンライン / ハイブリッドイベントで `$myAttendance !== null` の場合のみ表示。

```
オンライン参加情報
URL: https://zoom.us/j/xxxxx
パスワード: abc123（設定されている場合のみ表示）

⚠ 参加者の入室承認機能（待機室・ロビー等）を有効にすることを推奨します。
```

#### 主催者の参加者一覧

`attendance_mode` を参加者一覧に列追加する（対面 / オンライン）。

---

## テスト

`tests/Feature/EventTest.php`:
- `online_url` が必須になるバリデーション（オンライン・ハイブリッド）
- `online_url` が任意になるバリデーション（対面）
- `location` が nullable になるバリデーション（オンライン）

`tests/Feature/EventAttendanceTest.php`:
- ハイブリッドイベントで `attendance_mode` なしの申し込みが失敗する
- ハイブリッドイベントで `attendance_mode=online` の申し込みが成功する
- 対面イベントで `attendance_mode` が自動で `in_person` にセットされる
- オンラインイベントで `attendance_mode` が自動で `online` にセットされる
- Applied ユーザーにオンラインURLが表示される（ビューテストは省略、サービス層でカバー）

---

## 変更ファイル一覧

| 操作 | ファイル |
|---|---|
| 新規作成 | `database/migrations/XXXX_add_online_fields_to_events.php` |
| 新規作成 | `database/migrations/XXXX_add_attendance_mode_to_event_attendances.php` |
| 新規作成 | `app/Enums/AttendanceMode.php` |
| 修正 | `app/Models/Event.php` |
| 修正 | `app/Models/EventAttendance.php` |
| 修正 | `database/factories/EventFactory.php` |
| 修正 | `database/factories/EventAttendanceFactory.php` |
| 修正 | `app/Http/Requests/Event/StoreEventRequest.php` |
| 修正 | `app/Http/Requests/Event/UpdateEventRequest.php` |
| 修正 | `app/Http/Controllers/EventAttendanceController.php` |
| 修正 | `app/Services/EventAttendanceService.php` |
| 修正 | `resources/views/events/create.blade.php` |
| 修正 | `resources/views/events/edit.blade.php` |
| 修正 | `resources/views/events/show.blade.php` |
| 修正（テスト追加） | `tests/Feature/EventTest.php` |
| 修正（テスト追加） | `tests/Feature/EventAttendanceTest.php` |
