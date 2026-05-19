# プロジェクト進捗管理

## 🎯 画面実装状況（API対応マップ）

API側は実装済み。画面側（Web UI）の対応状況をまとめる。

### 🔐 認証

| API | 画面 | 状態 |
|---|---|---|
| `POST /api/v1/auth/register` | `GET/POST /register` | ✅ 実装済み |
| `POST /api/v1/auth/login` | `GET/POST /login` | ✅ 実装済み |
| `POST /api/v1/auth/logout` | ログアウトボタン（ヘッダー等） | ✅ 実装済み |
| `GET /api/v1/auth/me` | プロフィールページ | ✅ 実装済み |
| `POST /api/v1/auth/refresh` | — | ⚠️ Web側はセッション認証のため不要 |

### 📅 イベント

| API | 画面 | 状態 |
|---|---|---|
| `GET /api/v1/events` | `GET /`（一覧） | ✅ 実装済み |
| `GET /api/v1/events/{event}` | `GET /events/{event}`（詳細） | ✅ 実装済み |
| `POST /api/v1/events` | イベント作成ページ | ✅ 実装済み |
| `PUT /api/v1/events/{event}` | イベント編集ページ | ✅ 実装済み |
| `DELETE /api/v1/events/{event}` | イベント削除（主催者操作） | ✅ 実装済み |

### 👥 イベント参加

| API | 画面 | 状態 |
|---|---|---|
| `GET /api/v1/events/{event}/attendances` | 参加者一覧（詳細ページ内） | ✅ 実装済み |
| `POST /api/v1/events/{event}/attendances` | 参加申し込みボタン | ✅ 実装済み |
| `DELETE /api/v1/events/{event}/attendances` | 参加キャンセルボタン | ✅ 実装済み |

### 🙋 マイページ系

| API | 画面 | 状態 |
|---|---|---|
| `GET /api/v1/me/attendances` | 自分の申し込み一覧ページ | ✅ 実装済み |

### 🔍 検索・フィルタ

| API | 画面 | 状態 |
|---|---|---|
| `GET /api/v1/events` のクエリ（q/category/prefecture/from/to） | 一覧ページの検索・フィルタUI | ✅ 実装済み |

---

## 🧪 Blade フィーチャーテスト新規作成（73件）

API 削除に伴い、同等のカバレッジを持つ Blade 向けテストを新規作成する。
仕様書: `docs/superpowers/specs/2026-05-17-blade-feature-tests-design.md`

- [x] `tests/Feature/AuthTest.php` — 認証（登録・ログイン・ログアウト）14件
- [x] `tests/Feature/EventTest.php` — イベント CRUD + 検索・フィルタ 42件
- [x] `tests/Feature/EventAttendanceTest.php` — 参加申し込み・キャンセル 12件
- [x] `tests/Feature/MyAttendanceTest.php` — 自分の申し込み一覧 5件
- [x] 全テスト実行して PASS 確認（`php artisan test --compact`）— 83件すべて PASS

---

## 📌 次に実装するタスク（優先度順の提案）

### 🔴 高優先度（基本動線）
- [x] ログインページ作成
- [x] ログアウト導線（ヘッダーにドロップダウン or ボタン）
- [x] プロフィールページ（`GET /me` 相当）

### 🟡 中優先度（コア機能）
- [x] イベント参加申し込み / キャンセル機能
- [x] イベント作成（主催者機能）
- [x] イベント編集（主催者機能）
- [x] イベント削除（主催者機能）
- [x] 自分の申し込み一覧ページ（マイページ）

### 🟢 低優先度（拡張機能）
- [x] イベント一覧の検索・フィルタUI
- [x] イベント詳細ページの参加者一覧表示

---

## 🧹 メンターレビュー対応：不要なAPI削除

Blade（Web UI）で全機能が完結しているため、重複している API 層をまるごと削除する。

### 削除対象（14エンドポイントすべて）

| グループ | エンドポイント |
|---|---|
| 認証 | `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`, `POST /api/v1/auth/refresh` |
| イベント | `GET /api/v1/events`, `GET /api/v1/events/{event}`, `POST /api/v1/events`, `PUT /api/v1/events/{event}`, `DELETE /api/v1/events/{event}` |
| 参加 | `GET /api/v1/events/{event}/attendances`, `POST /api/v1/events/{event}/attendances`, `DELETE /api/v1/events/{event}/attendances` |
| マイページ | `GET /api/v1/me/attendances` |

### 削除タスク

