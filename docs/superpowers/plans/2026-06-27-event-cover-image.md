# イベントカバー画像 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** イベントにカバー画像1枚を任意でアップロードでき、一覧・詳細ページに表示する（未設定時はプレースホルダ）。

**Architecture:** Laravel の Filesystem 抽象（`public` ディスク）で画像を保存し、`events.cover_image_path` にパスを保持する。バリデーション・保存ロジックはリクエストクラスとコントローラに集約。配信は `Storage::url()`。本番ストレージの選定は別途（技術ADR `docs/adr/v6/technical/0004-cover-image-storage.md` 参照）。

**Tech Stack:** Laravel 13 / PHP 8.4 / PHPUnit 12 / Tailwind CSS v4 / Playwright

関連: ADR `docs/adr/v6/product/0004-event-cover-image.md`（Accepted）、設計書 `docs/superpowers/specs/2026-06-21-event-cover-image-design.md`

## Global Constraints

- カバー画像は**1イベント1枚・任意**。未設定時は共通プレースホルダを表示。
- 対応形式: **JPEG / PNG / WebP** のみ。サイズ上限 **5MB（5120KB）**。寸法上限 **4000×4000px**。
- 拡張子だけでなく実コンテンツを検証（`image` ルール）。再エンコード・ウイルススキャンは行わない。
- アップロード権限: **主催者・共同主催者**（既存の `EventPolicy::update` = `Event::isOrganizer`）。
- 保存先ディスクは `public`。保存パスは `events/{event_id}/...`。ファイル名は `store()` のランダム生成に任せる。
- Event は **SoftDeletes**。削除（論理削除）時は**画像ファイルを消さない**（復元時に壊れるため）。物理ファイルの掃除は将来の hard-delete 対応で扱う。
- PHP 変更後は `vendor/bin/pint --dirty --format agent` を実行。
- テストは PHPUnit。Feature テストは `Tests\TestCase` + `RefreshDatabase`。

---

### Task 1: DBスキーマとモデル

**Files:**
- Create: `database/migrations/2026_06_27_000000_add_cover_image_path_to_events_table.php`
- Modify: `app/Models/Event.php:18-31`（`#[Fillable([...])]` 属性）
- Test: `tests/Feature/Events/EventCoverImageTest.php`

**Interfaces:**
- Produces: `events.cover_image_path`（nullable string）、`Event` の fillable に `cover_image_path` を追加。

- [ ] **Step 1: マイグレーション作成**

Run: `php artisan make:migration add_cover_image_path_to_events_table --no-interaction`

生成ファイル名は日付付きになる。中身を以下に置き換える（パスは生成された実ファイルに合わせる）:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });
    }
};
```

- [ ] **Step 2: マイグレーション実行**

Run: `php artisan migrate --no-interaction`
Expected: `events` テーブルに `cover_image_path` が追加される。

- [ ] **Step 3: Event モデルの Fillable に追加**

`app/Models/Event.php` の `#[Fillable([...])]` 属性の配列に `'cover_image_path'` を追加する（`'description'` の後など）:

```php
#[Fillable([
    'user_id',
    'title',
    'description',
    'cover_image_path',
    'category',
    'prefecture',
    'location',
    'online_url',
    'online_password',
    'event_date',
    'end_date',
    'capacity',
    'status',
])]
```

- [ ] **Step 4: スキーマ確認テストを書く**

`tests/Feature/Events/EventCoverImageTest.php` を新規作成:

```php
<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCoverImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_image_path_is_fillable_and_nullable(): void
    {
        $event = Event::factory()->create(['cover_image_path' => null]);
        $this->assertNull($event->fresh()->cover_image_path);

        $event->update(['cover_image_path' => 'events/1/cover.jpg']);
        $this->assertSame('events/1/cover.jpg', $event->fresh()->cover_image_path);
    }
}
```

- [ ] **Step 5: テスト実行**

Run: `php artisan test --compact --filter=test_cover_image_path_is_fillable_and_nullable`
Expected: PASS

