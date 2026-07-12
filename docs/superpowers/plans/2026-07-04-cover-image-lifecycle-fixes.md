# カバー画像ライフサイクル修正 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** カバー画像のアップロード/更新/削除で起きる「旧画像喪失・孤児ファイル・部分成功・復元不能・同時更新の上書き」を解消する（問題一覧: `docs/superpowers/plans/2026-07-04-cover-image-lifecycle-issues.md`）。

**Architecture:** 画像の保存/差し替え/削除ロジックを `CoverImageService` に集約し、「新ファイルを先にアップ → DBはトランザクション → コミット後に旧削除 → DB失敗時は新ファイルを補償削除」の統一パターンで実装する。削除はソフトデリート維持のうえ、管理者による復元機能を追加し、物理削除時のファイル掃除フックと孤児回収バッチを用意する。同時更新は `updated_at` ベースの楽観ロックで防ぐ。

**Tech Stack:** Laravel 13 / PHP 8.4 / PHPUnit(Pest) 12 / Storage(`cover_disk`) / SoftDeletes

## Global Constraints

- カバー画像ディスクは常に `config('filesystems.cover_disk')` 経由で取得する（直書き禁止）。
- 保存パスは `events/{event_id}/...`。ファイル名は `store()` のランダム生成に任せる。
- 表側仕様（アップロード可・一覧/詳細で表示・任意・プレースホルダ）は変えない。
- Event は SoftDeletes。ソフトデリート時は画像ファイルを消さない（復元前提）。
- 復元機能は **管理者のみ**が **管理者削除したイベント**を対象に行う。復元後の status は **Private**（手動で再公開）。
- テストは Pest。Feature テストは `Tests\TestCase` + `RefreshDatabase`、`Storage::fake()` を使う。
- PHP 変更後は `vendor/bin/pint --dirty --format agent` を実行してからコミットする。
- 作業は `main` へ直接 push 不可（ブランチ保護）。feature ブランチ→PR で進める。

---

### Task 1: CoverImageService を作り、更新フロー（差し替え・削除）を安全化（問題1・3）

**Files:**
- Create: `app/Services/CoverImageService.php`
- Modify: `app/Http/Controllers/EventController.php:99-119`（update）
- Test: `tests/Feature/Events/CoverImageServiceTest.php`

**Interfaces:**
- Produces:
  - `CoverImageService::updateCover(Event $event, array $otherData, ?\Illuminate\Http\UploadedFile $newImage, bool $removeImage): void`
    - 新画像ありなら差し替え、`$removeImage` かつ新画像なしなら削除、どちらも無ければ `$otherData` のみ更新。
    - 失敗時は新ファイルを掃除して例外を再送出。旧ファイルはコミット成功後にのみ削除。

- [ ] **Step 1: 正常系（差し替え）の失敗テストを書く**

`tests/Feature/Events/CoverImageServiceTest.php`:
```php
<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\User;
use App\Services\CoverImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CoverImageService
    {
        return app(CoverImageService::class);
    }

    public function test_replacing_image_stores_new_deletes_old_and_updates_db(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('events/1', 'public'),
        ]);
        $oldPath = $event->cover_image_path;

        $this->service()->updateCover($event, ['title' => '更新後'], UploadedFile::fake()->image('new.jpg'), false);

        $event->refresh();
        $this->assertNotSame($oldPath, $event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertSame('更新後', $event->title);
    }
}
```

- [ ] **Step 2: 実行して失敗を確認**

Run: `php artisan test --compact --filter=test_replacing_image_stores_new_deletes_old_and_updates_db`
Expected: FAIL（`CoverImageService` 未定義）

- [ ] **Step 3: CoverImageService を実装**

