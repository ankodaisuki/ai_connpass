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

- [ ] `routes/api.php` を削除（または空に）
- [ ] `app/Http/Controllers/Api/` ディレクトリごと削除（4ファイル）
- [ ] `app/Http/Requests/Api/` ディレクトリごと削除（5ファイル）
- [ ] `app/Http/Resources/Api/` ディレクトリごと削除（4ファイル）
- [ ] `app/Http/Middleware/EnsureUserIsActive.php` 削除（API でのみ使用）
- [ ] `bootstrap/app.php` の `active.user` ミドルウェアエイリアス削除
- [ ] `tests/Feature/Api/` ディレクトリごと削除（4テストファイル）
- [ ] Sanctum 関連削除：
  - [ ] `composer remove laravel/sanctum`
  - [ ] `config/sanctum.php` 削除
  - [ ] `database/migrations/2026_05_13_063250_create_personal_access_tokens_table.php` 削除
  - [ ] `app/Models/User.php` の `HasApiTokens` トレイト削除
- [ ] 全テストを実行して既存機能の破壊がないことを確認（`php artisan test --compact`）

---

## ✅ 完了済み（API + 画面）

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

## 📝 開発ワークフロー

各タスク完了時：
1. ソースコード実装
2. ユーザーレビュー待ち
3. 承認後、git コミット
4. TodoWrite と TASKS.md を更新
