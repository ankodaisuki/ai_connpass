# UC3: Google カレンダー連携 設計書

**作成日:** 2026-05-22
**バージョン:** v2
**ステータス:** 設計確定

---

## 1. 概要

UC3 は、**参加者が申し込んだイベントを自分の Google カレンダーへ自動登録できる機能**です。

各ユーザーが一度だけ Google アカウントを連携（OAuth 同意）すると、以降はイベント申込時に自動でカレンダーへ予定が作成されます。連携はいつでも解除できます。外部 API 連携が失敗しても、申込自体は必ず成立します。

本設計のスコープは **Google カレンダー連携の本体**です。PDF 要件のうち「開催前の自動リマインド通知」は独立性が高いため、**後続の別サブ機能**として切り出します。

---

## 2. ビジネス要件（PDF より）と今回のスコープ

### UC3 要件
- ユーザーが許可した場合、申込時に Google カレンダーへ予定が登録される
- Google カレンダー連携を解除できる
- 外部 API 連携に失敗した場合でも、申込自体が破綻しない
- 開催前に、ユーザー本人向けの自動リマインド通知が届く

### 今回のスコープ
- ✅ 連携する／解除する（OAuth）
- ✅ 申込時に予定を自動作成
- ✅ キャンセル時に予定を削除
- ✅ 失敗しても申込は破綻しない（ベストエフォート）
- ⏭️ 自動リマインド通知 … **後続サブ機能として分離**（スケジューラ + Notification）

---

## 3. 設計判断（決定事項）

| 項目 | 決定 | 根拠 |
|---|---|---|
| 連携方式 | ユーザーごとの OAuth（自分のカレンダー） | connpass 的な体験。各自のカレンダーに登録 |
| ライブラリ | laravel/socialite + google/apiclient | OAuth 同意とトークン管理 + Calendar API の標準構成 |
| 同期方式 | 同期ベストエフォート（try/catch） | 本番 Railway にキューワーカーが常駐していないため |
| トークン保存 | 専用テーブル `google_calendar_tokens`（暗号化） | User と責務分離・暗号化保存 |
| 予定IDの保存 | `event_attendances.google_calendar_event_id` | キャンセル時の削除に使用 |
| スケジュール変更時 | 参加者カレンダーは**自動更新しない** | 参加者ごとのトークンが必要で複雑・人数分API・要件外 |
| 自動リマインド | 別サブ機能に分離 | カレンダー連携と独立。スケジューラ + 通知の領域 |
| 連携 UI | プロフィールページ | 既存の導線に自然 |

---

## 4. 技術設計

### 4.1 アーキテクチャ / データフロー

```
① 連携（1回だけ）
  プロフィール「Googleカレンダー連携」→ 連携する（/google/connect）
  → Google 同意画面（ログイン + カレンダー書込許可, access_type=offline, prompt=consent）
  → /google/callback でトークン取得・暗号化して google_calendar_tokens に保存

② 申込時（連携済みの場合のみ）
  申込（EventAttendanceService::apply）
    → DB に参加登録（トランザクション・必ず成功）
    → 連携済みなら GoogleCalendarService::createEvent を try/catch で同期実行
        成功: 返却された Google 予定ID を event_attendances に保存
        失敗: ログ + 画面に軽い注意（申込は成立のまま）

③ キャンセル時（EventAttendanceService::cancel）
  → 参加を Cancelled に更新
  → google_calendar_event_id があれば deleteEvent を try/catch で実行

④ 連携解除（/google/disconnect）
  → トークンを revoke + google_calendar_tokens から削除
  → 作成済みの予定はユーザーのカレンダーに残す（削除しない）
```

未連携ユーザーの申込は通常どおり成功し、カレンダー登録は行われない。

### 4.2 データモデル

**新規テーブル `google_calendar_tokens`（User と 1 対 1）**

| カラム | 型 | NULL | 説明 |
|---|---|---|---|
| `id` | bigint | NO | PK |
| `user_id` | bigint(unique, FK) | NO | 連携ユーザー |
| `access_token` | text（encrypted） | NO | アクセストークン |
| `refresh_token` | text（encrypted） | YES | 更新用トークン |
| `expires_at` | timestamp | YES | アクセストークン失効時刻 |
| `google_email` | string | YES | 連携中アカウント表示用 |
| `created_at` / `updated_at` | timestamp | YES | — |

- `access_token` / `refresh_token` は Laravel の `encrypted` キャストで暗号化保存
- `onDelete('cascade')`（ユーザー削除時に連動）

**既存テーブル変更 `event_attendances`**
- `google_calendar_event_id`（string, NULL 可）を追加。作成した Google 予定の ID を保存

### 4.3 コンポーネント（ファイル構成）