`app/Services/CoverImageService.php`:
```php
<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CoverImageService
{
    private function disk(): string
    {
        return config('filesystems.cover_disk');
    }

    /**
     * イベント更新（フィールド＋カバー画像の差し替え/削除）を安全に行う。
     *
     * @param  array<string, mixed>  $otherData  cover_image_path を含まないその他の更新項目
     */
    public function updateCover(Event $event, array $otherData, ?UploadedFile $newImage, bool $removeImage): void
    {
        $disk = $this->disk();
        $oldPath = $event->cover_image_path;
        $newPath = null;
        $data = $otherData;

        // ① 新ファイルを先にアップロード（旧はまだ消さない）
        if ($newImage !== null) {
            $newPath = $newImage->store("events/{$event->id}", $disk);
            $data['cover_image_path'] = $newPath;
        } elseif ($removeImage && $oldPath !== null) {
            $data['cover_image_path'] = null;
        }

        // ② DB更新はトランザクションで
        try {
            DB::transaction(fn () => $event->update($data));
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::disk($disk)->delete($newPath); // 補償: 孤児化させない
            }
            throw $e;
        }

        // ③ コミット成功後にのみ旧ファイルを掃除
        $replaced = $newPath !== null && $oldPath !== null && $oldPath !== $newPath;
        $removed = $newImage === null && $removeImage && $oldPath !== null;
        if (($replaced || $removed)) {
            Storage::disk($disk)->delete($oldPath);
        }
    }
}
```

- [ ] **Step 4: 実行して通ることを確認**

Run: `php artisan test --compact --filter=test_replacing_image_stores_new_deletes_old_and_updates_db`
Expected: PASS

- [ ] **Step 5: 「削除操作」と「アップ失敗時に旧画像が残る」テストを追加**

```php
    public function test_removing_image_nulls_db_and_deletes_file(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);
        $old = $event->cover_image_path;

        $this->service()->updateCover($event, ['title' => 'x'], null, true);

        $event->refresh();
        $this->assertNull($event->cover_image_path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_old_image_survives_when_new_upload_fails(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('events/1', 'public'),
        ]);
        $old = $event->cover_image_path;

        // アップロード先ディスクを put で例外を投げるモックに差し替える
        $throwing = \Mockery::mock(Storage::disk('public'))->makePartial();
        $throwing->shouldReceive('putFileAs')->andThrow(new \RuntimeException('upload failed'));
        Storage::shouldReceive('disk')->with('public')->andReturn($throwing);

        try {
            $this->service()->updateCover($event, ['title' => 'x'], UploadedFile::fake()->image('new.jpg'), false);
            $this->fail('例外が送出されるはず');
        } catch (\Throwable $e) {
            // 期待通り
        }

        $event->refresh();
        $this->assertSame($old, $event->cover_image_path); // DBは旧のまま
    }
```
> 注: `putFileAs` は `UploadedFile::store()` が内部で呼ぶメソッド。モックで例外を注入し、旧画像・DBが無傷であることを検証する。

- [ ] **Step 6: 実行して通ることを確認**

Run: `php artisan test --compact --filter=CoverImageServiceTest`
Expected: PASS（3件）

- [ ] **Step 7: EventController@update をサービスに置換**

`app/Http/Controllers/EventController.php` の `update` を次に置換（`CoverImageService` を DI）:
```php
public function update(UpdateEventRequest $request, Event $event, CoverImageService $coverImages): RedirectResponse
{
    $this->authorize('update', $event);

    $otherData = collect($request->validated())->except(['cover_image', 'remove_cover_image'])->all();

    $coverImages->updateCover(
        $event,
        $otherData,
        $request->file('cover_image'),
        $request->boolean('remove_cover_image'),
    );

    return redirect()->route('events.show', $event)->with('success', 'イベントを更新しました。');
}
```
`use App\Services\CoverImageService;` を追加し、不要になった `use Illuminate\Support\Facades\Storage;` は他で使っていなければ削除する。

- [ ] **Step 8: 既存のカバー画像テストが壊れていないか確認**