- [x] `routes/api.php` を削除
- [x] `app/Http/Controllers/Api/` ディレクトリごと削除（4ファイル）
- [x] `app/Http/Requests/Api/` ディレクトリごと削除 → `App\Http\Requests\Auth/`, `Event/` に移動
- [x] `app/Http/Resources/Api/` ディレクトリごと削除（4ファイル）
- [x] `app/Http/Middleware/EnsureUserIsActive.php` 削除
- [x] `bootstrap/app.php` の `active.user` ミドルウェアエイリアス削除
- [x] `tests/Feature/Api/` ディレクトリごと削除（4テストファイル）
- [x] Sanctum 関連削除：`composer remove laravel/sanctum`、`config/sanctum.php`、マイグレーション、`HasApiTokens` トレイト
- [x] 全テストを実行して既存機能の破壊がないことを確認（10件 PASS）

---

## ✅ 完了済み（API + 画面）

### メンターレビュー対応
- [x] 不要なAPI層を削除しBladeに一本化（コミット `c7314b3`）

### API実装
- [x] CLAUDE.md に開発ワークフローのルール追加
- [x] API設計の確認（DELETE は複数カラム更新で実装）
- [x] データベース層を構築：マイグレーション作成＆モデル定義
- [x] 認証機能を実装：ユーザー登録・ログイン・トークン更新
- [x] イベント管理API実装：CRUD操作＆テスト
- [x] イベント参加管理API実装：申し込み・キャンセル機能＆テスト
- [x] 検索・フィルタリング機能実装：イベント一覧検索

### 画面実装
- [x] ユーザー登録ページ（`/register`）
- [x] ログインページ（`/login`）：guest middleware で認証済みリダイレクト
- [x] イベント一覧ページ（`/`）：ヒーロー+カードグリッド+ページネーション
- [x] イベント詳細ページ（`/events/{event}`）：サマリーカード+参加進捗バー
- [x] イベント作成ページ（`/events/create`）：フォーム+ヘッダーボタン
- [x] ログアウト導線（ヘッダードロップダウン）
- [x] プロフィールページ（`/profile`）：ユーザー情報・統計・作成イベント一覧
- [x] イベント編集ページ（`/events/{event}/edit`）：Policy による認可・詳細ページに編集ボタン
- [x] イベント削除（詳細・編集ページの削除ボタン、confirm ダイアログ）
- [x] 自分の申し込み一覧ページ（`/my/attendances`）：カードリスト・終了済み表示・ページネーション
- [x] 参加申し込み・キャンセル機能（イベント詳細ページ、状態に応じたボタン切り替え）
- [x] イベント一覧の検索・フィルタUI（キーワード・カテゴリ・都道府県・日付範囲）
- [x] イベント詳細ページの参加者一覧（申し込み順・名前・申し込み日）
- [x] 共通レイアウトコンポーネント（`<x-app-layout>`）

---

## 🚀 Railway デプロイ（2026-05-18）

### 発生した問題と原因・対応まとめ

#### 1. `MissingAppKeyException`（APP_KEY 未設定）
- **原因**: Railway の Variables タブに `APP_KEY` を設定していたが、`RAILWAY_BETA_ENABLE_RUNTIME_V2`（新ランタイム Beta）が有効になっており、ユーザー定義の環境変数がコンテナに一切注入されなかった
- **対応**: Railway Variables に `RAILWAY_BETA_ENABLE_RUNTIME_V2=false` を追加して新ランタイムを無効化。`APP_KEY` が未注入の場合のフォールバック生成も `docker/start.sh` に追加

#### 2. `attempt to write a readonly database`（SQLite 権限エラー）
- **原因**: 環境変数が未注入のため `DB_CONNECTION=mysql` が届かず、Laravel が SQLite にフォールバック。SQLite ファイルが存在せず書き込みエラーが発生
- **対応**: `docker/start.sh` で SQLite ファイルを作成し `www-data` に権限付与。根本的には MySQL 接続が正しく渡されれば発生しない

#### 3. CSS/JS が `blocked: mixed-content`
- **原因**: Railway はリバースプロキシ経由で HTTPS を配信するが、Laravel がプロキシを信頼せず HTTP でアセット URL を生成。ブラウザが HTTPS ページ上の HTTP リソースをブロック
- **対応**:
  - `bootstrap/app.php` に `trustProxies` ミドルウェアを追加（`X-Forwarded-*` ヘッダーを信頼）
  - `APP_URL` を `https://` 付きに更新
  - `docker/start.sh` で `RAILWAY_PUBLIC_DOMAIN` から `APP_URL` を自動設定するフォールバックを追加