- [ ] **Step 6: Pint & コミット**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/Event.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: events に cover_image_path を追加しモデルのfillableに登録"
```

---

### Task 2: バリデーション

**Files:**
- Modify: `app/Http/Requests/Event/StoreEventRequest.php`（`rules()` に追加）
- Modify: `app/Http/Requests/Event/UpdateEventRequest.php`（`rules()` に追加）
- Test: `tests/Feature/Events/EventCoverImageTest.php`

**Interfaces:**
- Produces: 両リクエストが `cover_image` を受け付ける（任意）。`image`/`mimes:jpeg,png,webp`/`max:5120`/`dimensions:max_width=4000,max_height=4000`。

- [ ] **Step 1: 不正形式を拒否する失敗テストを書く**

`EventCoverImageTest.php` に追記。`validEventData()` ヘルパも追加する:

```php
    /** @return array<string, mixed> */
    private function validEventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テストイベント',
            'description' => '説明',
            'category' => \App\Enums\EventCategory::Backend->value,
            'prefecture' => '東京都',
            'location' => 'テスト会場',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(7)->addHours(2)->format('Y-m-d\TH:i'),
            'capacity' => 10,
            'status' => \App\Enums\EventStatus::Published->value,
        ], $overrides);
    }

    public function test_non_image_file_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('cover_image');
        Storage::disk('public')->assertDirectoryEmpty('events');
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('big.jpg')->size(6000),
        ]));

        $response->assertSessionHasErrors('cover_image');
    }

    public function test_too_large_dimensions_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('huge.jpg', 5000, 5000),
        ]));

        $response->assertSessionHasErrors('cover_image');
    }
```

- [ ] **Step 2: テスト実行（失敗を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: 3つの新規テストが FAIL（まだルールがなく画像が通る／エラーが出ない）

- [ ] **Step 3: StoreEventRequest にルール追加**

`app/Http/Requests/Event/StoreEventRequest.php` の `rules()` 配列に追加:

```php
'cover_image' => [
    'nullable',
    'image',
    'mimes:jpeg,png,webp',
    'max:5120',
    'dimensions:max_width=4000,max_height=4000',
],
```

- [ ] **Step 4: UpdateEventRequest にも同じルール追加**

`app/Http/Requests/Event/UpdateEventRequest.php` の `rules()` 配列に上記と同じ `cover_image` ルールを追加する。

- [ ] **Step 5: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: PASS

- [ ] **Step 6: Pint & コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Event tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: イベント作成・更新に cover_image のバリデーションを追加"
```

---

### Task 3: 作成時の画像保存（store）

**Files:**
- Modify: `app/Http/Controllers/EventController.php:61-73`（`store`）
- Test: `tests/Feature/Events/EventCoverImageTest.php`

**Interfaces:**
- Consumes: `StoreEventRequest`（`cover_image` を含む）、`events.cover_image_path`。
- Produces: 作成時、画像があれば `public` ディスクの `events/{event_id}/...` に保存し `cover_image_path` に格納。

- [ ] **Step 1: 作成時保存の失敗テストを書く**

```php
    public function test_event_can_be_created_with_cover_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ]));

        $event = Event::latest('id')->first();
        $response->assertRedirect(route('events.show', $event));
        $this->assertNotNull($event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        $this->assertStringStartsWith("events/{$event->id}/", $event->cover_image_path);
    }

    public function test_event_can_be_created_without_cover_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), $this->validEventData());

        $this->assertNull(Event::latest('id')->first()->cover_image_path);
    }
```

- [ ] **Step 2: テスト実行（失敗を確認）**

Run: `php artisan test --compact --filter=test_event_can_be_created_with_cover_image`
Expected: FAIL（`cover_image_path` が null のまま）

- [ ] **Step 3: store に画像保存を実装**

`EventController::store` を以下に変更する（`validated()` から `cover_image` を除外し、保存後に画像を格納）:

```php
public function store(StoreEventRequest $request): RedirectResponse
{
    /** @var User $user */
    $user = auth()->user();

    $event = Event::create([
        ...collect($request->validated())->except('cover_image')->all(),
        'user_id' => $user->id,
        'status' => $request->integer('status', EventStatus::Draft->value),
    ]);

    if ($request->hasFile('cover_image')) {
        $event->update([
            'cover_image_path' => $request->file('cover_image')->store("events/{$event->id}", 'public'),
        ]);
    }

    return redirect()->route('events.show', $event)->with('success', 'イベントを作成しました。');
}
```

- [ ] **Step 4: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: PASS

