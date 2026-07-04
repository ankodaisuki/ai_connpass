# カバー画像 Cloudflare R2 移行 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** カバー画像の保存先を Railway 永続 Volume から Cloudflare R2（S3 互換）へ移行し、Volume 撤去によりゼロダウンタイムリリースを可能にする。

**Architecture:** 保存先ディスク名を環境変数 `COVER_IMAGE_DISK` で切り替える（ローカル/テスト=`public`、本番=`s3`=R2）。`EventController` の保存・削除と Blade の URL 生成をディスク非依存にし、URL 生成は `Event::cover_image_url` アクセサに集約する。既存画像は冪等な artisan コマンドで R2 へコピーし、表示確認後に Volume を撤去する。DB スキーマは変更しない。

**Tech Stack:** Laravel v13 / PHP 8.4 / PHPUnit v12 / Flysystem S3 アダプタ / Cloudflare R2

## Global Constraints

- 設計書: `docs/superpowers/specs/2026-06-28-cover-image-r2-migration-design.md`
- 関連 ADR: 技術 `docs/adr/v6/technical/0004-cover-image-storage.md`（保存先ストレージの選定・移行/ロールバック記録）／ プロダクト `docs/adr/v6/product/0004-event-cover-image.md`（表側仕様は不変）
- 利用者から見える動作（アップロード可・一覧/詳細で表示）は変えない。
- DB スキーマ変更なし（`cover_image_path` の相対パスをそのまま使う）。
- 保存先ディスク名は常に `config('filesystems.cover_disk')` 経由で取得する（直書き `'public'` を残さない）。ただし移行コマンドはソース=`public`・ターゲット=`s3` を明示する。
- テストは PHPUnit。コミットは `vendor/bin/pint --dirty --format agent` を実行してから行う。
- 依存追加（`league/flysystem-aws-s3-v3`）は `CLAUDE.md` 方針によりユーザー承認を得てから実行する。
- 既存テスト（`tests/Feature/Events/EventCoverImageTest.php`）はテスト環境で `cover_disk` が `public` のままのため、そのまま green を維持すること。

---

### Task 1: 保存先ディスクを設定駆動にする（config + EventController）

**Files:**
- Modify: `config/filesystems.php`（`disks` 配列の後に `cover_disk` キーを追加）
- Modify: `app/Http/Controllers/EventController.php`（`store` / `update` の保存・削除）
- Test: `tests/Feature/Events/EventCoverImageTest.php`（テスト追加）

**Interfaces:**
- Produces: 設定値 `config('filesystems.cover_disk')`（string、デフォルト `'public'`）。Task 2・Task 3 以外の本番設定はこの値で配信ディスクを決める。

- [ ] **Step 1: 設定切替が効くことを確認する失敗テストを書く**

`tests/Feature/Events/EventCoverImageTest.php` のクラス内に追記:

```php
public function test_cover_image_is_stored_on_configured_disk(): void
{
    config(['filesystems.cover_disk' => 'r2test']);
    Storage::fake('r2test');
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('events.store'), $this->validEventData([
        'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
    ]));

    $event = Event::latest('id')->first();
    $this->assertNotNull($event->cover_image_path);
    Storage::disk('r2test')->assertExists($event->cover_image_path);
    Storage::disk('public')->assertDirectoryEmpty('events');
}
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test --compact --filter=test_cover_image_is_stored_on_configured_disk`
Expected: FAIL（画像が `public` に保存され、`r2test` には存在しないため）

- [ ] **Step 3: config に cover_disk を追加する**

`config/filesystems.php` の `'disks' => [ ... ],` ブロックの直後（同じ階層）に追加:

```php
    /*
    |--------------------------------------------------------------------------
    | Cover Image Disk
    |--------------------------------------------------------------------------
    |
    | カバー画像の保存・配信に使うディスク。ローカル/テストは public、
    | 本番は R2（s3 ディスク）を指す。COVER_IMAGE_DISK で切り替える。
    |
    */

    'cover_disk' => env('COVER_IMAGE_DISK', 'public'),
```

- [ ] **Step 4: EventController の保存・削除をディスク非依存にする**

`app/Http/Controllers/EventController.php` の `store`、`update` を修正。

`store` の画像保存:

```php
if ($request->hasFile('cover_image')) {
    $event->update([
        'cover_image_path' => $request->file('cover_image')->store("events/{$event->id}", config('filesystems.cover_disk')),
    ]);
}
```

`update` は先頭で `$disk` を取り出して各所で使う:

```php
$data = collect($request->validated())->except(['cover_image', 'remove_cover_image'])->all();
$disk = config('filesystems.cover_disk');

if ($request->hasFile('cover_image')) {
    if ($event->cover_image_path !== null) {
        Storage::disk($disk)->delete($event->cover_image_path);
    }
    $data['cover_image_path'] = $request->file('cover_image')->store("events/{$event->id}", $disk);
} elseif ($request->boolean('remove_cover_image') && $event->cover_image_path !== null) {
    Storage::disk($disk)->delete($event->cover_image_path);
    $data['cover_image_path'] = null;
}
```

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test --compact --filter=test_cover_image_is_stored_on_configured_disk`
Expected: PASS

- [ ] **Step 6: 既存のカバー画像テスト全体が壊れていないことを確認する**

Run: `php artisan test --compact tests/Feature/Events/EventCoverImageTest.php`
Expected: PASS（全ケース。テスト環境では `cover_disk` = `public` のため従来通り）

- [ ] **Step 7: Pint を実行してコミットする**

```bash
vendor/bin/pint --dirty --format agent
git add config/filesystems.php app/Http/Controllers/EventController.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: カバー画像の保存先ディスクを設定駆動にする"
```

---

### Task 2: URL 生成を Event::cover_image_url アクセサに集約する

**Files:**
- Modify: `app/Models/Event.php`（アクセサ追加、`use` 追加）
- Modify: `resources/views/events/index.blade.php:201`
- Modify: `resources/views/events/show.blade.php:64`
- Modify: `resources/views/events/edit.blade.php:213-214`
- Test: `tests/Feature/Events/EventCoverImageTest.php`（テスト追加）

**Interfaces:**
- Produces: `Event::cover_image_url`（アクセサ、string）。画像ありなら `config('filesystems.cover_disk')` の公開 URL、なしなら `asset('images/event-placeholder.svg')` を返す。Blade はこれを参照する。

- [ ] **Step 1: アクセサの失敗テストを書く**

`tests/Feature/Events/EventCoverImageTest.php` に追記:

```php
public function test_cover_image_url_returns_disk_url_when_present(): void
{
    Storage::fake('public');
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create([
        'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
    ]);

    $this->assertSame(
        Storage::disk('public')->url($event->cover_image_path),
        $event->cover_image_url,
    );
}

public function test_cover_image_url_returns_placeholder_when_absent(): void
{
    $event = Event::factory()->create(['cover_image_path' => null]);

    $this->assertStringContainsString('event-placeholder.svg', $event->cover_image_url);
}
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test --compact --filter=test_cover_image_url`
Expected: FAIL with "Undefined property" / アクセサ未定義

- [ ] **Step 3: Event モデルにアクセサを追加する**

`app/Models/Event.php` の `use` 群に追加（既存の並びに合わせる）:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
```

`casts()` メソッドの直後にアクセサを追加:

```php
/**
 * カバー画像の公開 URL。未設定時はプレースホルダを返す。
 *
 * @return Attribute<string, never>
 */
protected function coverImageUrl(): Attribute
{
    return Attribute::make(
        get: fn (): string => $this->cover_image_path
            ? Storage::disk(config('filesystems.cover_disk'))->url($this->cover_image_path)
            : asset('images/event-placeholder.svg'),
    );
}
```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `php artisan test --compact --filter=test_cover_image_url`
Expected: PASS

- [ ] **Step 5: Blade を `$event->cover_image_url` に置換する**

`resources/views/events/index.blade.php:201` の `<img src="...">` を:

```blade
<img src="{{ $event->cover_image_url }}"
```

`resources/views/events/show.blade.php:64` も同様に:

```blade
<img src="{{ $event->cover_image_url }}"
```

`resources/views/events/edit.blade.php:213-214`（`@if` 構造は維持し、URL のみ置換）:

```blade
@if ($event->cover_image_path)
    <img src="{{ $event->cover_image_url }}"
```

（各 `<img>` の他の属性 class/alt 等は既存のまま残すこと）

- [ ] **Step 6: 表示系の既存テストが通ることを確認する**

Run: `php artisan test --compact tests/Feature/Events/EventCoverImageTest.php`
Expected: PASS（`test_show_page_displays_cover_image_when_present` / `test_show_page_displays_placeholder_when_absent` を含む全ケース）