Run: `php artisan test --compact tests/Feature/Events`
Expected: PASS

- [ ] **Step 9: Pint & コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CoverImageService.php app/Http/Controllers/EventController.php tests/Feature/Events/CoverImageServiceTest.php
git commit -m "fix: カバー画像の更新を安全化（旧画像喪失・孤児化を防止）"
```

---

### Task 2: 作成フローの安全化（問題2）

**Files:**
- Modify: `app/Services/CoverImageService.php`（`createWithCover` 追加）
- Modify: `app/Http/Controllers/EventController.php:62-80`（store）
- Test: `tests/Feature/Events/CoverImageServiceTest.php`

**Interfaces:**
- Produces:
  - `CoverImageService::createWithCover(array $data, ?UploadedFile $newImage): Event`
    - `Event::create($data)` → 画像ありならアップ → パス記録、までをトランザクションで包む。失敗時はアップ済みファイルを掃除して再送出。

- [ ] **Step 1: 失敗テストを書く（成功時は画像付きで作成される）**

```php
    public function test_create_with_cover_persists_event_and_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $event = $this->service()->createWithCover(
            ['user_id' => $user->id, 'title' => 'T', 'description' => 'd', 'category' => \App\Enums\EventCategory::Backend->value, 'prefecture' => '東京都', 'location' => '会場', 'event_date' => now()->addDays(3), 'end_date' => now()->addDays(3)->addHour(), 'capacity' => 10, 'status' => \App\Enums\EventStatus::Draft->value],
            UploadedFile::fake()->image('c.jpg'),
        );

        $this->assertNotNull($event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'cover_image_path' => $event->cover_image_path]);
    }
```

- [ ] **Step 2: 実行して失敗を確認**

Run: `php artisan test --compact --filter=test_create_with_cover_persists_event_and_image`
Expected: FAIL（`createWithCover` 未定義）

- [ ] **Step 3: createWithCover を実装**

`CoverImageService` に追加:
```php
/**
 * イベント作成とカバー画像アップロードを原子的に行う。
 *
 * @param  array<string, mixed>  $data
 */
