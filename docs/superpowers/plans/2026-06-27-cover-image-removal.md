# カバー画像の削除 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** イベント編集画面で設定済みカバー画像を削除でき、削除後はプレースホルダが表示されるようにする。

**Architecture:** 編集フォームに削除チェックボックス（`remove_cover_image`）を追加し、`EventController::update` に「削除」分岐を1つ足す。`cover_image_path` を null にすれば既存のプレースホルダ表示ロジックがそのまま効く。新規アップロードは削除より優先。

**Tech Stack:** Laravel 13 / PHP 8.4 / PHPUnit 12 / Tailwind CSS v4

関連: 設計書 `docs/superpowers/specs/2026-06-27-cover-image-removal-design.md`、ADR `docs/adr/v6/0004-event-cover-image.md`

## Global Constraints

- 削除は `public` ディスクのファイル削除（`Storage::disk('public')->delete(...)`）＋ `cover_image_path = null`。
- 優先順位: **新規アップロード ＞ 削除**（両方指定時はアップロードが勝つ）。
- 権限は既存の `$this->authorize('update', $event)`（主催者・共同主催者）を流用、追加しない。
- 表示（show/index）は変更しない（null でプレースホルダが出る既存ロジックを利用）。
- PHP 変更後は `vendor/bin/pint --dirty --format agent`。
- テストは PHPUnit、`Tests\TestCase` + `RefreshDatabase`、`Storage::fake('public')` を使用。既存 `tests/Feature/Events/EventCoverImageTest.php` に追記（`validEventData()` ヘルパは再定義しない）。

---

### Task 1: 削除処理（バリデーション・コントローラ・テスト）

**Files:**
- Modify: `app/Http/Requests/Event/UpdateEventRequest.php`（`rules()` に1行追加）
- Modify: `app/Http/Controllers/EventController.php`（`update()` の画像処理に分岐追加）
- Test: `tests/Feature/Events/EventCoverImageTest.php`（4テスト追記）

**Interfaces:**
- Consumes: `events.cover_image_path`（nullable string）、`UpdateEventRequest`。
- Produces: `update()` が `remove_cover_image` を解釈し、オン時に画像を削除して `cover_image_path` を null にする。

- [ ] **Step 1: 失敗テストを書く（削除・維持・優先順位・空安全）**

`tests/Feature/Events/EventCoverImageTest.php` に追記:

```php
    public function test_cover_image_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;
        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'remove_cover_image' => '1',
        ]));

        $response->assertRedirect(route('events.show', $event));
        $this->assertNull($event->fresh()->cover_image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_cover_image_is_kept_when_not_removing(): void
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

    public function test_new_upload_takes_precedence_over_remove(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('events/1', 'public'),
        ]);
        $oldPath = $event->cover_image_path;

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('new.jpg', 800, 600),
            'remove_cover_image' => '1',
        ]));

        $response->assertRedirect(route('events.show', $event));
        $event->refresh();
        $this->assertNotNull($event->cover_image_path);
        $this->assertNotSame($oldPath, $event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_removing_when_no_image_is_safe(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['cover_image_path' => null]);

        $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'remove_cover_image' => '1',
        ]));

        $this->assertNull($event->fresh()->cover_image_path);
    }
```

- [ ] **Step 2: テスト実行（失敗を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: 新規4テストが FAIL（削除分岐が無く、画像が維持される／優先順位ロジックが無い）

- [ ] **Step 3: バリデーションルールを追加**

`app/Http/Requests/Event/UpdateEventRequest.php` の `rules()` 配列に追加:

```php
'remove_cover_image' => ['nullable', 'boolean'],
```

- [ ] **Step 4: update に削除分岐を実装**

`app/Http/Controllers/EventController.php::update` の画像処理を以下に置き換える（`except` に `remove_cover_image` を追加し、`elseif` で削除分岐）:

```php
public function update(UpdateEventRequest $request, Event $event): RedirectResponse
{
    $this->authorize('update', $event);

    $data = collect($request->validated())->except(['cover_image', 'remove_cover_image'])->all();

    if ($request->hasFile('cover_image')) {
        if ($event->cover_image_path !== null) {
            Storage::disk('public')->delete($event->cover_image_path);
        }
        $data['cover_image_path'] = $request->file('cover_image')->store("events/{$event->id}", 'public');
    } elseif ($request->boolean('remove_cover_image') && $event->cover_image_path !== null) {
        Storage::disk('public')->delete($event->cover_image_path);
        $data['cover_image_path'] = null;
    }

    $event->update($data);

    return redirect()->route('events.show', $event)->with('success', 'イベントを更新しました。');
}
```

- [ ] **Step 5: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: PASS（既存＋新規すべて）

- [ ] **Step 6: Pint & コミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Event/UpdateEventRequest.php app/Http/Controllers/EventController.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: カバー画像を編集画面から削除できるようにする（アップロード優先）"
```

---

### Task 2: 編集フォームの削除チェックボックス

**Files:**
- Modify: `resources/views/events/edit.blade.php`（現在画像プレビューの下にチェックボックス追加）
- Test: `tests/Feature/Events/EventCoverImageTest.php`（1テスト追記）

**Interfaces:**
- Consumes: Task 1 の `remove_cover_image` 受け口。
- Produces: 画面に `name="remove_cover_image"` のチェックボックス（現在画像がある時のみ）。

- [ ] **Step 1: フォーム検証テストを書く**

```php
    public function test_edit_form_shows_remove_checkbox_when_image_exists(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $response = $this->actingAs($user)->get(route('events.edit', $event));
        $response->assertOk();
        $response->assertSee('name="remove_cover_image"', false);
    }

    public function test_edit_form_hides_remove_checkbox_when_no_image(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['cover_image_path' => null]);

        $response = $this->actingAs($user)->get(route('events.edit', $event));
        $response->assertOk();
        $response->assertDontSee('name="remove_cover_image"', false);
    }
```

- [ ] **Step 2: テスト実行（失敗を確認）**

Run: `php artisan test --compact --filter=remove_checkbox`
Expected: FAIL（チェックボックスがまだ無い）

- [ ] **Step 3: 編集フォームにチェックボックスを追加**

`resources/views/events/edit.blade.php` の現在画像プレビュー（`@if ($event->cover_image_path)` で `<img ...>` を出している箇所）の `<img ...>` 直後、`@endif` の前に追加:

```blade
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <input type="checkbox" name="remove_cover_image" value="1"
                                   class="rounded border-slate-300 dark:border-[#3E3E3A] text-indigo-600 focus:ring-indigo-500">
                            現在の画像を削除する
                        </label>
```

> 既存のプレビューブロックは概ね次の形（実ファイルに合わせて挿入位置を調整）:
> ```blade
> @if ($event->cover_image_path)
>     <img src="{{ Storage::disk('public')->url($event->cover_image_path) }}" alt="現在のカバー画像" class="mb-2 h-32 w-auto rounded-lg object-cover ..." />
>     {{-- ここにチェックボックスを追加 --}}
> @endif
> ```

- [ ] **Step 4: テスト実行（成功を確認）**

Run: `php artisan test --compact --filter=EventCoverImageTest`
Expected: PASS（全テスト）

- [ ] **Step 5: コミット**

```bash
git add resources/views/events/edit.blade.php tests/Feature/Events/EventCoverImageTest.php
git commit -m "feat: 編集画面にカバー画像の削除チェックボックスを追加"
```

---

## 完了後

- [ ] 全 Feature テスト: `php artisan test --compact`
- [ ] `vendor/bin/pint --dirty --format agent` が clean
- [ ] 手動確認: 画像ありイベントの編集で「現在の画像を削除する」にチェック→更新→詳細でプレースホルダ表示
- [ ] PR 作成（`feature/cover-image-removal` → main）
