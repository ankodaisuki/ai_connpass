# イベント管理API 設計書

- 作成日: 2026-05-13
- 対象タスク: 「イベント管理API実装：CRUD操作＆テスト」(TASKS.md)
- 依存タスク: 認証機能(Sanctum)実装済み、データベース層構築済み

## 1. 背景と目的

connpass 風イベント管理サービスの中核機能として、イベント情報の CRUD API を提供する。
データベース層は完了済み (events テーブル、Event モデル、EventStatus/EventCategory Enum、EventFactory)。
本タスクではこれらを利用して REST API を構築する。

検索・フィルタリング機能は別タスク「検索・フィルタリング機能実装」として後続予定であり、本タスクの一覧は単純なページネーション付きリストとする。

## 2. 採用方針

- アーキテクチャは認証機能 (`AuthController`) と一貫させる: FormRequest + Policy + Resource + Eloquent
- ルートは `/api/v1/events/*`
- Route Model Binding を使ってコントローラ引数に `Event $event` を受ける
- 削除はソフトデリート (`SoftDeletes`) + status=Private への更新の組み合わせ
- 認可は `EventPolicy` (update/delete のみ)。閲覧系は Policy ではなくコントローラ内で判定

## 3. エンドポイント一覧

| メソッド | URL | 認証 | 概要 |
|---|---|---|---|
| GET | `/api/v1/events` | 不要 | 一覧 (Published のみ、15件/ページ、event_date 昇順) |
| GET | `/api/v1/events/{event}` | 不要 | 詳細 (Published は全員、Draft/Private は本人のみ) |
| POST | `/api/v1/events` | 必須 | 作成 |
| PUT | `/api/v1/events/{event}` | 必須＋本人 | 更新 |
| DELETE | `/api/v1/events/{event}` | 必須＋本人 | 削除 (status=Private + soft delete) |

### 3.1 GET /api/v1/events (index)

**処理**

- `Event::query()->where('status', EventStatus::Published)->orderBy('event_date', 'asc')->paginate(15)`
- SoftDeleted は自動的に除外される (SoftDeletes グローバルスコープ)

**レスポンス** (`200 OK`)

```json
{
  "data": [
    { "id": 1, "title": "...", ..., "user": { "id": 1, "name": "..." } }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

(Laravel の `ResourceCollection::paginate()` 自動生成構造)

### 3.2 GET /api/v1/events/{event} (show)

**処理**

1. Route Model Binding により `Event::findOrFail($id)` (SoftDeleted は 404)
2. Published の場合: 誰でもレスポンス
3. Draft / Private の場合:
   - 認証なし → 404 (存在を秘匿)
   - 認証あり かつ 本人 → レスポンス
   - 認証あり かつ 他人 → 404 (存在を秘匿)

**レスポンス** (`200 OK`)

```json
{ "data": { "id": 1, "title": "...", ..., "user": { ... } } }
```

### 3.3 POST /api/v1/events (store)

**認証**: `auth:sanctum` + `active.user` ミドルウェア

**バリデーション** (`StoreEventRequest`)

- `title`: required, string, max:255
- `description`: nullable, string
- `category`: required, integer, EventCategory enum 値 (1-6) — `Rule::enum(EventCategory::class)`
- `prefecture`: required, string, max:10
- `location`: required, string, max:255
- `event_date`: required, date, `after:now`
- `capacity`: required, integer, min:1
- `status`: nullable, integer, EventStatus enum 値 (0,1,2) — `Rule::enum(EventStatus::class)`。未指定なら Draft(0)

**処理**

- `user_id` は認証済みユーザーから自動設定 (リクエストでは受け取らない)
- `status` 未指定なら `EventStatus::Draft`

**レスポンス** (`201 Created`)

```json
{ "data": { "id": ..., ... } }
```

### 3.4 PUT /api/v1/events/{event} (update)

**認証**: `auth:sanctum` + `active.user` + EventPolicy::update (本人のみ)

**バリデーション** (`UpdateEventRequest`)

POST と同じルール。PUT セマンティクスで全フィールド必須。

**処理**

- Policy で本人確認 (`$user->id === $event->user_id`)。失敗時 403
- ソフトデリート済みは 404
- バリデーション通過後、`$event->update($validated)`

**レスポンス** (`200 OK`): 更新後の event を `EventResource` で返却

### 3.5 DELETE /api/v1/events/{event} (destroy)

**認証**: `auth:sanctum` + `active.user` + EventPolicy::delete (本人のみ)

**処理**

```php
$event->update(['status' => EventStatus::Private]);
$event->delete(); // SoftDeletes で deleted_at セット
```

**レスポンス** (`204 No Content`)

削除後はクエリから除外されるため、再度の GET/PUT/DELETE は全て 404。

## 4. EventResource

```php
return [
    'id' => $this->id,
    'title' => $this->title,
    'description' => $this->description,
    'category' => $this->category->value,
    'prefecture' => $this->prefecture,
    'location' => $this->location,
    'event_date' => $this->event_date->toIso8601ZuluString(),
    'capacity' => $this->capacity,
    'status' => $this->status->value,
    'user' => [
        'id' => $this->user->id,
        'name' => $this->user->name,
    ],
    'created_at' => $this->created_at->toIso8601ZuluString(),
    'updated_at' => $this->updated_at->toIso8601ZuluString(),
];
```

`user` は eager-load して N+1 を避ける (`with('user')` をコントローラで指定)。

## 5. EventPolicy

```php
class EventPolicy
{
    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }
}
```

`viewAny` / `view` / `create` は実装不要 (コントローラまたはミドルウェアで判定)。

Laravel 13 ではアプリケーション設定で `auth:policies` の自動検出が有効なため、`AuthServiceProvider` への明示登録は不要。

## 6. ファイル構成

新規作成:

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── EventController.php       ... 5メソッド
│   ├── Requests/
│   │   └── Api/
│   │       └── V1/
│   │           └── Event/
│   │               ├── StoreEventRequest.php
│   │               └── UpdateEventRequest.php
│   └── Resources/
│       └── Api/
│           └── V1/
│               └── EventResource.php
├── Policies/
│   └── EventPolicy.php
tests/
└── Feature/
    └── Api/
        └── V1/
            └── EventTest.php
```