public function createWithCover(array $data, ?UploadedFile $newImage): Event
{
    $disk = $this->disk();
    $uploadedPath = null;

    try {
        return DB::transaction(function () use ($data, $newImage, $disk, &$uploadedPath): Event {
            $event = Event::create($data);
            if ($newImage !== null) {
                $uploadedPath = $newImage->store("events/{$event->id}", $disk);
                $event->update(['cover_image_path' => $uploadedPath]);
            }

            return $event;
        });
    } catch (\Throwable $e) {
        if ($uploadedPath !== null) {
            Storage::disk($disk)->delete($uploadedPath);
        }
        throw $e;
    }
}
```
> create は path 生成に `event.id` が要るため insert 先行が不可避。対象行は新規行のみでロック影響が小さいため、アップロードをトランザクション内に置いて原子性（失敗＝行もファイルも残さない）を優先する。

- [ ] **Step 4: 実行して通ることを確認**

Run: `php artisan test --compact --filter=test_create_with_cover_persists_event_and_image`
Expected: PASS

- [ ] **Step 5: EventController@store をサービスに置換**

```php
public function store(StoreEventRequest $request, CoverImageService $coverImages): RedirectResponse
{
    /** @var User $user */
    $user = auth()->user();

    $event = $coverImages->createWithCover(
        [
            ...collect($request->validated())->except('cover_image')->all(),
            'user_id' => $user->id,
            'status' => $request->integer('status', EventStatus::Draft->value),
        ],
        $request->file('cover_image'),
    );

    return redirect()->route('events.show', $event)->with('success', 'イベントを作成しました。');
}
```

- [ ] **Step 6: 既存テスト確認 → Pint → コミット**

```bash
php artisan test --compact tests/Feature/Events
vendor/bin/pint --dirty --format agent
git add app/Services/CoverImageService.php app/Http/Controllers/EventController.php tests/Feature/Events/CoverImageServiceTest.php
git commit -m "fix: イベント作成時の画像アップロードを原子化（部分成功・孤児化を防止）"
```

---

### Task 3: 物理削除時のファイル掃除フック（問題4a）

**Files:**
- Modify: `app/Models/Event.php`（`booted()` に `forceDeleted` フック）
- Test: `tests/Feature/Events/EventCoverImageCleanupTest.php`

**Interfaces:**
- Produces: Event が `forceDelete()` されたとき `events/{id}/` 配下のファイルを削除する。ソフトデリートでは削除しない。

- [ ] **Step 1: 失敗テストを書く**

`tests/Feature/Events/EventCoverImageCleanupTest.php`:
```php
<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCoverImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_keeps_image_but_force_delete_removes_it(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;

        $event->delete(); // ソフトデリート
        Storage::disk('public')->assertExists($path);

        $event->forceDelete(); // 物理削除
        Storage::disk('public')->assertMissing($path);
    }
}
```

- [ ] **Step 2: 実行して失敗を確認**

Run: `php artisan test --compact --filter=test_soft_delete_keeps_image_but_force_delete_removes_it`
Expected: FAIL（forceDelete でファイルが残る）

- [ ] **Step 3: Event に forceDeleted フックを追加**

`app/Models/Event.php` にメソッドを追加:
```php
protected static function booted(): void
{
    static::forceDeleted(function (Event $event): void {
        if ($event->cover_image_path !== null) {
            Storage::disk(config('filesystems.cover_disk'))->deleteDirectory("events/{$event->id}");
        }
    });
}
```

- [ ] **Step 4: 実行して通ることを確認 → Pint → コミット**

Run: `php artisan test --compact --filter=EventCoverImageCleanupTest`
Expected: PASS
```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Event.php tests/Feature/Events/EventCoverImageCleanupTest.php
git commit -m "feat: イベント物理削除時にカバー画像ディレクトリを削除"
```

---

### Task 4: 管理者による復元機能（問題4b）

**Files:**
- Modify: `app/Services/AdminService.php`（`restoreEvent` 追加）
- Modify: `app/Http/Controllers/Admin/EventController.php`（`trashed` 一覧・`restore` アクション）
- Modify: `routes/web.php`（admin ルート追加）
- Create: `resources/views/admin/events/trashed.blade.php`
- Test: `tests/Feature/Admin/RestoreEventTest.php`

**Interfaces:**
- Consumes: `AdminAuditLog`（action `restore_event`）
- Produces:
  - `AdminService::restoreEvent(Event $event, User $admin, string $reason): void` — `restore()` し、監査ログを記録。status は Private のまま（削除時に Private 化済み）。
  - ルート名 `admin.events.trashed`（GET）、`admin.events.restore`（PATCH）

- [ ] **Step 1: 失敗テストを書く**

`tests/Feature/Admin/RestoreEventTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\UserStatus;
use App\Models\AdminAuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreEventTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'status' => UserStatus::Active]);
    }

    public function test_admin_can_restore_a_trashed_event_as_private(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Private]);
        $event->delete();

        $response = $this->actingAs($this->admin())
            ->patch(route('admin.events.restore', $event->id), ['reason' => '誤削除のため']);

        $response->assertRedirect();
        $restored = Event::find($event->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertSame(EventStatus::Private, $restored->status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'restore_event', 'target_type' => 'event', 'target_id' => $event->id,
        ]);
    }
}
```
> 注: 管理者判定の実カラム/仕組みは `EnsureUserIsAdmin` ミドルウェアと `User` を確認し、`is_admin` 等プロジェクトの実装に合わせること（Step 2 で確認）。

- [ ] **Step 2: 管理者判定の実装を確認**

Run: `php artisan tinker --execute 'echo json_encode(app(\App\Models\User::class)->getFillable());'` および `resources/views/admin`・`EnsureUserIsAdmin` を確認し、`admin()` ヘルパと `is_admin` 相当をプロジェクトの実装に合わせる。

- [ ] **Step 3: ルート追加**

`routes/web.php` の admin グループに追加:
```php
Route::get('events/trashed', [\App\Http\Controllers\Admin\EventController::class, 'trashed'])->name('events.trashed');
Route::patch('events/{id}/restore', [\App\Http\Controllers\Admin\EventController::class, 'restore'])->name('events.restore');
```
> 既存の admin グループの prefix/name（例 `admin.`）に合わせて配置する。`{event}` ではなくソフトデリート済みを解決するため `{id}` を使う。

- [ ] **Step 4: AdminService::restoreEvent を実装**

```php
public function restoreEvent(Event $event, User $admin, string $reason): void
{
    DB::transaction(function () use ($event, $admin, $reason): void {
        $event->restore();

        AdminAuditLog::create([
            'admin_user_id' => $admin->id,
            'action' => 'restore_event',
            'target_type' => 'event',
            'target_id' => $event->id,
            'reason' => $reason,
        ]);
    });
}
```

- [ ] **Step 5: コントローラに trashed / restore を実装**

`app/Http/Controllers/Admin/EventController.php`:
```php
public function trashed(): View
{
    $events = Event::onlyTrashed()
        ->whereIn('id', AdminAuditLog::where('action', 'delete_event')->where('target_type', 'event')->select('target_id'))
        ->with('user')
        ->orderByDesc('deleted_at')
        ->paginate(30);

    return view('admin.events.trashed', compact('events'));
}

