# イベント参加管理API 設計書

- 作成日: 2026-05-13
- 対象タスク: 「イベント参加管理API実装：申し込み・キャンセル機能＆テスト」(TASKS.md)
- 依存タスク: 認証機能、イベント管理API、データベース層 (いずれも実装済み)

## 1. 背景と目的

connpass 風サービスのイベント参加管理機能を実装する。
ユーザーは Published イベントに対して申し込み・キャンセルでき、自分の申し込み一覧やイベントの参加者一覧を取得できる。

## 2. 採用方針

- アーキテクチャは認証・イベント機能と一貫させる: Controller + Resource + Eloquent
- ルートは `/api/v1/events/{event}/attendances` (event-nested) と `/api/v1/me/attendances` (user-scoped)
- コントローラは 2 つに分割: `EventAttendanceController` (event-nested) と `MyAttendanceController` (user-scoped)
- Resource は 2 種類: `EventAttendanceResource` (参加者一覧用)、`MyAttendanceResource` (自分一覧用)
- FormRequest 不要 (リクエストボディがない / 検証はコントローラ内ビジネスルール)
- Policy 不要 (自分のリソースに限定するクエリで担保)

## 3. エンドポイント一覧

| メソッド | URL | 認証 | 概要 |
|---|---|---|---|
| GET | `/api/v1/events/{event}/attendances` | 不要 | Published イベントの参加者一覧 (Applied のみ、15件/ページ、applied_at 昇順) |
| POST | `/api/v1/events/{event}/attendances` | 必須 | イベント申し込み |
| DELETE | `/api/v1/events/{event}/attendances` | 必須 | 自分の申し込みをキャンセル |
| GET | `/api/v1/me/attendances` | 必須 | 自分の申し込み一覧 (Applied のみ、15件/ページ、applied_at 昇順) |

### 3.1 GET /api/v1/events/{event}/attendances (参加者一覧)

**処理**

1. Route Model Binding で `Event` を取得 (SoftDeleted は 404)
2. `$event->status !== Published` → 404 (存在を秘匿)
3. クエリ: `EventAttendance::where('event_id', $event->id)->where('status', AttendanceStatus::Applied)->with('user')->orderBy('applied_at', 'asc')->paginate(15)`

**レスポンス** (`200 OK`)

```json
{
  "data": [
    { "id": 1, "user": { "id": 1, "name": "..." }, "applied_at": "..." }
  ],
  "links": {...},
  "meta": {...}
}
```

### 3.2 POST /api/v1/events/{event}/attendances (申し込み)

**認証**: `auth:sanctum` + `active.user`

**ビジネスルール検証順序**:

1. イベントが SoftDeleted → 404 (Route Model Binding)
2. `$event->status !== Published` → 404 (存在を秘匿)
3. `$event->event_date->isPast()` → 422 `{ "message": "このイベントはすでに開始しています。" }`
4. `$event->user_id === $authUser->id` → 422 `{ "message": "作成者は自分のイベントに申し込めません。" }`
5. 既存 attendance を `EventAttendance::where('event_id', $event->id)->where('user_id', $authUser->id)->first()` で検索:
   - 存在 & `status === Applied` → 422 `{ "message": "すでに申し込み済みです。" }`
   - 存在 & `status === Cancelled` → 後述の「再申し込み」へ
   - 存在しない → 「新規申し込み」へ

6. 定員チェック (新規 / 再申し込み 共通):
   - `EventAttendance::where('event_id', $event->id)->where('status', AttendanceStatus::Applied)->count() >= $event->capacity` → 422 `{ "message": "定員に達しています。" }`

7. 新規申し込み: `EventAttendance::create([...])` で `status=Applied`, `applied_at=now()` を保存
8. 再申し込み: 既存ロウを `update(['status' => Applied, 'applied_at' => now(), 'cancelled_at' => null])`

**レスポンス** (`201 Created`)

```json
{ "data": { "id": ..., "user": { ... }, "applied_at": "..." } }
```

### 3.3 DELETE /api/v1/events/{event}/attendances (キャンセル)

**認証**: `auth:sanctum` + `active.user`

**処理**:

1. イベントが SoftDeleted → 404
2. `$event->event_date->isPast()` → 422 `{ "message": "このイベントはすでに開始しています。" }`
3. 自分の Applied 申し込みを検索: `EventAttendance::where('event_id', $event->id)->where('user_id', $authUser->id)->where('status', AttendanceStatus::Applied)->first()`
   - 存在しない → 404 (申し込んでいない)
4. `update(['status' => Cancelled, 'cancelled_at' => now()])`

**レスポンス** (`204 No Content`)

### 3.4 GET /api/v1/me/attendances (自分の申し込み一覧)

**認証**: `auth:sanctum` + `active.user`

**処理**:

- `$authUser->eventAttendances()->where('status', AttendanceStatus::Applied)->with('event')->orderBy('applied_at', 'asc')->paginate(15)`

**レスポンス** (`200 OK`)

```json
{
  "data": [
    {
      "id": 1,
      "event": {
        "id": 5,
        "title": "Laravel 勉強会",
        "event_date": "...",
        "prefecture": "東京都",
        "location": "...",
        "capacity": 30
      },
      "applied_at": "..."
    }
  ],
  "links": {...},
  "meta": {...}
}
```

## 4. Resource 定義

### 4.1 EventAttendanceResource