- [ ] **Step 5: Pint & コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EventController.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: イベント作成時にカバー画像を保存"
```

---

### Task 4: 更新時の画像差し替え（update）

**Files:**
- Modify: `app/Http/Controllers/EventController.php:92-99`（`update`）
- Test: `tests/Feature/Events/EventCoverImageTest.php`

**Interfaces:**
- Consumes: `UpdateEventRequest`、`events.cover_image_path`。
- Produces: 新画像があれば旧ファイルを削除してから新パスを保存。新画像がなければ既存パスを維持。

- [ ] **Step 1: 差し替え時に旧画像が消える失敗テストを書く**

```php
    public function test_updating_cover_image_replaces_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store("events/1", 'public'),
        ]);
        $oldPath = $event->cover_image_path;
        Storage::disk('public')->assertExists($oldPath);

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('new.jpg', 800, 600),
        ]));

        $response->assertRedirect(route('events.show', $event));
        $event->refresh();
        $this->assertNotSame($oldPath, $event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_updating_without_new_image_keeps_existing(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('keep.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;

        $this->actingAs($user)->put(route('events.update', $event), $this->validEventData());

        $this->assertSame($path, $event->fresh()->cover_image_path);
        Storage::disk('public')->assertExists($path);
    }
```

- [ ] **Step 2: テスト実行（失敗を確認）**

Run: `php artisan test --compact --filter=test_updating_cover_image_replaces_and_deletes_old_file`
Expected: FAIL

- [ ] **Step 3: update に差し替えを実装**

`EventController::update` を以下に変更:

```php
public function update(UpdateEventRequest $request, Event $event): RedirectResponse
{
    $this->authorize('update', $event);

    $data = collect($request->validated())->except('cover_image')->all();

    if ($request->hasFile('cover_image')) {
        if ($event->cover_image_path !== null) {
            Storage::disk('public')->delete($event->cover_image_path);
        }
        $data['cover_image_path'] = $request->file('cover_image')->store("events/{$event->id}", 'public');
    }

    $event->update($data);

    return redirect()->route('events.show', $event)->with('success', 'イベントを更新しました。');
}
```

ファイル冒頭の `use` に `use Illuminate\Support\Facades\Storage;` を追加する。

- [ ] **Step 4: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: PASS

- [ ] **Step 5: 権限テストを追加（他人は更新不可）**

```php
    public function test_non_organizer_cannot_upload_cover_image(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $response = $this->actingAs($stranger)->put(route('events.update', $event), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('x.jpg', 800, 600),
        ]));

        $response->assertForbidden();
        $this->assertNull($event->fresh()->cover_image_path);
    }
```

- [ ] **Step 6: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: PASS

- [ ] **Step 7: Pint & コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EventController.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: イベント更新時にカバー画像を差し替え（旧画像削除・権限維持）"
```

---

### Task 5: フォーム（create / edit）の画像入力

**Files:**
- Modify: `resources/views/events/create.blade.php:11`（`<form>` に enctype・ファイル入力追加）
- Modify: `resources/views/events/edit.blade.php`（enctype・ファイル入力・現画像プレビュー）
- Test: `tests/Feature/Events/EventCoverImageTest.php`

**Interfaces:**
- Consumes: フォーム送信 → Task 2-4 のサーバー処理。
- Produces: 画面に `name="cover_image"` のファイル入力。`<form>` は `enctype="multipart/form-data"`。

- [ ] **Step 1: フォームにファイル入力がある検証テストを書く**

```php
    public function test_create_form_has_cover_image_input(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('events.create'));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="cover_image"', false);
    }

    public function test_edit_form_has_cover_image_input(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $response = $this->actingAs($user)->get(route('events.edit', $event));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="cover_image"', false);
    }
```

- [ ] **Step 2: テスト実行（失敗を確認）**

Run: `php artisan test --compact --filter=form_has_cover_image_input`
Expected: FAIL

- [ ] **Step 3: create フォームを修正**

`resources/views/events/create.blade.php` の `<form method="POST" action="{{ route('events.store') }}" class="space-y-5">` を以下に変更:

```blade
<form method="POST" action="{{ route('events.store') }}" enctype="multipart/form-data" class="space-y-5">
```

タイトル入力欄の直後（最初の `</div>` の後）に、カバー画像の入力ブロックを追加:

```blade
                <!-- カバー画像 -->
                <div>
                    <label for="cover_image" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        カバー画像 <span class="text-slate-400 font-normal text-xs">（任意・JPEG/PNG/WebP・5MBまで）</span>
                    </label>
                    <input
                        type="file"
                        id="cover_image"
                        name="cover_image"
                        accept="image/jpeg,image/png,image/webp"
                        class="w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 @error('cover_image') border-red-500 @enderror"
                    />
                    @error('cover_image')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>
```

- [ ] **Step 4: edit フォームを修正**

`resources/views/events/edit.blade.php` の `<form ...>` に `enctype="multipart/form-data"` を追加し、create と同じカバー画像ブロックを追加する。ただし edit では入力欄の上に現在の画像プレビューを表示する:

```blade
                <!-- カバー画像 -->
                <div>
                    <label for="cover_image" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        カバー画像 <span class="text-slate-400 font-normal text-xs">（任意・JPEG/PNG/WebP・5MBまで）</span>
                    </label>
                    @if ($event->cover_image_path)
                        <img src="{{ Storage::disk('public')->url($event->cover_image_path) }}"
                             alt="現在のカバー画像"
                             class="mb-2 h-32 w-auto rounded-lg object-cover border border-slate-200 dark:border-[#3E3E3A]" />
                    @endif
                    <input
                        type="file"
                        id="cover_image"
                        name="cover_image"
                        accept="image/jpeg,image/png,image/webp"
                        class="w-full text-sm text-slate-700 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 @error('cover_image') border-red-500 @enderror"
                    />
                    @error('cover_image')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>
```

ファイル先頭に `@use(Illuminate\Support\Facades\Storage)` がなければ、`Storage` はファサードのため Blade から直接呼べる（`\Storage` ではなく `Storage::` で動く。動かない場合は `{{ asset('storage/'.$event->cover_image_path) }}` を使う）。

- [ ] **Step 5: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=form_has_cover_image_input`
Expected: PASS

- [ ] **Step 6: コミット**

```bash
git add resources/views/events/create.blade.php resources/views/events/edit.blade.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: イベント作成・編集フォームにカバー画像入力を追加"
```

---

### Task 6: 表示（show / index）とプレースホルダ

**Files:**
- Create: `public/images/event-placeholder.svg`
- Modify: `resources/views/events/show.blade.php`（画像表示）
- Modify: `resources/views/events/index.blade.php`（カードに画像表示）
- Test: `tests/Feature/Events/EventCoverImageTest.php`

**Interfaces:**
- Consumes: `events.cover_image_path`。
- Produces: 詳細・一覧で画像 or プレースホルダを表示。

- [ ] **Step 1: 表示テストを書く**

```php
    public function test_show_page_displays_cover_image_when_present(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->published()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $response = $this->get(route('events.show', $event));
        $response->assertOk();
        $response->assertSee($event->cover_image_path, false);
    }

    public function test_show_page_displays_placeholder_when_absent(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->published()->create(['cover_image_path' => null]);

        $response = $this->get(route('events.show', $event));
        $response->assertOk();
        $response->assertSee('event-placeholder.svg', false);
    }
```

> 注: `published()` ファクトリ状態が存在するか確認する（`grep -n "function published" database/factories/EventFactory.php`）。なければ `['status' => \App\Enums\EventStatus::Published->value]` を直接渡す。

- [ ] **Step 2: テスト実行（失敗を確認）**

Run: `php artisan test --compact --filter=displays_cover_image`
Expected: FAIL

- [ ] **Step 3: プレースホルダ画像を作成**

`public/images/event-placeholder.svg` を新規作成:

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#6366f1"/>
      <stop offset="1" stop-color="#a855f7"/>
    </linearGradient>
  </defs>
  <rect width="800" height="450" fill="url(#g)"/>
  <text x="400" y="235" font-family="sans-serif" font-size="40" fill="#ffffff" fill-opacity="0.85" text-anchor="middle">AI Connpass</text>
</svg>
```

- [ ] **Step 4: show に画像表示を追加**

`resources/views/events/show.blade.php` のパンくず `</nav>` の直後に、カバー画像表示ブロックを追加:

```blade
    <!-- カバー画像 -->
    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-[#3E3E3A]">
        <img src="{{ $event->cover_image_path ? Storage::disk('public')->url($event->cover_image_path) : asset('images/event-placeholder.svg') }}"
             alt="{{ $event->title }} のカバー画像"
             class="w-full h-64 sm:h-80 object-cover" />
    </div>
```

- [ ] **Step 5: index のカードに画像を追加**

`resources/views/events/index.blade.php` のイベントカード内（各イベントの `route('events.show', ...)` を含むリンク要素の先頭）に、カバー画像を追加する。カード要素を確認:

Run: `grep -n "events.show\|@foreach\|@forelse" resources/views/events/index.blade.php`

各カードの上部に以下を挿入（カードのクラス構造に合わせてラップする）:

```blade
                    <img src="{{ $event->cover_image_path ? Storage::disk('public')->url($event->cover_image_path) : asset('images/event-placeholder.svg') }}"
                         alt="{{ $event->title }} のカバー画像"
                         class="w-full h-40 object-cover" />
```

- [ ] **Step 6: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: PASS（全テスト）

- [ ] **Step 7: storage:link を確認しビルド**

Run: `php artisan storage:link`（既にあれば「already exists」）
Run: `npm run build`
Expected: エラーなし

- [ ] **Step 8: コミット**

```bash
git add public/images/event-placeholder.svg resources/views/events/show.blade.php resources/views/events/index.blade.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: イベント一覧・詳細にカバー画像（未設定時プレースホルダ）を表示"
```

---

### Task 7: E2E テスト

**Files:**
- Create: `tests/e2e/event-cover-image.spec.ts`
- Test: 同ファイル

**Interfaces:**
- Consumes: 既存の `tests/e2e/helpers.ts`（`register`, `createEvent`, `uniqueEmail`）。

- [ ] **Step 1: E2E テストを書く**

`tests/e2e/event-cover-image.spec.ts` を新規作成。1×1の最小 PNG をテスト中に生成してアップロードする:

```ts
import { test, expect } from '@playwright/test';
import { register, uniqueEmail } from './helpers';

// 1x1 透明PNG（base64）
const PNG_1PX = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
    'base64',
);

test('作成時にカバー画像をアップロードすると詳細ページに表示される', async ({ page }) => {
    await register(page, 'テストユーザー', uniqueEmail(), 'password123');

    await page.goto('/events/create');
    await page.fill('input[name=title]', 'カバー画像テスト');
    await page.selectOption('select[name=category]', { index: 1 });
    await page.selectOption('select[name=prefecture]', { label: '東京都' });
    await page.fill('input[name=location]', 'テスト会場');
    await page.fill('input[name=event_date]', '2030-12-31T10:00');
    await page.fill('input[name=end_date]', '2030-12-31T18:00');
    await page.fill('input[name=capacity]', '10');

    await page.setInputFiles('input[name=cover_image]', {
        name: 'cover.png',
        mimeType: 'image/png',
        buffer: PNG_1PX,
    });

    await page.getByRole('button', { name: '作成する' }).click();
    await page.waitForURL(/\/events\/\d+$/);

    const img = page.locator('img[alt$="のカバー画像"]').first();
    await expect(img).toBeVisible();
    await expect(img).not.toHaveAttribute('src', /event-placeholder\.svg/);
});
```

- [ ] **Step 2: dev サーバー稼働を確認して実行**

Run: `curl -s -o /dev/null -w "%{http_code}" http://localhost`（200 を確認）
Run: `npx playwright test event-cover-image --reporter=list`
Expected: PASS

- [ ] **Step 3: コミット**

```bash
git add tests/e2e/event-cover-image.spec.ts
git commit -m "test: カバー画像アップロードのE2Eテストを追加"
```

---

## 完了後

- [ ] 全 Feature テストを実行: `php artisan test --compact`
- [ ] `vendor/bin/pint --dirty --format agent` が clean
- [ ] PR 作成（`feature/v7-event-cover-image-impl` → main）

## デプロイ時の注意（本番・検証）

- 本番/検証のストレージ選定は技術ADR `docs/adr/v6/technical/0004-cover-image-storage.md` のとおり別途決定（S3互換が推奨たたき台）。`FILESYSTEM_DISK` と認証情報を設定すればコード変更不要。
- コンテナ起動時に `php artisan storage:link` を実行する必要がある（`public` ディスク利用時）。Dockerfile/起動スクリプトに追加する。