### 変更ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `docker/start.sh` | APP_KEY フォールバック生成・APP_URL 自動設定・SQLite 権限付与・`storage:link` 追加 |
| `docker/nginx.conf` | `error_log /dev/stderr` 追加（Railway ログに nginx エラーを出力） |
| `bootstrap/app.php` | `trustProxies` ミドルウェア追加（HTTPS 対応） |
| `compose.yaml` | `compose.local.yaml` にリネーム（Railway の Docker Compose 検出を防止） |

### 残課題

- **環境変数注入の根本問題**: Railway UI の Variables タブから設定した変数はコンテナに注入されなかった。Railway CLI（`railway variables set`）で設定することで解決

### Railway MySQL への接続方法

#### 前提：mysql クライアントのインストール（Mac）

```bash
brew install mysql-client
echo 'export PATH="/usr/local/opt/mysql-client/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

#### Railway CLI のインストールと接続

```bash
# Railway CLI インストール
npm install -g @railway/cli

# ログイン・プロジェクトリンク
railway login
railway link

# MySQL に接続（Railway の Connect タブの「Raw mysql command」を参考に）
# ※ -p とパスワードの間はスペースなし
mysql -h <HOST> -u root -p<PASSWORD> --port <PORT> --protocol=TCP railway
```

#### よく使う SQL

```sql
SHOW TABLES;
SELECT * FROM events LIMIT 10;
SELECT * FROM users LIMIT 10;
```

#### 環境変数を CLI で設定する方法

Railway UI の Variables タブではなく CLI で設定すると確実に反映される：

```bash
railway variables set APP_KEY=base64:...
railway variables set DB_CONNECTION=mysql
railway variables set DB_HOST=mysql.railway.internal
railway variables set DB_DATABASE=railway
railway variables set DB_USERNAME=root
railway variables set "DB_PASSWORD=..."
railway variables set APP_ENV=production
railway variables set APP_DEBUG=false
railway variables set "APP_URL=https://xxxx.up.railway.app"
railway variables set SESSION_DRIVER=database
railway variables set CACHE_STORE=database
railway variables set QUEUE_CONNECTION=database
railway variables set LOG_CHANNEL=stderr
railway variables set RAILWAY_BETA_ENABLE_RUNTIME_V2=false
```

---

## ⚙️ GitHub Actions CI 導入（2026-05-18）

`main` ブランチへの push・PR 時に自動でテストが実行される仕組みを追加。

### 設定ファイル

`.github/workflows/ci.yml`

### 実行内容

1. PHP 8.4 セットアップ
2. `composer install`
3. `.env` 生成・`APP_KEY` 発行
4. `php artisan test --compact`（83件、SQLite インメモリで実行）

### 動作確認方法

GitHub リポジトリの **Actions タブ**で結果を確認。
- ✅ 成功：テスト全件 PASS
- ❌ 失敗：テスト失敗（デプロイ前に気づける）

---

---

## 🛠️ Claude Code Skills 管理（2026-05-19）

プロジェクト専用のスキルを `.claude/skills/` ディレクトリに追加。

### 追加スキル一覧

#### Laravel エコシステム（5つ）
- `laravel-best-practices` — Laravel PHP コードのベストプラクティス（既存）
- `laravel-plugin-discovery` — LaraPlugins.io MCP でパッケージ検索・評価
- `laravel-security` — 認証、検証、CSRF、ファイルアップロード、レート制限
- `laravel-tdd` — PHPUnit/Pest による TDD ワークフロー（80%+ カバレッジ）
- `laravel-verification` — デプロイ前の検証ループ（lint、テスト、セキュリティ、本番対応）

#### API・バックエンド設計（2つ）
- `api-design` — REST API 設計パターン（リソース命名、HTTP セマンティクス、ページネーション）
- `backend-patterns` — バックエンド開発パターン（キャッシング、認証、レート制限、ログ）

#### デプロイ・インフラ（2つ）
- `deployment-patterns` — デプロイ戦略（Rolling、Blue-Green、Canary）・CI/CD パイプライン
- `docker-patterns` — Docker・Docker Compose ベストプラクティス（マルチステージビルド、セキュリティ）

#### テスト（1つ）
- `e2e-testing` — Playwright E2E テスト（Page Object Model、CI/CD 統合）

#### フロントエンド（1つ）
- `tailwindcss-development` — Tailwind CSS v4 開発（既存）

### 合計 11 個のスキル

これらのスキルは対応する作業を行う際に自動的に活動化される（例：Laravel コード作成時に `laravel-best-practices`、API 設計時に `api-design` が自動呼び出し）。

---

## 📝 開発ワークフロー

各タスク完了時：
1. ソースコード実装
2. ユーザーレビュー待ち
3. 承認後、git コミット
4. TodoWrite と TASKS.md を更新