- [ ] **Step 7: Pint を実行してコミットする**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Event.php resources/views/events/index.blade.php resources/views/events/show.blade.php resources/views/events/edit.blade.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "refactor: カバー画像URL生成をEventアクセサに集約する"
```

---

### Task 3: 既存画像を R2 へ移すコマンド `covers:migrate-to-r2`

**Files:**
- Create: `app/Console/Commands/MigrateCoverImagesToR2.php`
- Test: `tests/Feature/Console/MigrateCoverImagesToR2Test.php`

**Interfaces:**
- Produces: artisan コマンド `covers:migrate-to-r2 {--dry-run}`。ソース=`public` ディスク、ターゲット=`s3` ディスク。冪等（ターゲットに存在すればスキップ）。

- [ ] **Step 1: コマンドの雛形を生成する**

Run: `php artisan make:command MigrateCoverImagesToR2 --no-interaction`
Expected: `app/Console/Commands/MigrateCoverImagesToR2.php` が作成される

- [ ] **Step 2: 失敗テストを書く**

`tests/Feature/Console/MigrateCoverImagesToR2Test.php` を作成:

```php
<?php

namespace Tests\Feature\Console;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateCoverImagesToR2Test extends TestCase
{
    use RefreshDatabase;

    public function test_copies_existing_images_from_public_to_s3(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $this->artisan('covers:migrate-to-r2')->assertSuccessful();

        Storage::disk('s3')->assertExists($event->cover_image_path);
    }

    public function test_is_idempotent_and_skips_existing(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $this->artisan('covers:migrate-to-r2')->assertSuccessful();
        $this->artisan('covers:migrate-to-r2')
            ->expectsOutputToContain('スキップ: 1')
            ->assertSuccessful();
    }

