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

## 📆 UC3: Google カレンダー連携（v2）✅

connpass のように、ユーザーが自分の Google カレンダーへイベント予定を登録できるようにする。
申込時に自動登録するフルAPI連携（OAuth）を目標とするが、前提となる「終了日時」を先に追加する。

### 1. イベント終了日時の追加（前提機能）【完了】

- [x] マイグレーション：`events.end_date`（NOT NULL）を追加し既存データを `event_date + 2時間` でバックフィル
- [x] `Event` モデル：`end_date` を Fillable + datetime キャスト
- [x] `EventFactory`：`end_date`（event_date + 2時間）を生成
- [x] バリデーション（Store/Update）：`end_date` 必須・`after:event_date`、項目名を日本語化
- [x] 作成・編集フォーム：終了日時入力を追加
- [x] 「終了」判定を終了時刻基準に変更
  - [x] 一覧：`end_date >= now`（開催中も表示、終了後に消える）
  - [x] 過去に参加した：`end_date < now`
  - [x] 申込締切：`end_date` まで（途中参加を許容）
  - [x] 出欠記録・キャンセル可否は従来どおり開始時刻（`event_date`）基準を維持
- [x] 表示を「開始 〜 終了」レンジに更新（一覧 / 詳細 / プロフィール作成イベント / 申込一覧 / 過去に参加した）
- [x] テスト追加・更新（バリデーション、開催中表示・途中参加、終了判定）

### 2. Google カレンダー連携本体（フルAPI・OAuth）【完了】

- [x] Google Cloud プロジェクト作成・Calendar API 有効化・OAuth クライアント発行
- [x] OAuth 同意フロー（連携する／解除する）— laravel/socialite
- [x] アクセストークン・リフレッシュトークンの暗号化保存・更新
- [x] 申込時に Google カレンダーへ予定を作成（同期、ベストエフォート：失敗しても申込は成功）
- [x] キャンセル時に予定を削除
- [ ] 開催前の自動リマインド通知（スケジューラ + Notification）— 未対応（スコープ外）

### 発生した問題と対応

#### 1. Railway Runtime V2 の環境変数インジェクション不具合

- **現象**: Railway の Variables に `GCAL_CLIENT_ID` 等を設定してもコンテナ内に注入されず、PHP から `getenv()` / `$_ENV` / `$_SERVER` で取得できない
- **原因**: `RAILWAY_BETA_ENABLE_RUNTIME_V2` が有効な環境では、新規追加した変数がコンテナに注入されないバグ（Railway 既知の beta 不具合）
- **対応**: Railway CLI で `railway variables set GCAL_CLIENT_ID=...` を実行。さらに Dockerfile に `ARG`/`ENV` を追加し、ビルド時に変数を焼き込む回避策を実施
- **変数名変更**: `GOOGLE_*` → `GCAL_*` へ変更（Railway が `GOOGLE_` プレフィックスをフィルタリングする可能性を排除するため）

#### 2. `config:cache` の削除（start.sh）

- **現象**: `config:cache` を実行すると、Railway がランタイムで変数を注入する前のキャッシュ（空値）が固定されてしまう
- **対応**: `start.sh` から `php artisan config:cache`・`route:cache`・`view:cache` を削除し、`optimize:clear` のみ実行するように変更
- **影響**: キャッシュなしのため、本番パフォーマンスがやや低下する可能性がある

#### 3. `GCAL_CLIENT_SECRET` の Docker イメージ焼き込み（セキュリティ懸念）

- **現象**: Dockerfile の `ARG`/`ENV` で `GCAL_CLIENT_SECRET` をビルド時に注入するため、イメージレイヤーに平文で残る
- **リスク**: イメージを誰かが取得した場合にシークレットが漏洩する可能性
- **現状**: Railway の private registry を使用しているため直ちに問題にはならないが、将来的に対応が必要

#### 4. Google OAuth テストモードの制限

- **現象**: OAuth 同意画面が「テストモード」のため、Google Cloud Console のテストユーザーに登録されていないアカウントでは認証がブロックされる
- **対応**: Google Cloud Console の「OAuth 同意画面 → テストユーザー」に使用するアカウントを手動追加

### 今後の注意点

1. **Railway Runtime V2 修正後の対応**: Railway が Runtime V2 のインジェクション不具合を修正した際は、Dockerfile の `ARG`/`ENV` を削除し、`start.sh` に `config:cache` を戻してパフォーマンスを回復すること
2. **シークレットの安全な管理**: Railway の不具合が解消されたら、`GCAL_CLIENT_SECRET` を Dockerfile に焼き込む方式をやめ、ランタイム変数注入に戻すこと
3. **Google OAuth 公開申請**: 本番リリース時はアプリを「テストモード」から「本番モード」へ昇格させ、Google の審査を受けること（`calendar.events` スコープは要審査）
4. **リマインド通知**: 「開催前の自動リマインド通知」は今回のスコープ外として未実装。今後の UC として対応を検討すること