public function restore(Request $request, int $id): RedirectResponse
{
    $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
    $event = Event::onlyTrashed()->findOrFail($id);

    /** @var User $admin */
    $admin = $request->user();
    $this->adminService->restoreEvent($event, $admin, $validated['reason']);

    return redirect()->route('admin.events.trashed')->with('success', "「{$event->title}」を復元しました。");
}
```
`use App\Models\AdminAuditLog;` `use App\Models\User;` `use Illuminate\View\View;` を追加。

- [ ] **Step 6: trashed 一覧ビューを作成**

`resources/views/admin/events/trashed.blade.php`（既存 `admin/events/index.blade.php` の構造・レイアウトに合わせる。各行に理由入力付き復元フォーム）:
```blade
<form method="POST" action="{{ route('admin.events.restore', $event->id) }}">
    @csrf
    @method('PATCH')
    <input type="text" name="reason" placeholder="復元理由" required>
    <button type="submit">復元</button>
</form>
```

- [ ] **Step 7: テスト実行 → Pint → コミット**

Run: `php artisan test --compact --filter=RestoreEventTest`
Expected: PASS
```bash
vendor/bin/pint --dirty --format agent
git add app/Services/AdminService.php app/Http/Controllers/Admin/EventController.php routes/web.php resources/views/admin/events/trashed.blade.php tests/Feature/Admin/RestoreEventTest.php
git commit -m "feat: 管理者による削除済みイベントの復元機能を追加"
```

---

### Task 5: 孤児ファイル掃除コマンド（問題6）

**Files:**
- Create: `app/Console/Commands/PruneOrphanCoverImages.php`
- Modify: `routes/console.php`（スケジュール登録）
- Test: `tests/Feature/Console/PruneOrphanCoverImagesTest.php`

**Interfaces:**
- Produces: `covers:prune-orphans {--dry-run} {--hours=24}` — `events/` 配下のファイルのうち、DB(`withTrashed` の `cover_image_path`)から参照されず、かつ最終更新から `--hours` 以上経過したものを削除。`--dry-run` は対象表示のみ。

- [ ] **Step 1: 失敗テストを書く**

`tests/Feature/Console/PruneOrphanCoverImagesTest.php`:
```php
<?php

