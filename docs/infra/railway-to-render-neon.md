# Railway → Render + Neon 移行コスト調査

作成日: 2026-06-14

## 現状インフラ（Railway）

| 項目 | 内容 |
|------|------|
| プロジェクト | `powerful-manifestation` |
| アプリ URL | https://aiconnpass-production.up.railway.app |
| リポジトリ | `ankodaisuki/ai_connpass`（GitHub 自動デプロイ連携） |
| DB | MySQL 8.4（Railway マネージド、`mysql-volume`） |
| リージョン | `iad`（バージニア） |

---

## 移行先

- **PHP サーバー:** Render
- **DB:** Neon（PostgreSQL）

---

## 最大の問題：MySQL → PostgreSQL

Neon は PostgreSQL のため、DB エンジン変更が発生する。

### マイグレーションへの影響

| 問題 | 箇所 | 対応 |
|------|------|------|
| `unsignedTinyInteger` | 7箇所（`users`, `events`, `event_attendances`, `event_organizers`） | Laravel が `smallInteger` に自動マップ。修正不要 |
| `mediumText` / `longText` | 複数（Laravel 標準マイグレーション含む） | Laravel が `text` に自動マップ。修正不要 |
| `DB::statement("UPDATE ... DATE_ADD(...)")` | `2026_05_21_220324_add_end_date_to_events.php:26` | **MySQL 関数のため PostgreSQL 用に書き直しが必要**（唯一の修正箇所） |

### アプリコードへの影響

生 SQL（`whereRaw` / `selectRaw` 等）はアプリコードに存在しない。**コード修正は不要。**

### 既存データの移行

本番 MySQL のデータを PostgreSQL へ変換する作業が必要。

```bash
# 例: pgloader を使った変換
pgloader mysql://user:pass@railway-host/ai_connpass \
         postgresql://user:pass@neon-host/ai_connpass
```

---

## Render（PHP サーバー）

コードへの影響はゼロ。設定ファイルの追加のみ。

| 作業 | 内容 |
|------|------|
| `render.yaml` 作成 | ビルド・起動コマンドの定義 |
| 環境変数設定 | `DB_CONNECTION=pgsql` 他を Render ダッシュボードで設定 |
| GitHub 連携 | Railway と同様に `main` ブランチ自動デプロイ設定 |

---

## 工数見積もり

| 作業 | 工数目安 |
|------|---------|
| Render 設定（`render.yaml` 作成・環境変数） | 1〜2時間 |
| マイグレーション修正（`add_end_date_to_events.php` 1ファイル） | 30分 |
| ローカルで PostgreSQL（Docker）動作確認 | 2〜3時間 |
| 既存データの MySQL → PostgreSQL 変換・移行 | 半日〜1日 |
| **合計** | **1〜2日** |

---

## 代替案：Fly.io

MySQL をそのまま使えるため DB エンジン変更が不要。

| 比較軸 | Render + Neon | Fly.io |
|--------|--------------|--------|
| DB エンジン変更 | 必要（MySQL → PostgreSQL） | 不要 |
| データ移行 | 必要 | 不要（dump → restore） |
| 移行コスト | 1〜2日 | 半日程度 |
| 無料枠 | あり（両サービスとも） | あり |
