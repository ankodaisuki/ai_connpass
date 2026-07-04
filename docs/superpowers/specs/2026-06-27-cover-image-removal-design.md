# カバー画像の削除 設計書

## 概要

イベント編集画面で、設定済みのカバー画像を**削除**できるようにする。削除すると `cover_image_path` が null になり、既存のプレースホルダ表示ロジックにより一覧・詳細ではデフォルト画像（プレースホルダ）が表示される。

関連: ADR `docs/adr/v6/product/0004-event-cover-image.md`（「任意・未設定時プレースホルダ」の自然な補完。表側仕様の判断は変わらないため新規 ADR は不要）

## 現状

- `EventController::update` は**新規アップロード（差し替え）のみ**対応。画像をクリアする手段がない。
- 編集フォームは現在画像のプレビューとファイル入力を表示するが、削除操作が無い。
- 一覧(show/index)は `cover_image_path` が null のときプレースホルダを表示するロジックを既に持つ（変更不要）。

## 変更内容

### 1. 編集フォーム（`resources/views/events/edit.blade.php`）

現在画像がある場合のみ、プレビューの下に削除チェックボックスを表示する。

```blade
@if ($event->cover_image_path)
    {{-- 既存のプレビュー --}}
    <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
        <input type="checkbox" name="remove_cover_image" value="1" class="rounded border-slate-300">
        現在の画像を削除する
    </label>
@endif
```

画像が無いときはチェックボックスを出さない（消す対象が無いため）。

### 2. バリデーション（`app/Http/Requests/Event/UpdateEventRequest.php`）

`rules()` に追加:

```php
'remove_cover_image' => ['nullable', 'boolean'],
```

### 3. 更新処理（`app/Http/Controllers/EventController.php::update`）

既存の差し替えロジックに「削除」分岐を1つ足す。**優先順位は「新規アップロード ＞ 削除」**（両方指定時はアップロードを優先）。

```php
$data = collect($request->validated())->except(['cover_image', 'remove_cover_image'])->all();

if ($request->hasFile('cover_image')) {
    // 差し替え（既存）：旧画像を削除して新画像を保存
    if ($event->cover_image_path !== null) {
        Storage::disk('public')->delete($event->cover_image_path);
    }
    $data['cover_image_path'] = $request->file('cover_image')->store("events/{$event->id}", 'public');
} elseif ($request->boolean('remove_cover_image') && $event->cover_image_path !== null) {
    // 削除：ファイルを消して null に
    Storage::disk('public')->delete($event->cover_image_path);
    $data['cover_image_path'] = null;
}

$event->update($data);
```

### 4. 表示

変更なし。`cover_image_path` が null になれば既存ロジックがプレースホルダを表示する。

## エッジケース

- 削除チェックありだが既存画像が無い → 何もしない（安全）。
- 新規アップロード＋削除チェック同時 → アップロードを優先（新画像になる）。
- 権限は既存の `$this->authorize('update', $event)` を流用（主催者・共同主催者のみ）。

## テスト（Feature: `tests/Feature/Events/EventCoverImageTest.php` に追記）

- `remove_cover_image=1` で更新 → ファイルが削除され `cover_image_path` が null。
- `remove_cover_image` 未指定で更新 → 既存画像が維持される。
- 新規アップロード＋`remove_cover_image=1` → 新画像が設定される（アップロード優先）。
- 既存画像が無い状態で `remove_cover_image=1` → エラーにならず維持（null のまま）。

## 変更ファイル一覧

| 種別 | ファイル | 内容 |
|---|---|---|
| 変更 | `resources/views/events/edit.blade.php` | 削除チェックボックス追加 |
| 変更 | `app/Http/Requests/Event/UpdateEventRequest.php` | `remove_cover_image` ルール追加 |
| 変更 | `app/Http/Controllers/EventController.php` | update に削除分岐追加 |
| 変更 | `tests/Feature/Events/EventCoverImageTest.php` | 削除テスト追加 |