### カレンダー連携の既知の課題（今後の対応候補）

#### A. イベント変更時に参加者のカレンダーが更新されない

- **内容**: 主催者がイベントのタイトル・日時・場所を変更しても、参加者の Google カレンダー予定は変更前のまま残る
- **対応案**: `GoogleCalendarService::updateEvent()` を追加し、`EventController::update()` で全 Applied 参加者のカレンダーを一括更新する

#### B. イベント削除・非公開・下書き変更時に参加者のカレンダーに残る

- **内容**: 主催者がイベントを削除、または Public → Private/Draft に変更しても、参加者の Google カレンダー予定は残り続ける
- **対応案**: `EventController::destroy()` および `update()` でステータスが非公開になる際に、全 Applied 参加者のカレンダー予定を削除する

#### C. 連携解除時に既存のカレンダー予定が残る

- **内容**: ユーザーが「連携を解除する」を実行すると、アクセストークンは失効するが、それ以前に作成済みの Google カレンダー予定は削除されない
- **対応案**: `disconnect()` 処理でトークンを失効させる前に、`event_attendances.google_calendar_event_id` を持つ全予定を Google Calendar から削除する

#### D. 再申込時に古いカレンダー予定が孤立する

- **内容**: キャンセル時のカレンダー削除が失敗（ネットワークエラー等）した状態で再申込すると、新しいカレンダー予定が作られ DB の `google_calendar_event_id` が上書きされる。古い予定の ID は失われ、Google カレンダー側に孤立した予定が残る
- **対応案**: 再申込の `syncCalendarOnApply()` で既存の `google_calendar_event_id` があれば先に削除してから新規作成する

#### E. トークン期限切れ（リフレッシュトークンなし）のサイレント失敗

- **内容**: Google がリフレッシュトークンを返さなかった場合（`refresh_token = null`）、アクセストークンの有効期限切れ後は `authorizedClient()` が `null` を返しカレンダー同期が無音で失敗する。ユーザーへの通知は一切ない
- **対応案**: 同期失敗時にプロフィール画面で警告を表示、または再連携を促す通知を実装する

#### F. 参加者多数イベントでの同期パフォーマンス（課題 A/B 対応時）

- **内容**: 課題 A・B を実装した場合、参加者数 × Google API 呼び出しが同期で発生し、主催者の更新・削除操作がタイムアウトするリスクがある。Google API のレート制限（1 ユーザーあたり 10 req/sec）に当たる可能性もある
- **対応案**: カレンダー同期処理をキュー（`Queue::push` / Laravel Job）で非同期化する

---

## 🔔 キャンセル待ち機能

設計仕様: `docs/superpowers/specs/2026-05-26-waitlist-design.md`
実装計画: `docs/superpowers/plans/2026-05-26-waitlist.md`

### 実装内容

- [x] Task 1: マイグレーション・Enum・Model・Factory 更新
  - [x] `waitlisted_at` カラム追加マイグレーション
  - [x] `AttendanceStatus::Waitlisted = 2` 追加
  - [x] `EventAttendance` に `waitlisted_at` fillable/cast 追加
  - [x] `Event` に `waitlistAttendances()` リレーション追加
  - [x] `EventAttendanceFactory` に `waitlisted()` ステート追加

- [x] Task 2: サービス層 - キャンセル待ち登録
  - [x] `apply()` の戻り値を `AttendanceStatus` に変更
  - [x] `waitlistApply()` メソッド追加
  - [x] コントローラーの flash メッセージ分岐

- [x] Task 3: サービス層 - 自動昇格
  - [x] `cancel()` を Applied / Waitlisted 両対応に変更
  - [x] `promoteFromWaitlist()` メソッド追加

- [x] Task 4: メール送信
  - [x] `WaitlistConfirmationMail` クラス + ビュー
  - [x] `WaitlistPromotedMail` クラス + ビュー

- [x] Task 5: Google カレンダー連携（昇格時）テスト追加

- [x] Task 6: EventController + UI 更新
  - [x] `show()` に `$myWaitlist`・`$myWaitlistPosition`・`$isWaitlistFull` 追加
  - [x] `show.blade.php` にキャンセル待ちボタン・バッジ追加
  - [x] 主催者セクションにキャンセル待ちタブ追加

### マージ後の追加実装

- [x] `/my/attendances` にキャンセル待ちタブ追加（`?tab=waitlist`）
  - [x] `EventAttendance` に `waitlistedToPublishedEvent` スコープ追加
  - [x] `MyAttendanceController` に `tab` クエリパラメータ対応
  - [x] 申込一覧 / キャンセル待ち一覧をタブ切り替えで表示
  - [x] ページネーション時も `?tab=waitlist` を維持（`withQueryString()`）

