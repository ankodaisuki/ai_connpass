# イベント検索・フィルタリング機能 設計書

- 作成日: 2026-05-13
- 対象タスク: 「検索・フィルタリング機能実装：イベント一覧検索」(TASKS.md)
- 依存タスク: イベント管理API実装済み (`GET /api/v1/events` 既存)

## 1. 背景と目的

イベント一覧 (`GET /api/v1/events`) は現状、全 Published イベントを event_date 昇順で返すだけのシンプルな構成。
ユーザーが目的のイベントを効率的に発見できるよう、キーワード・カテゴリ・都道府県・開催日の各軸で絞り込み検索を可能にする。

外部検索エンジン (Elasticsearch, Meilisearch, Scout 等) は今回のスコープでは使わず、DB の LIKE クエリと WHERE 句で実装する。

## 2. 採用方針

- 既存の `GET /api/v1/events` を **拡張** (新規エンドポイント追加なし)
- クエリパラメータで絞り込み (`?q=...&category=N&prefecture=...&from=...&to=...`)
- パラメータ検証は新規 FormRequest `IndexEventRequest` に集約
- 複数指定時は AND 結合
- 未指定パラメータは無視 (現状の単純一覧と完全互換)
- ソートは現状の `event_date` 昇順固定

## 3. クエリパラメータ仕様

| パラメータ | 型 | 必須 | 説明 |
|---|---|---|---|
| `q` | string (max:255) | 任意 | title または description の部分一致 (LIKE %q%) |
| `category` | integer | 任意 | EventCategory enum 値 (1-6) で完全一致 |
| `prefecture` | string (max:10) | 任意 | prefecture カラムの完全一致 |
| `from` | date (ISO8601) | 任意 | `event_date >= from` |
| `to` | date (ISO8601) | 任意 | `event_date <= to` (日付のみの場合 endOfDay 補完) |

その他、既存の `?page=N` (ページネーション) は引き続き利用可能。

### 3.1 バリデーションルール

`App\Http\Requests\Api\V1\Event\IndexEventRequest`:

```php
return [
    'q' => ['nullable', 'string', 'max:255'],
    'category' => ['nullable', 'integer', Rule::enum(EventCategory::class)],
    'prefecture' => ['nullable', 'string', 'max:10'],
    'from' => ['nullable', 'date'],
    'to' => ['nullable', 'date', 'after_or_equal:from'],
];
```

検証失敗 → `422 Unprocessable Entity` + Laravel 標準のバリデーションエラーレスポンス。

### 3.2 `to` の補完ロジック

ユーザーが `?to=2026-06-30` (日付のみ) を指定したとき、直感的には「6月30日のイベントも含めて欲しい」と期待する。
しかし `Carbon::parse('2026-06-30')` は `2026-06-30 00:00:00` を返すため、6月30日12:00 開催のイベントは含まれなくなってしまう。

これを解消するため、`to` がパースされた結果の **時・分・秒がすべて 0** の場合に限り、`endOfDay()` で23:59:59を補完する:

```php
$toDate = Carbon::parse($to);
if ($toDate->hour === 0 && $toDate->minute === 0 && $toDate->second === 0) {
    $toDate = $toDate->endOfDay();
}
```

これにより `?to=2026-06-30` は `2026-06-30 23:59:59` まで、`?to=2026-06-30T15:00:00Z` はそのままの時刻まで、と意図通りに動作する。

## 4. クエリビルダの拡張

`App\Http\Controllers\Api\V1\EventController::index` を以下の形に変更:

```php
public function index(IndexEventRequest $request): AnonymousResourceCollection
{
    $query = Event::query()
        ->with('user')
        ->where('status', EventStatus::Published);

    if ($q = $request->validated('q')) {
        $query->where(function ($qb) use ($q) {
            $qb->where('title', 'LIKE', "%{$q}%")
               ->orWhere('description', 'LIKE', "%{$q}%");
        });
    }

    if ($category = $request->validated('category')) {
        $query->where('category', EventCategory::from($category));
    }

    if ($prefecture = $request->validated('prefecture')) {
        $query->where('prefecture', $prefecture);
    }

    if ($from = $request->validated('from')) {
        $query->where('event_date', '>=', Carbon::parse($from));
    }

    if ($to = $request->validated('to')) {
        $toDate = Carbon::parse($to);
        if ($toDate->hour === 0 && $toDate->minute === 0 && $toDate->second === 0) {
            $toDate = $toDate->endOfDay();
        }
        $query->where('event_date', '<=', $toDate);
    }

    return EventResource::collection(
        $query->orderBy('event_date', 'asc')->paginate(self::PER_PAGE)
    );
}
```

## 5. ファイル構成

新規作成:

- `app/Http/Requests/Api/V1/Event/IndexEventRequest.php`

変更:

- `app/Http/Controllers/Api/V1/EventController.php` (index メソッドの拡張)
- `tests/Feature/Api/V1/EventTest.php` (検索テスト追加)

## 6. テスト計画

既存の `EventTest.php` の index テスト群に検索ケースを追加 (約 12 ケース):

### 6.1 キーワード検索 (`?q=...`)
- `q=Laravel`: title に含まれるイベントがヒット
- `q=勉強会`: description に含まれるイベントがヒット
- `q=Laravel`: title マッチと description マッチの両方が返る
- `q=NotMatching`: 該当なしで data=[] / total=0

### 6.2 カテゴリフィルタ (`?category=N`)
- `category=1`: Frontend のみがヒット
- `category=99`: バリデーションエラー 422

### 6.3 都道府県フィルタ (`?prefecture=東京都`)
- `prefecture=東京都`: 東京都のイベントのみがヒット

### 6.4 日付範囲 (`?from=...&to=...`)
- `from=2026-06-01` (日付のみ): 6/1以降のイベントがヒット (今後の6月以降)
- `to=2026-06-30` (日付のみ): 6/30 23:59:59 までのイベントがヒット (endOfDay 補完)
- `from=2026-06-01&to=2026-06-30`: 6月のイベントのみがヒット
- `from=invalid`: バリデーションエラー 422
- `from=2026-06-30&to=2026-06-01`: `after_or_equal:from` で 422

### 6.5 複数パラメータ AND 結合
- `q=Laravel&category=1`: title に Laravel を含み category=Frontend のイベントのみ

### 6.6 既存テストの回帰確認

- 既存のページネーション・ソート・SoftDeleted除外テストは引き続き PASS

## 7. スコープ外（今回は実装しない）

- ソート選択 (`?sort=...`)
- 全文検索エンジン (Scout / Elasticsearch / Meilisearch)
- 主催者 (`user`) での絞り込み
- 残席数による絞り込み
- ファセット検索 / 集計付き検索

## 8. 受け入れ基準

- [ ] `GET /api/v1/events` に5つのクエリパラメータ (q/category/prefecture/from/to) を追加できる
- [ ] パラメータ未指定時は既存の挙動と完全に同じ (回帰なし)
- [ ] 複数パラメータは AND 結合される
- [ ] 不正なパラメータは 422 で拒否される
- [ ] `to` の日付のみ指定は endOfDay 補完される
- [ ] `php artisan test --compact tests/Feature/Api/V1/EventTest.php` で全 PASS
- [ ] `vendor/bin/pint --dirty --format agent` でフォーマット違反ゼロ
- [ ] TASKS.md の検索・フィルタリング機能実装が完了に移動している