変更:

- `routes/api.php` ... v1/events ルートを追記

## 7. テスト計画

`tests/Feature/Api/V1/EventTest.php` に約 22 ケース:

### 7.1 index (GET /events)

- 正常系: Published のみ返却 (Draft/Private/SoftDeleted は除外)
- ページネーション: 16件作成時に 1ページ目=15件, 2ページ目=1件
- ソート: event_date 昇順

### 7.2 show (GET /events/{event})

- 正常系: Published は認証不要で取得可
- 正常系: Draft を本人が取得可
- 正常系: Private を本人が取得可
- 異常系: Draft を非認証ユーザーが取得 → 404
- 異常系: Private を他人が取得 → 404
- 異常系: SoftDeleted は 404

### 7.3 store (POST /events)

- 正常系: 認証ユーザーがイベント作成。user_id が認証ユーザーになる
- 正常系: status 未指定なら Draft で作成される
- 異常系: 認証なし → 401
- 異常系: 凍結ユーザー → 403
- 異常系: title 欠如 → 422
- 異常系: event_date が過去 → 422
- 異常系: capacity が 0 以下 → 422
- 異常系: category が範囲外 → 422

### 7.4 update (PUT /events/{event})

- 正常系: 本人が更新可
- 異常系: 認証なし → 401
- 異常系: 他人 → 403
- 異常系: バリデーション失敗 → 422
- 異常系: SoftDeleted を更新しようとすると 404

### 7.5 destroy (DELETE /events/{event})

- 正常系: 本人が削除、status=Private になり deleted_at がセットされる、204 No Content
- 削除後: 再度 GET/PUT/DELETE は 404
- 異常系: 認証なし → 401
- 異常系: 他人 → 403

## 8. スコープ外（今回は実装しない）

- 検索・フィルタリング機能 (別タスク)
- イベント参加 (申し込み/キャンセル) 機能 (別タスク)
- 「自分のイベント一覧」エンドポイント
- 物理削除エンドポイント
- 復元 (restore) エンドポイント

## 9. 受け入れ基準

- [ ] 5 エンドポイントが期待どおりのステータスコードとレスポンスを返す
- [ ] Policy が他人による更新・削除を防ぐ
- [ ] DELETE が status=Private と deleted_at の両方を更新する
- [ ] 凍結ユーザー (`status=0`) は store/update/destroy で 403
- [ ] `php artisan test --compact tests/Feature/Api/V1/EventTest.php` で全テスト PASS
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
