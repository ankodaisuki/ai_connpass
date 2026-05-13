# 認証機能 設計書

- 作成日: 2026-05-13
- 対象タスク: 「認証機能を実装：ユーザー登録・ログイン・トークン更新」(TASKS.md)
- 採用方針: Laravel Sanctum を用いた API トークン認証

## 1. 背景と目的

本アプリは Laravel 13 / PHP 8.4 製の API ベースのイベント管理サービスである。
クライアント（将来的に SPA、モバイル、サードパーティツール等を想定）からの API 呼び出しを認証するための仕組みが必要であり、TASKS.md の第一段に「ユーザー登録・ログイン・トークン更新」が定義されている。

セッション認証では「トークン更新」という概念が成立しないため、Laravel 公式パッケージである **Sanctum の personal access token** を用いた API トークン認証方式を採用する。

## 2. 採用方式

### 2.1 パッケージ

- `laravel/sanctum` を `composer require` で導入する
- `php artisan install:api` を実行して以下を自動生成させる
  - `config/sanctum.php`
  - `personal_access_tokens` テーブルのマイグレーション
  - `routes/api.php`
  - `bootstrap/app.php` への API ルート登録

### 2.2 認証フロー

1. クライアントが `POST /api/v1/auth/register` または `POST /api/v1/auth/login` を呼ぶ
2. サーバーは認証成功時にプレーンテキストの API トークン (`Bearer xxxxx`) を返却する
3. クライアントは以降のリクエストの `Authorization: Bearer xxxxx` ヘッダーにトークンを含める
4. サーバーは `auth:sanctum` ミドルウェアでトークンを検証し、ユーザーを識別する
5. トークン期限（24時間）が切れる前にクライアントは `POST /api/v1/auth/refresh` を呼んで新規トークンを取得する

### 2.3 トークン期限

- `config/sanctum.php` の `expiration` を `60 * 24` 分（= 24時間）に設定する
- 期限切れトークンで認証必須エンドポイントを呼ぶと `401 Unauthenticated` が返る
- Sanctum 標準の `php artisan sanctum:prune-expired` Artisan コマンドで期限切れトークンを削除可能（運用時にスケジューラ登録を検討。本タスクのスコープ外）

## 3. エンドポイント一覧

すべてのエンドポイントは `/api/v1/auth/*` 配下に配置する。

| メソッド | URL | 認証 | 概要 |
|---|---|---|---|
| POST | `/api/v1/auth/register` | 不要 | ユーザー登録＋トークン発行 |
| POST | `/api/v1/auth/login`    | 不要 | ログイン認証＋トークン発行 |
| POST | `/api/v1/auth/refresh`  | 必須 | 新規トークン発行＋現在のトークン削除 |
| POST | `/api/v1/auth/logout`   | 必須 | 現在のトークンを削除 |
| GET  | `/api/v1/auth/me`       | 必須 | 現在の認証ユーザー情報を取得 |

### 3.1 POST /api/v1/auth/register

ユーザーを新規作成し、同時に API トークンを発行する。

**リクエスト**

```json
{
  "email": "user@example.com",
  "name": "山田 太郎",
  "password": "secret1234",
  "password_confirmation": "secret1234"
}
```

**バリデーション** (`RegisterRequest`)

- `email`: 必須、メール形式、`users.email` で一意
- `name`: 必須、文字列、255文字以下
- `password`: 必須、最低8文字、`confirmed`（`password_confirmation` と一致）

**レスポンス** (`201 Created`)

```json
{
  "data": {
    "user": { "id": 1, "email": "user@example.com", "name": "山田 太郎" },
    "token": "1|abcdef...plainTextToken",
    "token_type": "Bearer",
    "expires_at": "2026-05-14T12:00:00Z"
  }
}
```

`status` カラムはデフォルト 1（有効）が設定される。

`expires_at` の計算式は `personal_access_tokens.created_at + config('sanctum.expiration')` 分。Sanctum は DB に期限カラムを持たないため、レスポンス生成時にコントローラ側で算出する。ISO8601 UTC で出力する。

### 3.2 POST /api/v1/auth/login

メール＋パスワードで認証し、API トークンを発行する。

**リクエスト**

```json
{
  "email": "user@example.com",
  "password": "secret1234"
}
```

**バリデーション** (`LoginRequest`)

- `email`: 必須、メール形式
- `password`: 必須、文字列

**ロジック**

1. `Auth::attempt(['email' => ..., 'password' => ...])` で認証
2. 認証成功でも `user->status === UserStatus::Inactive` の場合は `403 アカウントが凍結されています` を返す
3. それ以外は新規トークンを発行してレスポンス

**レスポンス** (`200 OK`)