- [x] キャンセル・出欠制限の強化（設計仕様: `docs/superpowers/specs/2026-06-06-attendance-restrictions-design.md`）
  - [x] イベント終了後（`end_date`）は主催者も出欠を記録できない
  - [x] キャンセルは `end_date` 後のみ不可（Applied・Waitlisted 共通）
  - [x] 出席済み（`attended_at` あり）の場合も終了前後問わずキャンセル不可
  - [x] UI: 申込済みユーザーのキャンセルボタンを `end_date` / `attended_at` ベースで制御
  - [x] UI: キャンセル待ちユーザーのキャンセルボタンを終了前は表示、終了後は非表示

- [x] イベント削除通知メール（設計仕様: `docs/superpowers/specs/2026-06-06-event-cancelled-notification-design.md`）
  - [x] `EventCancelledMail` クラス + ビュー（件名「【イベント中止】{タイトル}」）
  - [x] `EventController::destroy()` で Applied・Waitlisted 全参加者に送信
  - [x] 送信失敗は `Log::warning` で記録し処理継続

---

## 🚀 feature/waitlist リリース前対応

### 背景

`feature/waitlist` を `main` へマージすると Railway が自動デプロイされ本番に反映される。
リリース前に下記の問題を修正し、ロールバック手順を整備する。

---

### ① Race condition の修正【要対応】

**問題**
- 残り枠1つの状態で複数人が同時申し込みすると、check-then-act パターンのため定員オーバーで全員 Applied になる可能性がある。
- 複数人が同時にキャンセルすると、昇格処理が競合し1人しか昇格されないまま複数枠が空く可能性がある。

**修正箇所**: `app/Services/EventAttendanceService.php`

- [x] `apply()` — 申し込み枠チェック前に `lockForUpdate()` でレコードをロック
- [x] `promoteFromWaitlist()` — キャンセル待ち取得時に `lockForUpdate()` でレコードをロック

---

### ③ ロールバック対策【要対応】

**問題**
`make_applied_at_nullable` の `down()` が Waitlisted レコード（`applied_at = NULL`）を持つ状態で実行されると、NOT NULL 制約違反でロールバックが失敗する。

**修正箇所**: `database/migrations/2026_05_27_231604_make_applied_at_nullable_in_event_attendances.php`

- [x] `down()` に NULL の `applied_at` を埋める処理を追加してからカラム変更を実行

---

### リリース手順

1. mainマージ前に `v2` タグを付与（切り戻しの基点）
   ```bash
   git tag v2 main
   git push origin v2
   ```
2. `feature/waitlist` の PR をマージ（Railway が自動デプロイ・マイグレーション実行）
3. 本番動作確認手順を実施（下記参照）

**メンテナンスウィンドウ**: 不要（マイグレーションはデータ量が少ないため実質ゼロダウンタイム）

---

### 本番動作確認手順

リリース後に以下を順番に確認する。すべてパスすれば切り戻し不要。

| # | 確認内容 | 期待する結果 |
|---|---|---|
| 1 | 満員のイベント詳細ページを開く | 「キャンセル待ちに登録する」ボタンが表示される |
| 2 | キャンセル待ちに登録する | 登録完了メッセージが表示され、確認メールが届く |
| 3 | 申込済みユーザーが参加キャンセルする | キャンセル待ち1位に昇格通知メールが届く |
| 4 | 主催者がイベント詳細を開く | 主催者タブに「キャンセル待ち」一覧が表示される |
| 5 | キャンセル待ちも満員のイベントに申し込む | 「キャンセル待ちも満員です」エラーが表示される |

---

### 切り戻し判断基準

| 状況 | 対応 |
|---|---|
| Waitlisted機能が正常に動作している | **対策前進**（個別不具合はhotfixで対応） |
| Waitlisted機能が全く動作しない（申し込みエラー・マイグレーション失敗等） | **旧資材に切り戻し** |

---

### 切り戻し手順（Waitlisted機能が全く動作しない場合のみ）

```bash
# Railway で v2 タグのコミットを指定して再デプロイ
# または git revert でマージコミットを打ち消してプッシュ
git revert -m 1 <merge-commit-hash>
git push origin main
```

**切り戻し後の注意**
- Waitlisted レコードが存在する場合は下記SQLで強制キャンセル扱いにする
  ```sql
  UPDATE event_attendances SET status = 1, cancelled_at = NOW() WHERE status = 2;
  ```
- 対象ユーザーへの個別通知が必要

---

## 📝 開発ワークフロー

各タスク完了時：
1. ソースコード実装
2. ユーザーレビュー待ち
3. 承認後、git コミット
4. TodoWrite と TASKS.md を更新