namespace Tests\Feature\Console;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOrphanCoverImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_unreferenced_old_files_but_keeps_referenced_and_recent(): void
    {
        Storage::fake('public');
        // 参照あり（残す）
        $referenced = UploadedFile::fake()->image('ref.jpg')->store('events/1', 'public');
        Event::factory()->create(['cover_image_path' => $referenced]);
        // 参照なし・古い（消す）: 最終更新を過去にする
        $orphan = UploadedFile::fake()->image('orphan.jpg')->store('events/2', 'public');
        touch(Storage::disk('public')->path($orphan), now()->subDays(2)->timestamp);
        // 参照なし・新しい（消さない）
        $recent = UploadedFile::fake()->image('recent.jpg')->store('events/3', 'public');

        $this->artisan('covers:prune-orphans', ['--hours' => 24])->assertSuccessful();

        Storage::disk('public')->assertExists($referenced);
        Storage::disk('public')->assertMissing($orphan);
        Storage::disk('public')->assertExists($recent);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        Storage::fake('public');
        $orphan = UploadedFile::fake()->image('o.jpg')->store('events/9', 'public');
        touch(Storage::disk('public')->path($orphan), now()->subDays(2)->timestamp);

        $this->artisan('covers:prune-orphans', ['--dry-run' => true, '--hours' => 24])->assertSuccessful();

        Storage::disk('public')->assertExists($orphan);
    }
}
```

- [ ] **Step 2: 実行して失敗を確認**

Run: `php artisan test --compact --filter=PruneOrphanCoverImagesTest`
Expected: FAIL（コマンド未定義）

- [ ] **Step 3: コマンドを実装**

`app/Console/Commands/PruneOrphanCoverImages.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneOrphanCoverImages extends Command
{
    protected $signature = 'covers:prune-orphans {--dry-run : 削除せず対象のみ表示} {--hours=24 : この時間以上経過した未参照ファイルのみ対象}';

    protected $description = 'どのイベントからも参照されない古いカバー画像ファイルを削除する';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $hours = (int) $this->option('hours');
        $disk = Storage::disk(config('filesystems.cover_disk'));

        $referenced = Event::withTrashed()->whereNotNull('cover_image_path')->pluck('cover_image_path')->all();
        $referenced = array_flip($referenced);

        $threshold = now()->subHours($hours)->timestamp;
        $deleted = 0;

        foreach ($disk->allFiles('events') as $file) {
            if (isset($referenced[$file])) {
                continue;
            }
            if ($disk->lastModified($file) > $threshold) {
                continue; // 猶予時間内は対象外
            }

            if ($dryRun) {
                $this->line("[dry-run] 削除対象: {$file}");
            } else {
                $disk->delete($file);
                $this->line("削除: {$file}");
            }
            $deleted++;
        }

        $this->info("対象: {$deleted} 件".($dryRun ? '（dry-run）' : ''));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: 実行して通ることを確認**

Run: `php artisan test --compact --filter=PruneOrphanCoverImagesTest`
Expected: PASS（2件）

- [ ] **Step 5: スケジュール登録**

`routes/console.php` に追加:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('covers:prune-orphans')->weekly();
```

- [ ] **Step 6: Pint & コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/PruneOrphanCoverImages.php routes/console.php tests/Feature/Console/PruneOrphanCoverImagesTest.php
git commit -m "feat: 孤児カバー画像を回収する covers:prune-orphans コマンドを追加"
```

---

### Task 6: 楽観ロックで同時更新の上書きを防止（問題5）

**Files:**
- Modify: `app/Http/Requests/Event/UpdateEventRequest.php`（`expected_updated_at` ルール）
- Modify: `app/Http/Controllers/EventController.php`（update 冒頭で版チェック）
- Modify: `resources/views/events/edit.blade.php`（hidden フィールド）
- Test: `tests/Feature/Events/EventOptimisticLockTest.php`

**Interfaces:**
- Consumes: フォームの hidden `expected_updated_at`（`$event->updated_at->timestamp`）
- Produces: 版が食い違う更新を `ValidationException`（`cover_image`ではなく `expected_updated_at` キー）で弾き、`session` にエラーを返す。

- [ ] **Step 1: 失敗テストを書く**

`tests/Feature/Events/EventOptimisticLockTest.php`:
```php
<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_update_is_rejected(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['title' => '元']);
        $stale = $event->updated_at->timestamp;

        // 別経路で更新して updated_at を進める
        sleep(1);
        $event->update(['title' => '先に更新']);

        $response = $this->actingAs($user)->put(route('events.update', $event), [
            'title' => '後から更新', 'description' => 'd',
            'category' => \App\Enums\EventCategory::Backend->value, 'prefecture' => '東京都', 'location' => '会場',
            'event_date' => now()->addDays(3)->format('Y-m-d\TH:i'), 'end_date' => now()->addDays(3)->addHour()->format('Y-m-d\TH:i'),
            'capacity' => 10, 'status' => \App\Enums\EventStatus::Published->value,
            'expected_updated_at' => $stale,
        ]);

        $response->assertSessionHasErrors('expected_updated_at');
        $this->assertSame('先に更新', $event->fresh()->title); // 上書きされない
    }
}
```

- [ ] **Step 2: 実行して失敗を確認**

Run: `php artisan test --compact --filter=test_stale_update_is_rejected`
Expected: FAIL（版チェックが無く上書きされる）

- [ ] **Step 3: UpdateEventRequest にルール追加**

`rules()` に追加:
```php
'expected_updated_at' => ['nullable', 'integer'],
```

- [ ] **Step 4: update 冒頭に版チェックを追加**

`EventController@update` の authorize 直後に:
```php
$expected = $request->integer('expected_updated_at');
if ($expected !== 0 && $expected !== $event->updated_at->timestamp) {
    throw \Illuminate\Validation\ValidationException::withMessages([
        'expected_updated_at' => '他の人がこのイベントを更新しました。最新の内容を確認してから保存してください。',
    ]);
}
```

- [ ] **Step 5: 実行して通ることを確認**

Run: `php artisan test --compact --filter=test_stale_update_is_rejected`
Expected: PASS

- [ ] **Step 6: edit ビューに hidden フィールド追加**

`resources/views/events/edit.blade.php` のフォーム内に:
```blade
<input type="hidden" name="expected_updated_at" value="{{ $event->updated_at->timestamp }}">
```

- [ ] **Step 7: 既存テスト確認 → Pint → コミット**

Run: `php artisan test --compact tests/Feature/Events`
Expected: PASS
```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Event/UpdateEventRequest.php app/Http/Controllers/EventController.php resources/views/events/edit.blade.php tests/Feature/Events/EventOptimisticLockTest.php
git commit -m "feat: イベント更新に楽観ロックを追加（同時更新の上書きを防止）"
```

---

## 完了時

- `docs/superpowers/plans/2026-07-04-cover-image-lifecycle-issues.md` のチェックリストを `- [x]` に更新し、状態サマリー表も「対応済み」に変更する。
- 全 Feature テストを実行して緑を確認: `php artisan test --compact`
- feature ブランチを push し、PR を作成（CI 緑を確認してからマージ）。

## タスクと問題の対応

| 問題 | 対応タスク |
|---|---|
| 1（更新時に旧画像が消える） | Task 1 |
| 2（作成時の部分成功） | Task 2 |
| 3（更新/削除操作のDB失敗で壊れる） | Task 1（統一パターンに集約） |
| 4a（削除時の画像掃除） | Task 3 |
| 4b（復元機能） | Task 4 |
| 5（同時更新の上書き） | Task 6 |
| 6（孤児ファイル回収） | Task 5 |