    public function test_dry_run_does_not_copy(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $this->artisan('covers:migrate-to-r2', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('s3')->assertMissing($event->cover_image_path);
    }

    public function test_ignores_events_without_cover_image(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        Event::factory()->create(['cover_image_path' => null]);

        $this->artisan('covers:migrate-to-r2')
            ->expectsOutputToContain('コピー: 0')
            ->assertSuccessful();
    }
}
```

- [ ] **Step 3: テストを実行して失敗を確認する**

Run: `php artisan test --compact tests/Feature/Console/MigrateCoverImagesToR2Test.php`
Expected: FAIL（コマンド未実装）

- [ ] **Step 4: コマンドを実装する**

`app/Console/Commands/MigrateCoverImagesToR2.php` を実装:

```php
<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateCoverImagesToR2 extends Command
{
    protected $signature = 'covers:migrate-to-r2 {--dry-run : コピーせず対象件数のみ表示}';

    protected $description = 'カバー画像を public ディスクから R2(s3) へ冪等にコピーする';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $source = Storage::disk('public');
        $target = Storage::disk('s3');

        $copied = 0;
        $skipped = 0;
        $missing = 0;

        Event::whereNotNull('cover_image_path')->each(function (Event $event) use ($source, $target, $dryRun, &$copied, &$skipped, &$missing): void {
            $path = $event->cover_image_path;

            if ($target->exists($path)) {
                $skipped++;

                return;
            }

            if (! $source->exists($path)) {
                $this->warn("元ファイルが見つかりません (event {$event->id}): {$path}");
                $missing++;

                return;
            }

            if ($dryRun) {
                $this->line("[dry-run] コピー対象: {$path}");
                $copied++;

                return;
            }

            $target->writeStream($path, $source->readStream($path));
            $this->line("コピー完了: {$path}");
            $copied++;
        });

        $this->info("コピー: {$copied} / スキップ: {$skipped} / 欠損: {$missing}".($dryRun ? '（dry-run）' : ''));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test --compact tests/Feature/Console/MigrateCoverImagesToR2Test.php`
Expected: PASS（4 ケース全て）

- [ ] **Step 6: Pint を実行してコミットする**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/MigrateCoverImagesToR2.php tests/Feature/Console/MigrateCoverImagesToR2Test.php
git commit -m "feat: カバー画像をR2へ移行するartisanコマンドを追加"
```

---

### Task 4: R2 依存の追加と .env.example 整備（要ユーザー承認）

**Files:**
- Modify: `composer.json` / `composer.lock`（`composer require` 経由）
- Modify: `.env.example`

**Interfaces:**
- Consumes: なし（インフラ設定）
- Produces: 本番で `s3` ディスクが R2 に接続できる状態。

- [ ] **Step 1: 依存追加の承認を得る**

ユーザーに次を確認してから進む（`CLAUDE.md` の依存変更ポリシー）:
「`league/flysystem-aws-s3-v3 ^3.0`（`aws/aws-sdk-php` を同梱）を追加します。R2(S3 互換) 接続に必須です。よろしいですか？」

- [ ] **Step 2: 依存をインストールする**

Run: `composer require league/flysystem-aws-s3-v3 "^3.0"`
Expected: インストール成功。`composer show league/flysystem-aws-s3-v3` でバージョンが表示される。

- [ ] **Step 3: .env.example に R2 用設定を追記する**

`.env.example` の `FILESYSTEM_DISK` 付近、または `AWS_*` 群の近くに追記:

```dotenv
# カバー画像の保存先ディスク（ローカルは public、本番は s3=R2）
COVER_IMAGE_DISK=public

# Cloudflare R2（s3 ディスク経由）。本番のみ設定する。
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=auto
# AWS_BUCKET=
# AWS_ENDPOINT=https://<アカウントID>.r2.cloudflarestorage.com
# AWS_URL=https://img.example.com
# AWS_USE_PATH_STYLE_ENDPOINT=false
```

- [ ] **Step 4: 設定が読めることを確認する**

Run: `php artisan config:show filesystems.cover_disk`
Expected: `public`（ローカル env の値）が表示される

- [ ] **Step 5: コミットする**

```bash
git add composer.json composer.lock .env.example
git commit -m "build: R2(S3互換)接続用のFlysystem S3アダプタを追加しenv例を整備"
```

---

### Task 5: 本番カットオーバー Runbook（手動・コードは start.sh 整理のみ）

> このタスクはコードの自動テスト対象ではなく、本番反映の手順書。各ステップでロールバック余地を残し、Volume は手順 4 まで保険として残す。実施者は Railway の権限を持つ運用者。

**Files:**
- Modify（手順 5 で実施）: `docker/start.sh`

- [ ] **Step 1: Cloudflare 側を準備する**
  - R2 バケットを作成。
  - カスタムドメイン（例 `img.example.com`）をバケットにバインドし公開配信を有効化。
  - R2 API トークン（アクセスキー/シークレット）を発行。

- [ ] **Step 2: Railway に R2 認証情報を設定（配信はまだ Volume）**
  - `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION=auto` / `AWS_BUCKET` / `AWS_ENDPOINT` / `AWS_URL` / `AWS_USE_PATH_STYLE_ENDPOINT=false` を設定。
  - `COVER_IMAGE_DISK` は **まだ `public` のまま**（または未設定）。
  - Task 1〜4 を含むビルドをデプロイ。

- [ ] **Step 3: 既存画像を R2 へコピー**
  - Run（Railway のサービスシェル等）: `php artisan covers:migrate-to-r2 --dry-run` で件数確認 → 問題なければ `php artisan covers:migrate-to-r2`。
  - Cloudflare ダッシュボードで R2 にファイルが入ったことをサンプル確認。

- [ ] **Step 4: 配信を R2 へ切替**
  - Railway で `COVER_IMAGE_DISK=s3` に変更 → 再デプロイ/再起動。
  - 一覧・詳細・編集の各画面でカバー画像が R2(カスタムドメイン) から表示されることを確認。
  - 切替中の取りこぼし対策として `php artisan covers:migrate-to-r2` を再実行（冪等）。

- [ ] **Step 5: Volume 撤去と start.sh 整理**
  - R2 配信が完全に正常と確認できたら、Railway の Volume を解除。
  - `docker/start.sh` から Volume 前提の権限付与ブロックを削除:

```diff
-# 永続Volume（storage/app/public）をマウントしている場合、
-# Volumeはroot所有でマウントされるため www-data が書き込めるよう権限を付与する。
-# （Dockerfileのchownはビルド時のため、ランタイムでマウントされるVolumeには効かない）
-mkdir -p storage/app/public
-chown -R www-data:www-data storage/app/public
-chmod -R 775 storage/app/public
-
```

  - `php artisan storage:link --force` は、`public` ディスクを他用途で使っていない場合は削除可。使用有無を確認してから判断する（不明なら残す）。
  - 変更を別ブランチでデプロイし、再デプロイ時にダウンタイムが発生しない（ゼロダウンタイム化）ことを確認。
  - コミット:

```bash
git add docker/start.sh
git commit -m "chore: Volume撤去に伴いstart.shのVolume権限処理を削除"
```

---

## 検証

全タスク完了後、回帰がないことを確認:

- Run: `php artisan test --compact tests/Feature/Events/EventCoverImageTest.php tests/Feature/Console/MigrateCoverImagesToR2Test.php`
  Expected: PASS
- 必要に応じて全体: `php artisan test --compact`