- 成功時: register と同じレスポンス構造（`status` コードは 200）
- 認証失敗: `422` バリデーションエラー風に `email` フィールドへ `提供された認証情報は正しくありません` を返す（Laravel の慣習）
- 凍結アカウント: `403 Forbidden`

### 3.3 POST /api/v1/auth/refresh

認証必須。現在使用中のトークンを削除し、新規トークンを発行する。

**ロジック**

1. `$request->user()->currentAccessToken()->delete();`
2. `$user->createToken('api-token')->plainTextToken;` で新規発行
3. レスポンスは register/login と同じ構造

### 3.4 POST /api/v1/auth/logout

認証必須。現在のトークンのみを削除する（他デバイスのトークンは保持）。

**レスポンス** (`204 No Content`)

### 3.5 GET /api/v1/auth/me

認証必須。現在のユーザー情報を `UserResource` で返す。

**レスポンス** (`200 OK`)

```json
{ "data": { "id": 1, "email": "user@example.com", "name": "山田 太郎" } }
```

## 4. status カラムの扱い

`users.status` は **アカウント凍結状態** を表すフラグである（0:凍結、1:有効）。

- **ログイン時**: `status=0` のユーザーは認証成功させない（凍結アカウントエラー）
- **認証済みリクエスト時**: 凍結直後の既存トークンも弾けるよう、`auth:sanctum` の後段にカスタムミドルウェア `EnsureUserIsActive` を挟んで `status=1` 以外を拒否する
- **登録時**: 新規ユーザーは `status=1` で作成される（デフォルト値）

## 5. ファイル構成

新規作成するファイル:

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── Auth/
│   │               └── AuthController.php   ... 5 メソッド (register/login/refresh/logout/me)
│   ├── Middleware/
│   │   └── EnsureUserIsActive.php           ... status=1 のみ通す
│   ├── Requests/
│   │   └── Api/
│   │       └── V1/
│   │           └── Auth/
│   │               ├── RegisterRequest.php
│   │               └── LoginRequest.php
│   └── Resources/
│       └── Api/
│           └── V1/
│               └── UserResource.php
config/
└── sanctum.php                                ... install:api で生成、expiration を編集
database/
└── migrations/
    └── YYYY_MM_DD_HHMMSS_create_personal_access_tokens_table.php  ... install:api で生成
routes/
└── api.php                                    ... install:api で生成、v1/auth ルートを追記
tests/
└── Feature/
    └── Api/
        └── V1/
            └── Auth/
                └── AuthTest.php
```

変更するファイル:

- `app/Models/User.php`: `HasApiTokens` trait を追加
- `bootstrap/app.php`: `EnsureUserIsActive` を `active.user` エイリアスで登録
- `composer.json`: `laravel/sanctum` 依存を追加（`composer require` で自動）

## 6. テスト計画

`tests/Feature/Api/V1/Auth/AuthTest.php` に以下のケースを実装する:

### 6.1 register

- 正常系: 有効な入力でユーザー作成・トークン返却・DB に保存される
- 異常系: email 形式不正 → 422
- 異常系: email 重複 → 422
- 異常系: password が 8 文字未満 → 422
- 異常系: password_confirmation 不一致 → 422

### 6.2 login

- 正常系: 正しい認証情報でトークン返却
- 異常系: 存在しないメール → 422
- 異常系: パスワード不一致 → 422
- 異常系: status=0 のユーザー → 403

### 6.3 refresh

- 正常系: 新トークン発行 + 旧トークンが DB から削除される
- 異常系: 認証なし → 401
- 異常系: 期限切れトークン → 401（Carbon::travel で時刻操作）
- 異常系: status=0 のユーザー → 403（active.user ミドルウェア）

### 6.4 logout

- 正常系: トークン削除 + 204 No Content
- 異常系: 認証なし → 401

### 6.5 me

- 正常系: 認証ユーザーの情報を返す
- 異常系: 認証なし → 401
- 異常系: status=0 のユーザー → 403

## 7. スコープ外（今回は実装しない）

- パスワードリセット機能（`password_reset_tokens` テーブルは既存だが API は別タスク）
- メール確認機能
- 多要素認証（2FA）
- 期限切れトークンの自動 prune スケジューラ登録
- レート制限（後続タスクで Route レベルで追加することは可能）

## 8. 受け入れ基準

- [ ] `composer require laravel/sanctum` と `php artisan install:api` が完了している
- [ ] 5 エンドポイントすべてが期待どおりのステータスコードとレスポンスを返す
- [ ] `php artisan test --compact tests/Feature/Api/V1/Auth/AuthTest.php` で全テストが PASS する
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
- [ ] 凍結ユーザー (`status=0`) はログイン拒否＆既存トークンでもアクセス拒否される
