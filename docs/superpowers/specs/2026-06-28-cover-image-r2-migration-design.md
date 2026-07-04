# カバー画像の Cloudflare R2 移行 設計書

## 概要

カバー画像の保存先を、Railway の永続 Volume（コンテナのローカルディスク）から **Cloudflare R2（S3 互換オブジェクトストレージ）** へ移行する。配信は **カスタムドメイン**（例 `img.example.com`）経由とし、エグレス（転送量）無料と CDN キャッシュの恩恵を受ける。

移行完了後に Railway の Volume を撤去することで、「Volume 付きサービスは再デプロイ時に短いダウンタイムが発生する」という Railway の制約（[healthchecks ドキュメント](https://docs.railway.com/deployments/healthchecks)「Services with attached volumes」）が外れ、**ゼロダウンタイムリリースが有効化**される。

関連:
- 技術 ADR `docs/adr/v6/technical/0004-cover-image-storage.md`（保存先ストレージの選定・移行トリガー・R2 移行記録。本移行はここに記録済み）
- プロダクト ADR `docs/adr/v6/product/0004-event-cover-image.md`（表側仕様）
- **利用者から見える動作（画像をアップロードでき、一覧・詳細で表示される）は変わらない**ため、プロダクト ADR の改訂は不要（保存先切替は表側仕様に波及しない）。

## 目的（ゴール）

1. 新規アップロード・配信・削除をすべて R2 経由にする。
2. 既存画像を R2 へ移行する（データ消失なし）。
3. Volume を撤去し、ゼロダウンタイムリリースを可能にする。
4. DB スキーマは変更しない（`cover_image_path` の相対パスをそのまま使う）。

## 現状

- カバー画像は `public` ディスク（`storage/app/public/events/{event_id}/...`）に保存。Railway では同パスに永続 Volume（マウントパス `/var/www/html/storage/app/public`）をマウントして永続化している。
- ストレージアクセスはすべて Laravel の `Storage` ファサード経由。直書きの `disk('public')` は **4 ファイル / 6 箇所**:
  - `app/Http/Controllers/EventController.php`（保存 2・削除 2）
  - `resources/views/events/edit.blade.php`（URL 生成 1）
  - `resources/views/events/index.blade.php`（URL 生成 1）
  - `resources/views/events/show.blade.php`（URL 生成 1）
- `config/filesystems.php` に `s3` ディスクが定義済み（env 駆動）。
- S3 用 Flysystem アダプタ（`league/flysystem-aws-s3-v3` / `aws/aws-sdk-php`）は **未インストール**。
- 現状データは少量（V6 の新機能のため件数が少ない）。

## 決定事項（ブレストでの合意）

| 論点 | 決定 | 理由 |
|---|---|---|
| 配信方法 | カスタムドメイン経由の公開配信 | エグレス無料 + CDN キャッシュ + URL 安定。`r2.dev` は本番非推奨 |
| 開発/テスト環境 | ローカル/テストは `public` のまま、本番のみ R2 | ローカルに R2 認証情報が不要。テストは `Storage::fake()` で完結 |
| 既存画像の移行 | 冪等な使い捨て artisan コマンドで一括コピー | 再現性・冪等性。新旧混在を残さない |
| スコープ | R2 移行 + Volume 撤去 + ゼロダウンタイム化まで一気通貫 | 当初目的の完遂。ただし Volume 解除は表示確認後の最終ステップ |

## 変更内容

### 1. 依存追加

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

`aws/aws-sdk-php` も依存として同梱される。**依存変更のため実装時にユーザー承認を取る**（`CLAUDE.md` 方針）。

### 2. ストレージ構成（ディスク設計）

保存先ディスク名を環境変数で切り替える。`config/filesystems.php` に設定値を追加:

```php
// config/filesystems.php
'cover_disk' => env('COVER_IMAGE_DISK', 'public'),
```

- ローカル/テスト: `COVER_IMAGE_DISK` 未設定 → `public`（従来通り）
- 本番: `COVER_IMAGE_DISK=s3`（既存の `s3` ディスクを R2 に向ける）

既存の `s3` ディスク定義（`config/filesystems.php`）はそのまま利用する。R2 は S3 互換のため、env で R2 のエンドポイント・バケット・公開 URL を指定する。

本番の Railway Variables:

```
COVER_IMAGE_DISK=s3
AWS_ACCESS_KEY_ID=<R2 API トークンのアクセスキー>
AWS_SECRET_ACCESS_KEY=<R2 API トークンのシークレット>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<バケット名>
AWS_ENDPOINT=https://<アカウントID>.r2.cloudflarestorage.com
AWS_URL=https://img.example.com   # カスタムドメイン（公開 URL の基点）
AWS_USE_PATH_STYLE_ENDPOINT=false
```

R2 の公開アクセスは Cloudflare 側でバケットにカスタムドメインをバインドして設定する（ACL ベースではない）。`Storage::url()` は `AWS_URL` を基点に公開 URL を生成する。

### 3. コード変更点

#### 3-1. 保存先ディスクを設定駆動に（`app/Http/Controllers/EventController.php`）

`store` / `update` の `disk('public')` 直書きを設定駆動に置換する。

```php
$disk = config('filesystems.cover_disk');

// 保存
$request->file('cover_image')->store("events/{$event->id}", $disk);

// 削除
Storage::disk($disk)->delete($event->cover_image_path);
```

保存・削除の計 4 箇所すべてを `config('filesystems.cover_disk')` 経由にする。

#### 3-2. URL 生成を Event モデルのアクセサに集約（`app/Models/Event.php`）

Blade 3 箇所に重複している `Storage::disk('public')->url(...)` ＋プレースホルダ分岐を、モデルのアクセサに集約する。保存先非依存になり、Blade からストレージ実装が消える。

```php
// app/Models/Event.php
protected function coverImageUrl(): Attribute
{
    return Attribute::make(
        get: fn (): string => $this->cover_image_path
            ? Storage::disk(config('filesystems.cover_disk'))->url($this->cover_image_path)
            : asset('images/event-placeholder.svg'),
    );
}
```

Blade 側は `{{ $event->cover_image_url }}` に統一する（show / index）。edit はプレビュー用に画像有無を判定しているため、`@if ($event->cover_image_path)` 構造は維持しつつ URL 取得を `$event->cover_image_url` に置換する。

### 4. 既存画像の移行コマンド

冪等な使い捨て artisan コマンドを追加する。

```
php artisan covers:migrate-to-r2 [--dry-run]
```

仕様:
- `cover_image_path` が非 null の全イベントを走査。
- **ソース = `public` ディスク（Volume）**、**ターゲット = `s3` ディスク（R2）** へ同一パスでコピー。
- ターゲットに既に同一パスが存在すればスキップ（冪等）。
- `--dry-run` で対象件数のみ表示。
- 各件の成功/スキップ/失敗をログ出力し、最後にサマリを表示。

ソース/ターゲットのディスク名はコマンド内で明示（`public` → `s3`）。移行専用処理のため、配信用の `cover_disk` 設定には依存させない。

### 5. カットオーバー手順（安全段取り）

各段階でロールバック余地を残す。**Volume は手順 4 まで保険として残す。**

1. 本番に R2 認証情報を設定（ただし `COVER_IMAGE_DISK=public` のまま ＝ 配信・新規保存は Volume 継続）。コード ＋ 移行コマンドをデプロイ。
2. `covers:migrate-to-r2` を実行 → 既存画像が Volume と R2 の**両方**に存在する状態にする。R2 上のファイルをサンプル確認。
3. `COVER_IMAGE_DISK=s3` に切替（再デプロイ/再起動）→ 配信・新規アップロードが R2 経由に。既存画像は手順 2 で R2 にあるため**表示は途切れない**。
4. 切替後にもう一度 `covers:migrate-to-r2` を実行し、手順 2〜3 の間に Volume へ入った取りこぼしを冪等に吸収する。全画面（一覧・詳細・編集）で R2 配信を確認。
5. 問題なければ **Railway の Volume を解除**。`docker/start.sh` の Volume 権限付与処理を整理する。`storage:link` は R2 配信では不要になるが、`public` ディスクを使う他用途が無いか確認のうえ削除可否を判断する。
6. Volume 撤去によりゼロダウンタイムリリースが有効化されたことを、再デプロイ時のダウンタイム有無で確認する。

### 6. start.sh / Dockerfile の調整

- `docker/start.sh`: Volume 前提の権限付与ブロック（`chown -R www-data:www-data storage/app/public` 等）を、Volume 撤去後に整理する。
- `php artisan migrate --force` の起動時実行は本移行の対象外（ゼロダウンタイムを厳密化する場合は後方互換マイグレーション/Pre-Deploy 分離を別途検討するが、今回のスコープ外）。

## テスト方針

- 既存テストの `Storage::fake('public')` は、テスト環境のディスク（`public`）に追従させる（テスト環境では `cover_disk` = `public` のままなので大きな変更は不要）。
- 追加テスト:
  - `Event::cover_image_url` アクセサ: 画像ありで対象ディスクの URL を返す / 画像なしでプレースホルダ URL を返す。
  - `covers:migrate-to-r2` コマンド: `Storage::fake()` でソース→ターゲットのコピーを検証。既存ファイルがある場合のスキップ（冪等性）を検証。`--dry-run` でコピーが発生しないことを検証。
  - コントローラ: `config(['filesystems.cover_disk' => 'testing'])` 等でディスク切替が効くこと（保存・削除が対象ディスクに対して行われること）。

## 影響・結果

- カバー画像の保存・配信が R2 経由になる（利用者から見た動作は不変）。
- Volume 撤去によりゼロダウンタイムリリースが可能になる。
- DB スキーマ変更なし。Blade からストレージ実装依存が消え、将来の保存先変更に強くなる。
- 運用コストは R2 無料枠内に収まる見込み（カバー画像・小規模・エグレス無料）。

## スコープ外（YAGNI）

- 画像のサムネイル生成・複数サイズ配信。
- 署名付き URL（非公開バケット）。カバー画像は公開前提のため不要。
- 起動時マイグレーションの Pre-Deploy 分離・後方互換化（ゼロダウンタイムを厳密化する場合に別途検討）。
- 水平スケール（複数レプリカ）対応。R2 化により可能になるが、本移行では構成変更しない。