| ファイル | 責務 |
|---|---|
| `config/services.php` | `google`（client_id / client_secret / redirect）設定を追加 |
| migration: `create_google_calendar_tokens_table` | トークンテーブル作成 |
| migration: `add_google_calendar_event_id_to_event_attendances` | 予定ID カラム追加 |
| `app/Models/GoogleCalendarToken.php` | トークンモデル（暗号化キャスト・`user()` リレーション） |
| `app/Models/User.php`（追記） | `googleCalendarToken()` リレーション・`hasGoogleCalendarConnected()` |
| `app/Http/Controllers/GoogleCalendarConnectionController.php` | `connect` / `callback` / `disconnect` |
| `app/Services/GoogleCalendarService.php` | google/apiclient ラッパ：`createEvent` / `deleteEvent` / トークン更新 / `revoke` |
| `app/Services/EventAttendanceService.php`（追記） | `apply` / `cancel` から GoogleCalendarService をベストエフォート呼び出し |
| `routes/web.php`（追記） | `/google/connect` `/google/callback` `/google/disconnect`（auth） |
| `resources/views/profile/show.blade.php`（追記） | 連携セクション（連携する／解除する） |

各サービスは 1 責務に絞る。`EventAttendanceService` は「連携済みなら呼ぶ・失敗しても申込を守る」だけを担う。

### 4.4 OAuth / スコープ

- スコープ：`https://www.googleapis.com/auth/calendar.events`（カレンダー予定の作成・削除）
- `access_type=offline` + `prompt=consent` でリフレッシュトークンを取得
- Socialite の Google プロバイダで同意フローを実装：
  - `Socialite::driver('google')->scopes([...])->with(['access_type' => 'offline', 'prompt' => 'consent'])->redirect()`
  - コールバックで `->user()` から access_token / refresh_token / expires_in / email を取得し保存

### 4.5 失敗時ハンドリング

- 申込の DB 登録はトランザクションで確実に成功させ、カレンダー作成はその後に try/catch で実行
- トークン期限切れ：呼び出し前に `expires_at` を確認 → 失効なら `refresh_token` で更新し保存。更新も失敗（失効/取消）なら未連携扱いにしてスキップし、再連携を促す
- Google クライアントに短いタイムアウトを設定し、申込がハングしないようにする
- キャンセル時の削除も try/catch（失敗してもキャンセルは成立）
- レート制限(429)・一時障害は v1 ではログのみ

### 4.6 UI/UX

- **プロフィールページに「Googleカレンダー連携」セクション**
  - 未連携：説明文 + 「連携する」ボタン（→ `/google/connect`）
  - 連携済み：連携中アカウント（`google_email`）表示 + 「連携を解除する」ボタン（confirm 付き）
- 申込時（連携済み）：自動で予定作成。成功時は success に「カレンダーに登録しました」を併記、失敗時は軽い注意文
- 未連携ユーザーの申込ボタン付近に「連携すると申込時に自動でカレンダー登録されます」の小さな案内（任意）

### 4.7 認可

- `/google/connect` `/google/callback` `/google/disconnect` は `auth` ミドルウェア配下
- 連携・解除は本人のトークンのみ操作（`auth()->user()` 起点）

---

## 5. テスト方針

実 Google には接続しない。`GoogleCalendarService` と Socialite はモック/フェイクに差し替える（コンテナバインド）。

- 連携：`connect` が Google 認可 URL へリダイレクト／`callback` でトークン保存／`disconnect` で削除＋revoke 呼び出し
- 申込（連携済み）：`createEvent` が呼ばれ `google_calendar_event_id` が保存される
- 申込（未連携）：カレンダー呼び出しなし・申込は成功
- 申込（カレンダー例外）：**申込は成功**・予定IDは未保存（ベストエフォート）
- キャンセル：予定IDがあれば `deleteEvent` 呼び出し
- トークンが暗号化保存されている（DB 生値が平文でない）

---

## 6. 前提・運用

### Google Cloud Console 設定（ユーザー作業）
本機能を実際に動かすには、ユーザーが以下を行う必要がある：
1. Google Cloud プロジェクト作成
2. Google Calendar API を有効化
3. OAuth 同意画面を設定（スコープ `calendar.events`、テストユーザー登録）
4. OAuth 2.0 クライアント ID（ウェブ）を発行し、リダイレクト URI を登録
5. `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` を `.env`（本番は Railway 変数）に設定

コード自体はフェイク/モックで作成・テスト可能。実接続は上記設定後。

### 依存追加（承認済み）
- `laravel/socialite`
- `google/apiclient`

### 本番デプロイ
- マイグレーションは `docker/start.sh` の `php artisan migrate --force` で適用
- 本番（Railway）に上記 Google 環境変数を設定

---

## 7. 既知の制約

- **スケジュール変更時、参加者カレンダーは自動更新しない**：参加者のカレンダー予定は申込時のまま。変更同期は参加者ごとのトークン・人数分 API 呼び出しが必要で複雑なため v1 では対象外
- **自動リマインド通知は本スコープ外**：後続サブ機能（スケジューラ + Notification）として別途設計
- 完全な監査ログ・リトライキューは持たない（v1 はベストエフォート + ログ）

---

## 8. 今後の拡張可能性

- 開催前の自動リマインド通知（次サブ機能）
- スケジュール変更時の参加者カレンダー同期（キューワーカー導入後）
- カレンダー連携失敗の再試行キュー
- 招待者（attendees）としての追加やオンライン会議リンク連携
