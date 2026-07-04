# イベントカバー画像 設計書

## 概要

イベントごとにカバー画像1枚をアップロードでき、一覧・詳細ページに表示する機能。表側仕様は ADR-0004（V6）で確定済み。本書は裏側の実装設計を定める。

関連: `docs/adr/v6/product/0004-event-cover-image.md`

## 確定済みの表側仕様（ADR より）

- カバー画像1枚・**任意**（未設定時はプレースホルダ表示）
- 対応形式: JPEG / PNG / WebP、上限 5MB、寸法 4000×4000px まで
- アップロード権限: 主催者・共同主催者（既存のイベント編集権限）

---

## 設計方針

新規のファイルアップロード機能であり、既存パターンはない。Laravel 標準の Filesystem 抽象（`Storage` ファサード）を用い、ローカル開発と将来の S3 移行をコード変更なしで両立させる。

---

## 1. データベース

`events` テーブルに画像パスを保持するカラムを追加するマイグレーションを新規作成する。

```php
Schema::table('events', function (Blueprint $table) {
    $table->string('cover_image_path')->nullable()->after('description');
});
```

- nullable のため既存レコードに影響なし（ゼロダウンタイム）
- `Event` モデルの `$fillable` に `cover_image_path` を追加
- パスのみ保持し、URL 生成は `Storage::url()` に委ねる

---

## 2. ストレージ

- **ディスク**: `public`（`storage/app/public`）を使用
- **公開**: `php artisan storage:link` でシンボリックリンクを張る
- **保存パス**: `events/{event_id}/cover_{ランダム}.{拡張子}`
  - イベントごとにディレクトリを分け、削除・管理を容易にする
  - ファイル名はランダム化し推測・上書きを防ぐ（`store()` のデフォルト挙動を利用）
- **配信**: ビューで `Storage::disk('public')->url($event->cover_image_path)` を使う
- **S3 移行**: `FILESYSTEM_DISK` を `s3` に変更し AWS 認証情報を設定すれば、コード変更なしで切替可能

### Railway 対応

Railway のファイルシステムは揮発性のため、本番デプロイ前にストレージを別途決定する（ADR/本書のスコープ外、デプロイ時に判断）。ローカル開発では `public` ディスクで完結する。`storage:link` はコンテナ起動時に実行する必要があるため、起動スクリプト（Dockerfile / supervisor 設定）に追加する。

---

## 3. バリデーション

`StoreEventRequest` / `UpdateEventRequest` に `cover_image` ルールを追加する。

```php
'cover_image' => [
    'nullable',
    'image',                                  // 実コンテンツが画像か検証（拡張子偽装を防ぐ）
    'mimes:jpeg,png,webp',
    'max:5120',                               // 5MB（KB単位）
    'dimensions:max_width=4000,max_height=4000',
],
```

- `image` ルールが実際の画像内容を検査するため、拡張子偽装ファイルを拒否できる
- 検証失敗時はフォームにエラーメッセージを表示（既存の `@error` パターンに準拠）

---

## 4. コントローラ

`EventController` の `store` / `update` / `destroy` を変更する。画像保存ロジックは重複するため、private メソッドに切り出す。

### store

```php
public function store(StoreEventRequest $request): RedirectResponse
{
    $user = auth()->user();

    $event = Event::create([
        ...$request->safe()->except('cover_image'),
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

### update

- 新しい画像がアップロードされた場合、**旧画像を削除**してから新パスを保存する
- 画像未変更の場合は既存パスを維持する

```php
if ($request->hasFile('cover_image')) {
    if ($event->cover_image_path) {
        Storage::disk('public')->delete($event->cover_image_path);
    }
    $data['cover_image_path'] = $request->file('cover_image')->store("events/{$event->id}", 'public');
}
```

### destroy

- `Event` は SoftDeletes のため、削除（論理削除）時は**画像ファイルを消さない**（復元時に画像参照が壊れるため）。`destroy` は既存の削除フロー（`EventCancellationService::cancel`）を変更しない。
- 物理ファイルの掃除が必要になった場合は、将来の hard-delete 対応でまとめて扱う。運営者削除（`AdminService::deleteEvent`）も同様にソフトデリートのため画像は保持する。

### 権限

画像のアップロード権限は既存の `EventPolicy::update`（オーナー＋共同主催者）を再利用する。`update` メソッド冒頭で `$this->authorize('update', $event)` を呼んでいるため追加対応は不要。

---

## 5. ビュー

### create / edit フォーム

- `<form>` に `enctype="multipart/form-data"` を追加
- ファイル入力欄を追加（`<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">`）
- edit では現在の画像をプレビュー表示
- `@error('cover_image')` でエラー表示

### show（詳細）

- カバー画像を大きく表示
- 未設定時はプレースホルダ画像を表示

### index（一覧）

- 各イベントカードにカバー画像をサムネイル的に表示
- 未設定時はプレースホルダ画像を表示

### プレースホルダ

`public/images/event-placeholder.svg`（または同等）を用意し、未設定時に共通表示する。

---

## 6. テスト

### Feature テスト（`tests/Feature/Event/EventCoverImageTest.php`）

`Storage::fake('public')` と `UploadedFile::fake()->image()` を使う。

- 画像付きでイベント作成 → ファイル保存・`cover_image_path` 設定を確認
- 画像なしで作成 → `cover_image_path` が null
- 不正形式（PDF 等）を拒否（`assertSessionHasErrors('cover_image')`）
- サイズ超過を拒否
- 寸法超過を拒否
- 更新時に新画像へ差し替え → 旧画像が削除される
- 権限なしユーザーのアップロード拒否（403）
- イベント削除時に画像ファイルが削除される

### E2E テスト（`tests/e2e/event-cover-image.spec.ts`）

- 作成フォームから画像をアップロード → 詳細ページに画像が表示される

---

## 7. 将来の拡張（今回はやらない）

- 画像のサーバー側再エンコード（不正コード除去・ウイルス対策の強化）
- サムネイル生成・WebP 自動変換による一覧の最適化
- 複数画像ギャラリー

---

## 変更ファイル一覧

| 種別 | ファイル | 内容 |
|---|---|---|
| 新規 | `database/migrations/xxxx_add_cover_image_path_to_events_table.php` | カラム追加 |
| 変更 | `app/Models/Event.php` | `$fillable` に追加 |
| 変更 | `app/Http/Requests/Event/StoreEventRequest.php` | バリデーション追加 |
| 変更 | `app/Http/Requests/Event/UpdateEventRequest.php` | バリデーション追加 |
| 変更 | `app/Http/Controllers/EventController.php` | 保存・削除ロジック |
| 変更 | `resources/views/events/create.blade.php` | enctype・ファイル入力 |
| 変更 | `resources/views/events/edit.blade.php` | enctype・ファイル入力・プレビュー |
| 変更 | `resources/views/events/show.blade.php` | 画像表示 |
| 変更 | `resources/views/events/index.blade.php` | 画像表示 |
| 新規 | `public/images/event-placeholder.svg` | プレースホルダ |
| 新規 | `tests/Feature/Event/EventCoverImageTest.php` | Feature テスト |
| 新規 | `tests/e2e/event-cover-image.spec.ts` | E2E テスト |
| 変更 | デプロイ起動スクリプト | `storage:link` 実行 |