```php
return [
    'id' => $this->id,
    'user' => [
        'id' => $this->user->id,
        'name' => $this->user->name,
    ],
    'applied_at' => $this->applied_at->toIso8601ZuluString(),
];
```

### 4.2 MyAttendanceResource

```php
return [
    'id' => $this->id,
    'event' => [
        'id' => $this->event->id,
        'title' => $this->event->title,
        'event_date' => $this->event->event_date->toIso8601ZuluString(),
        'prefecture' => $this->event->prefecture,
        'location' => $this->event->location,
        'capacity' => $this->event->capacity,
    ],
    'applied_at' => $this->applied_at->toIso8601ZuluString(),
];
```

## 5. ファイル構成

新規作成:

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── EventAttendanceController.php   ... index/store/destroy (event-nested)
│   │           └── MyAttendanceController.php       ... index (/me/attendances)
│   └── Resources/
│       └── Api/
│           └── V1/
│               ├── EventAttendanceResource.php      ... 参加者一覧用
│               └── MyAttendanceResource.php         ... 自分一覧用
tests/
└── Feature/
    └── Api/
        └── V1/
            ├── EventAttendanceTest.php
            └── MyAttendanceTest.php
```

変更:

- `routes/api.php` ... 4 ルート追加 (events/{event}/attendances 配下 3 ルート、me/attendances 1 ルート)

## 6. 業務ロジック詳細

### 6.1 定員カウント方法

```php
EventAttendance::where('event_id', $event->id)
    ->where('status', AttendanceStatus::Applied)
    ->count()
```

SoftDeletes グローバルスコープにより `deleted_at IS NULL` の行のみが対象となる (今回 attendance の soft delete は API では発生させないが、念のため)。

**作成者は定員に含めない**: 作成者は申し込み自体ができないため、自然に除外される。

### 6.2 再申し込みのロジック

ユニーク制約 `(event_id, user_id)` のため、キャンセル済みロウを物理削除せず UPDATE で再利用する。既存 attendance を SoftDeletes スコープ内で検索 (`withTrashed` は使わない) し、`status=Cancelled` なら以下を更新:

```php
$attendance->update([
    'status' => AttendanceStatus::Applied,
    'applied_at' => now(),
    'cancelled_at' => null,
]);
```

定員チェックは再申し込みでも実施する。「キャンセル済み → 再申し込み可」だが、その間に他人が枠を埋めていたら拒否される。

### 6.3 競合状態 (Race Condition)

定員チェックと INSERT/UPDATE の間で同時申し込みがあると、定員を超える可能性がある。本タスクのスコープ外 (`Event::lockForUpdate()` 等の DB レベルロックは導入しない)。実運用では問題になり得るが MVP として許容。

## 7. テスト計画

### 7.1 EventAttendanceTest (`tests/Feature/Api/V1/EventAttendanceTest.php`)

**index (GET /events/{event}/attendances)**:
- 正常系: Published イベントの Applied 参加者を返却 (3件作成、1件キャンセルで2件表示)
- 異常系: Draft イベントは 404
- 異常系: Private イベントは 404
- 異常系: SoftDeleted イベントは 404
- ページネーション: 16件で 1ページ目 15件、メタ確認
- ソート: applied_at 昇順

**store (POST /events/{event}/attendances)**:
- 正常系: 認証ユーザーが Published イベントに申し込み、201、DB に Applied で保存
- 正常系: キャンセル済み状態から再申し込み (status=Applied, cancelled_at=null になる)
- 異常系: 認証なし → 401
- 異常系: 凍結ユーザー → 403
- 異常系: Draft → 404
- 異常系: 過去イベント → 422 ("このイベントはすでに開始しています。")
- 異常系: 作成者本人 → 422 ("作成者は自分のイベントに申し込めません。")
- 異常系: 定員オーバー → 422 ("定員に達しています。")
- 異常系: 重複申し込み (既に Applied) → 422 ("すでに申し込み済みです。")

**destroy (DELETE /events/{event}/attendances)**:
- 正常系: status=Cancelled, cancelled_at セット、204
- 異常系: 認証なし → 401
- 異常系: 凍結ユーザー → 403
- 異常系: 申し込んでいない → 404
- 異常系: 過去イベント → 422

### 7.2 MyAttendanceTest (`tests/Feature/Api/V1/MyAttendanceTest.php`)

**index (GET /me/attendances)**:
- 正常系: 自分の Applied 申し込みのみ返却、event 情報含む
- Cancelled は除外
- 他人の申し込みは含まれない
- 認証なし → 401
- 凍結ユーザー → 403
- ページネーション: 16件で 1ページ目 15件
- ソート: applied_at 昇順

## 8. スコープ外（今回は実装しない）

- 検索・フィルタリング (別タスク)
- 申し込み一覧の Cancelled 含むオプション
- 定員管理のロック (Race Condition 対応)
- メール通知
- キャンセル待ち (waitlist) 機能

## 9. 受け入れ基準

- [ ] 4 エンドポイントが期待どおりのステータスコードとレスポンスを返す
- [ ] 申し込み・キャンセル・再申し込みのフローが動作する
- [ ] 定員、過去イベント、作成者本人、Draft/Private のいずれも適切に拒否される
- [ ] `php artisan test --compact tests/Feature/Api/V1/` で全 PASS
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
- [ ] TASKS.md のイベント参加管理 API 実装が完了に移動している
