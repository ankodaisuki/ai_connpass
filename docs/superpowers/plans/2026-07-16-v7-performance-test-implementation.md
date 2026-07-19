# v7 性能試験・ロングランテスト 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** スペック `docs/superpowers/specs/2026-07-11-v7-performance-test-design.md` の性能試験（限界探索ランプ・スパイク再現・24時間ロングラン）を実行可能にする一式（シードコマンド・整合性検証・観測スナップショット・k6シナリオ・実施手順書）を作る。

**Architecture:** 負荷生成は k6（ローカルMacから実行、対象は Railway 本番 or ローカルSail）。シード・検証・観測は artisan コマンド（`perf:seed` / `perf:verify` / `perf:snapshot`）としてアプリ側に実装し、Pest でテストする。k6 シナリオは `perf/k6/` に置き、SLO を thresholds として埋め込んで合否判定を自動化する。

**Tech Stack:** k6（brew でインストール、composer/npm 依存は増やさない）、Laravel artisan コマンド、Pest。

## ツール選定の記録（スペック「次のステップ2」の決定）

| 候補 | シナリオ記述性 | 整合性検証のしやすさ | 判断 |
|---|---|---|---|
| **k6（採用）** | JS でジャーニーを記述。CSRF 抽出・Cookie jar が素直に書ける | SLO を `thresholds` に書けば実行結果が自動で合否判定される。RPS 制御（arrival-rate executor）が正確 | **採用** |
| Locust | Python で柔軟 | 分散実行前提の作りで単機・単発の本試験にはオーバーキル。SLO 判定は自作が必要 | 不採用 |
| Gatling | Scala/JVM。レポートは綺麗 | 学習コスト高、JVM セットアップが重い | 不採用 |

## Global Constraints

- ドキュメント・コメント・コミットメッセージは日本語（グローバル CLAUDE.md）
- PHP 変更後は `vendor/bin/pint --dirty --format agent` を実行してからコミット
- テストは Pest。`./vendor/bin/sail artisan test --compact` で実行（Docker/Sail 起動が前提）
- composer / npm の依存は追加しない。k6 は外部バイナリ（`brew install k6`）
- 新ディレクトリ `perf/` を作成する（k6 スクリプト置き場。本計画のレビューで承認済みとする）
- 本番（Railway）での破壊的操作の前に必ず DB バックアップを取る（プロジェクト CLAUDE.md）
- 試験用データはすべて `@perf.test` ドメインのメール・`【perf】` プレフィックスの名前で識別可能にする（後始末のため）
- 試験アカウントの共通パスワードは `perf-pass-2026`（k6 スクリプトとシードで一致させること）
- 既存実装の前提: ログイン POST は `email` / `password` / `remember` / `_token`。ハイブリッドイベント（`prefecture='ハイブリッド'`）への申込 POST は `attendance_mode`（`online` / `in_person`）が必須。Enum 値は EventStatus::Published=1、AttendanceStatus Applied=0/Cancelled=1/Waitlisted=2

## ファイル構成（このプランで作るもの）

```
perf/
├── README.md                    # ツール選定・実行方法・実施手順（runbook）
└── k6/
    ├── lib/journey.js           # 共通ジャーニー（CSRFログイン・閲覧・申込 等）
    ├── ramp.js                  # 試験1(a) 限界探索ランプ
    ├── spike.js                 # 試験1(b) スパイク再現
    └── longrun.js               # 試験2 24時間ロングラン
app/Console/Commands/
├── PerfSeed.php                 # perf:seed 大量データ＋試験用データ投入
├── PerfVerify.php               # perf:verify 定員・キャンセル待ち整合性検証
└── PerfSnapshot.php             # perf:snapshot 観測スナップショット(JSONL)
routes/console.php               # perf:snapshot の毎時スケジュール（env ゲート）
config/app.php                   # perf_monitoring 設定の追加
tests/Feature/Console/
├── PerfSeedTest.php
├── PerfVerifyTest.php
└── PerfSnapshotTest.php
docs/test/v7-performance-report.md  # レポート雛形（実施時に記入）
```

---

### Task 1: perf/ ディレクトリと README（ツール選定・全体像）

**Files:**
- Create: `perf/README.md`

**Interfaces:**
- Produces: `perf/` ディレクトリ（以降のタスクの置き場）と、Task 10 で拡充する README の骨子

- [ ] **Step 1: k6 をインストールして動作確認**

```bash
brew install k6
k6 version
```

Expected: `k6 v0.5x.x` などのバージョン表示（vのメジャーは問わない）

- [ ] **Step 2: perf/README.md を作成**

````markdown
# v7 性能試験（perf）

スペック: `docs/superpowers/specs/2026-07-11-v7-performance-test-design.md`

## ツール選定

| 候補 | 判断 | 理由 |
|---|---|---|
| **k6（採用）** | ✅ | シナリオをJSで記述でき、SLOを`thresholds`で自動合否判定できる。arrival-rate executorでRPSを正確に制御できる |
| Locust | ❌ | 分散実行前提で単機・単発の本試験にはオーバーキル。SLO判定は自作 |
| Gatling | ❌ | Scala/JVMで学習・セットアップコストが高い |

## 構成

- `k6/lib/journey.js` — 共通ジャーニー（CSRFログイン・閲覧・申込）
- `k6/ramp.js` — 試験1(a) 限界探索ランプ（5→10→25→50→100 RPS）
- `k6/spike.js` — 試験1(b) スパイク再現（殺到1分 約100 RPS）
- `k6/longrun.js` — 試験2 24時間ロングラン（3〜5 RPS＋毎時10 RPS×5分）

## RPS とジャーニーの換算

1ジャーニー ≒ 7動的リクエスト。k6 の arrival rate（イテレーション開始数/秒）×7 ≒ RPS。
スペックの「最初の1分に1,500人」は think time を除いた等価RPS（約100 RPS）に圧縮してモデル化する
（サーバーに届く仕事量は同じ。ユーザー数のリアリティより RPS の正確さを優先）。

## 実行方法・実施手順（runbook）

Task 10 で追記する。
````

- [ ] **Step 3: コミット**

```bash
git add perf/README.md
git commit -m "docs: v7性能試験のツール選定（k6採用）とperf/ディレクトリを追加"
```

---

### Task 2: perf:seed — 試験用アカウントと対象イベントの投入

**Files:**
- Create: `app/Console/Commands/PerfSeed.php`
- Test: `tests/Feature/Console/PerfSeedTest.php`

**Interfaces:**
- Produces: artisan コマンド `perf:seed`。オプション: `--test-accounts=` `--users=` `--events=` `--published-events=` `--attendances=` `--chunk=` `--force`
- Produces: 試験用アカウント `perf_user_{1..N}@perf.test`（パスワード `perf-pass-2026`）、主催者 `perf_organizer@perf.test`、対象イベント（`【perf】超人気カンファレンス`・ハイブリッド・capacity 2000・Published）。**コマンドは最後に `TARGET_EVENT_ID={id}` を標準出力する**（k6 が使う）
- このタスクでは試験用アカウント・主催者・対象イベント・公開背景イベントまで実装する。大量蓄積データ（`--users`/`--events`/`--attendances`）は Task 3 で実装（オプション定義だけ先に置く）

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Console/PerfSeedTest.php`:

```php
<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('perf:seed が試験用アカウントと対象イベントを投入する', function () {
    $this->artisan('perf:seed', [
        '--test-accounts' => 10,
        '--users' => 0,
        '--events' => 0,
        '--published-events' => 5,
        '--attendances' => 0,
        '--force' => true,
    ])
        ->expectsOutputToContain('TARGET_EVENT_ID=')
        ->assertSuccessful();

    // 試験用アカウント（既知パスワードでログイン可能）
    expect(User::where('email', 'like', 'perf_user_%@perf.test')->count())->toBe(10);
    $user = User::where('email', 'perf_user_1@perf.test')->firstOrFail();
    expect(Hash::check('perf-pass-2026', $user->password))->toBeTrue();

    // 対象イベント: ハイブリッド・定員2000・公開済み・未来開催
    $target = Event::where('title', '【perf】超人気カンファレンス')->firstOrFail();
    expect($target->prefecture)->toBe('ハイブリッド')
        ->and($target->capacity)->toBe(2000)
        ->and($target->status)->toBe(EventStatus::Published)
        ->and($target->online_url)->not->toBeNull()
        ->and($target->event_date->isFuture())->toBeTrue();

    // 公開中の背景イベント
    expect(
        Event::where('status', EventStatus::Published)
            ->where('title', 'like', '【perf】背景イベント%')->count()
    )->toBe(5);
});

test('perf:seed は --force なしでは確認プロンプトを出す', function () {
    $this->artisan('perf:seed', ['--test-accounts' => 1])
        ->expectsConfirmation('大量データを投入します。よろしいですか？', 'no')
        ->assertFailed();
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfSeedTest`
Expected: FAIL（`perf:seed` コマンドが存在しない）

- [ ] **Step 3: コマンドを実装**

```bash
./vendor/bin/sail artisan make:command PerfSeed --no-interaction
```

`app/Console/Commands/PerfSeed.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PerfSeed extends Command
{
    protected $signature = 'perf:seed
        {--test-accounts=5000 : k6が使う既知パスワードの試験用アカウント数}
        {--users=1000000 : 蓄積データとしての一般ユーザー数}
        {--events=200000 : 蓄積データとしての過去イベント数}
        {--published-events=5000 : 公開中の背景イベント数}
        {--attendances=3000000 : 蓄積データとしての申込履歴数}
        {--chunk=1000 : バルクINSERTのチャンクサイズ}
        {--force : 確認プロンプトをスキップ}';

    protected $description = '性能試験用のデータを投入する（試験アカウント・対象イベント・想定規模の蓄積データ）';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('大量データを投入します。よろしいですか？')) {
            return self::FAILURE;
        }

        $hash = Hash::make('perf-pass-2026');
        $now = now();

        $organizerId = $this->seedTestAccounts($hash, $now);
        $targetEventId = $this->seedTargetEvent($organizerId, $now);
        $this->seedPublishedEvents($organizerId, $now);
        $this->seedBulkData($hash, $organizerId, $now);

        $this->info("TARGET_EVENT_ID={$targetEventId}");

        return self::SUCCESS;
    }

    /**
     * 試験用アカウント（既知パスワード）と主催者を投入し、主催者IDを返す。
     */
    private function seedTestAccounts(string $hash, \Illuminate\Support\Carbon $now): int
    {
        $organizerId = DB::table('users')->insertGetId([
            'email' => 'perf_organizer@perf.test',
            'name' => '【perf】主催者',
            'password' => $hash,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $count = (int) $this->option('test-accounts');
        $chunk = (int) $this->option('chunk');
        foreach (array_chunk(range(1, max($count, 0)), max($chunk, 1)) as $ids) {
            DB::table('users')->insert(array_map(fn (int $i): array => [
                'email' => "perf_user_{$i}@perf.test",
                'name' => "【perf】試験ユーザー{$i}",
                'password' => $hash,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids));
        }
        $this->info("試験用アカウント {$count} 件を投入");

        return $organizerId;
    }

    /**
     * スパイク対象の超人気イベント（ハイブリッド・定員2000）を作成し、IDを返す。
     */
    private function seedTargetEvent(int $organizerId, \Illuminate\Support\Carbon $now): int
    {
        return DB::table('events')->insertGetId([
            'user_id' => $organizerId,
            'title' => '【perf】超人気カンファレンス',
            'description' => '性能試験（スパイク）対象イベント。会場200人＋オンライン1,800人のハイブリッド想定。',
            'category' => EventCategory::Backend->value,
            'prefecture' => 'ハイブリッド',
            'location' => '東京国際フォーラム＋オンライン',
            'online_url' => 'https://example.com/perf-live',
            'event_date' => $now->copy()->addDays(7)->setTime(19, 0),
            'end_date' => $now->copy()->addDays(7)->setTime(21, 0),
            'capacity' => 2000,
            'status' => EventStatus::Published->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 公開中の背景イベント（閲覧ジャーニーの分散先）を投入する。
     */
    private function seedPublishedEvents(int $organizerId, \Illuminate\Support\Carbon $now): void
    {
        $count = (int) $this->option('published-events');
        $chunk = (int) $this->option('chunk');
        $prefectures = ['東京都', '大阪府', '愛知県', 'オンライン', '福岡県'];

        foreach (array_chunk(range(1, max($count, 0)), max($chunk, 1)) as $ids) {
            DB::table('events')->insert(array_map(fn (int $i): array => [
                'user_id' => $organizerId,
                'title' => "【perf】背景イベント{$i}",
                'description' => '性能試験の背景負荷用イベント。',
                'category' => EventCategory::cases()[$i % count(EventCategory::cases())]->value,
                'prefecture' => $prefectures[$i % count($prefectures)],
                'location' => '会場'.$i,
                'online_url' => null,
                'event_date' => $now->copy()->addDays(1 + ($i % 30))->setTime(19, 0),
                'end_date' => $now->copy()->addDays(1 + ($i % 30))->setTime(21, 0),
                'capacity' => 20 + ($i % 180),
                'status' => EventStatus::Published->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids));
        }
        $this->info("公開背景イベント {$count} 件を投入");
    }

    /**
     * 想定規模の蓄積データ（一般ユーザー・過去イベント・申込履歴）を投入する。
     * Task 3 で実装する。
     */
    private function seedBulkData(string $hash, int $organizerId, \Illuminate\Support\Carbon $now): void
    {
        // Task 3 で実装
    }
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfSeedTest`
Expected: PASS（2件）

- [ ] **Step 5: Pint を実行してコミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/PerfSeed.php tests/Feature/Console/PerfSeedTest.php
git commit -m "feat: 性能試験用シードコマンド perf:seed（試験アカウント・対象イベント・背景イベント）"
```

---

### Task 3: perf:seed — 想定規模の蓄積データ（バルク投入）

**Files:**
- Modify: `app/Console/Commands/PerfSeed.php`（`seedBulkData` の実装）
- Test: `tests/Feature/Console/PerfSeedTest.php`（テスト追加）

**Interfaces:**
- Consumes: Task 2 の `perf:seed` オプション定義と `seedBulkData(string $hash, int $organizerId, Carbon $now)` の呼び出し
- Produces: `--users` 件の一般ユーザー（`bulk_user_{i}@perf.test`）、`--events` 件の過去イベント（`【perf】過去イベント{i}`）、`--attendances` 件の申込履歴（Applied 80% / Cancelled 15% / Waitlisted 5%）

- [ ] **Step 1: 失敗するテストを追加**

`tests/Feature/Console/PerfSeedTest.php` に追記:

```php
test('perf:seed が蓄積データ（ユーザー・過去イベント・申込履歴）を投入する', function () {
    $this->artisan('perf:seed', [
        '--test-accounts' => 0,
        '--users' => 50,
        '--events' => 20,
        '--published-events' => 0,
        '--attendances' => 100,
        '--chunk' => 30, // チャンク境界をまたぐ件数で検証
        '--force' => true,
    ])->assertSuccessful();

    expect(User::where('email', 'like', 'bulk_user_%@perf.test')->count())->toBe(50);
    expect(Event::where('title', 'like', '【perf】過去イベント%')->count())->toBe(20);
    expect(DB::table('event_attendances')->count())->toBe(100);

    // (event_id, user_id) のユニーク制約に反していない（挿入が成功している時点で保証されるが件数で再確認）
    $duplicates = DB::table('event_attendances')
        ->select('event_id', 'user_id')
        ->groupBy('event_id', 'user_id')
        ->havingRaw('COUNT(*) > 1')
        ->count();
    expect($duplicates)->toBe(0);

    // 過去イベントは終了済み
    $past = Event::where('title', '【perf】過去イベント1')->firstOrFail();
    expect($past->end_date->isPast())->toBeTrue();
});
```

ファイル先頭の `use` に追加: `use Illuminate\Support\Facades\DB;`

- [ ] **Step 2: テストが失敗することを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfSeedTest`
Expected: 新テストが FAIL（bulk_user が0件）

- [ ] **Step 3: seedBulkData を実装**

`app/Console/Commands/PerfSeed.php` の `seedBulkData` を置き換え:

```php
    /**
     * 想定規模の蓄積データ（一般ユーザー・過去イベント・申込履歴）を投入する。
     *
     * 空のDBで試験すると実態より良い数字が出るため、想定時点までに蓄積している
     * はずの行数を事前に入れる（スペック「事前データ」参照）。
     */
    private function seedBulkData(string $hash, int $organizerId, \Illuminate\Support\Carbon $now): void
    {
        $chunk = max((int) $this->option('chunk'), 1);
        $userCount = (int) $this->option('users');
        $eventCount = (int) $this->option('events');
        $attendanceCount = (int) $this->option('attendances');

        // 一般ユーザー（bcryptハッシュは1回だけ計算して使い回す）
        $userStartId = null;
        for ($offset = 0; $offset < $userCount; $offset += $chunk) {
            $rows = [];
            for ($i = $offset + 1; $i <= min($offset + $chunk, $userCount); $i++) {
                $rows[] = [
                    'email' => "bulk_user_{$i}@perf.test",
                    'name' => "【perf】一般ユーザー{$i}",
                    'password' => $hash,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($rows);
            $userStartId ??= (int) DB::table('users')->where('email', 'bulk_user_1@perf.test')->value('id');
            if (($offset + $chunk) % 100000 < $chunk) {
                $this->info('ユーザー投入: '.min($offset + $chunk, $userCount)."/{$userCount}");
            }
        }

        // 過去イベント（終了済み。申込履歴のぶら下げ先）
        $eventStartId = null;
        for ($offset = 0; $offset < $eventCount; $offset += $chunk) {
            $rows = [];
            for ($i = $offset + 1; $i <= min($offset + $chunk, $eventCount); $i++) {
                $date = $now->copy()->subDays(1 + ($i % 365))->setTime(19, 0);
                $rows[] = [
                    'user_id' => $organizerId,
                    'title' => "【perf】過去イベント{$i}",
                    'description' => '蓄積データ用の終了済みイベント。',
                    'category' => EventCategory::cases()[$i % count(EventCategory::cases())]->value,
                    'prefecture' => '東京都',
                    'location' => '会場'.$i,
                    'online_url' => null,
                    'event_date' => $date,
                    'end_date' => $date->copy()->addHours(2),
                    'capacity' => 20 + ($i % 180),
                    'status' => EventStatus::Published->value,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            DB::table('events')->insert($rows);
            $eventStartId ??= (int) DB::table('events')->where('title', '【perf】過去イベント1')->value('id');
        }

        // 申込履歴。(event, user) ペアが衝突しないよう、イベントは15件ごと・ユーザーは連番で割り当てる
        if ($attendanceCount > 0 && $userCount > 0 && $eventCount > 0) {
            for ($offset = 0; $offset < $attendanceCount; $offset += $chunk) {
                $rows = [];
                for ($n = $offset; $n < min($offset + $chunk, $attendanceCount); $n++) {
                    $statusRoll = $n % 20; // 80% Applied / 15% Cancelled / 5% Waitlisted
                    $status = match (true) {
                        $statusRoll < 16 => 0, // Applied
                        $statusRoll < 19 => 1, // Cancelled
                        default => 2,          // Waitlisted
                    };
                    $rows[] = [
                        'event_id' => $eventStartId + (intdiv($n, 15) % $eventCount),
                        'user_id' => $userStartId + ($n % $userCount),
                        'status' => $status,
                        'applied_at' => $now->copy()->subDays($n % 365),
                        'cancelled_at' => $status === 1 ? $now->copy()->subDays($n % 30) : null,
                        'waitlisted_at' => $status === 2 ? $now->copy()->subDays($n % 365) : null,
                        'attendance_mode' => $n % 3 === 0 ? 'online' : 'in_person',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('event_attendances')->insert($rows);
                if (($offset + $chunk) % 500000 < $chunk) {
                    $this->info('申込履歴投入: '.min($offset + $chunk, $attendanceCount)."/{$attendanceCount}");
                }
            }
        }

        $this->info("蓄積データ投入完了（ユーザー{$userCount}・過去イベント{$eventCount}・申込{$attendanceCount}）");
    }
```

> 衝突しない根拠: `event = intdiv(n,15) % E`、`user = n % U`。同一ペアが再出現するのは n が U の倍数ズレたときだが、そのとき event 側は `intdiv(n,15)` が U/15 ずれ、E との剰余が一致しない（U=100万・E=20万の既定値で attendances 300万件まで衝突なし）。テストは chunk 境界をまたぐ件数（100件・chunk30）で検証する。

- [ ] **Step 4: テストが通ることを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfSeedTest`
Expected: PASS（3件）

- [ ] **Step 5: Pint を実行してコミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/PerfSeed.php tests/Feature/Console/PerfSeedTest.php
git commit -m "feat: perf:seed に想定規模の蓄積データ投入（ユーザー・過去イベント・申込履歴）を追加"
```

---

### Task 4: perf:verify — 定員・キャンセル待ちの整合性検証

**Files:**
- Create: `app/Console/Commands/PerfVerify.php`
- Test: `tests/Feature/Console/PerfVerifyTest.php`

**Interfaces:**
- Produces: artisan コマンド `perf:verify {event}`。スパイク試験後に実行し、①定員超過ゼロ ②空席があるのにキャンセル待ちが残っていない（繰り上げ漏れなし）を検証。違反があれば exit code 1

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Console/PerfVerifyTest.php`:

```php
<?php

use App\Enums\AttendanceStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAttendance(Event $event, AttendanceStatus $status): EventAttendance
{
    return EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'status' => $status,
        'applied_at' => $status === AttendanceStatus::Applied ? now() : null,
        'waitlisted_at' => $status === AttendanceStatus::Waitlisted ? now() : null,
    ]);
}

test('整合な状態なら成功する（定員ちょうど＋キャンセル待ち）', function () {
    $event = Event::factory()->create(['capacity' => 2]);
    makeAttendance($event, AttendanceStatus::Applied);
    makeAttendance($event, AttendanceStatus::Applied);
    makeAttendance($event, AttendanceStatus::Waitlisted);

    $this->artisan('perf:verify', ['event' => $event->id])
        ->expectsOutputToContain('整合性OK')
        ->assertSuccessful();
});

test('定員超過を検出して失敗する', function () {
    $event = Event::factory()->create(['capacity' => 1]);
    makeAttendance($event, AttendanceStatus::Applied);
    makeAttendance($event, AttendanceStatus::Applied); // 超過

    $this->artisan('perf:verify', ['event' => $event->id])
        ->expectsOutputToContain('定員超過')
        ->assertFailed();
});

test('空席があるのにキャンセル待ちが残っていれば繰り上げ漏れとして失敗する', function () {
    $event = Event::factory()->create(['capacity' => 2]);
    makeAttendance($event, AttendanceStatus::Applied); // 空席1
    makeAttendance($event, AttendanceStatus::Waitlisted);

    $this->artisan('perf:verify', ['event' => $event->id])
        ->expectsOutputToContain('繰り上げ漏れ')
        ->assertFailed();
});
```

> 注: `EventAttendanceFactory` は存在する（`database/factories/`）。既存テスト（`tests/Feature/Reminder/ReminderStoreTest.php:41` 等）と同じ使い方。
>
> スコープ注記: スペックの整合性観点のうち「キャンセル待ちの順序が申込順どおりか」は、繰り上げ処理時の順序ロジックとして既存 Feature テストが担保している。`perf:verify` は試験後の**終状態の不変条件**（定員超過ゼロ・繰り上げ漏れゼロ）を検証する役割分担とする。

- [ ] **Step 2: テストが失敗することを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfVerifyTest`
Expected: FAIL（コマンド未定義）

- [ ] **Step 3: コマンドを実装**

```bash
./vendor/bin/sail artisan make:command PerfVerify --no-interaction
```

`app/Console/Commands/PerfVerify.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Console\Command;

class PerfVerify extends Command
{
    protected $signature = 'perf:verify {event : 対象イベントID}';

    protected $description = '性能試験後に定員・キャンセル待ちの整合性を検証する（違反があれば exit 1）';

    public function handle(): int
    {
        $event = Event::withTrashed()->findOrFail((int) $this->argument('event'));

        $applied = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Applied)
            ->count();
        $waitlisted = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('status', AttendanceStatus::Waitlisted)
            ->count();

        $this->table(
            ['項目', '値'],
            [
                ['定員', $event->capacity],
                ['参加確定（Applied）', $applied],
                ['キャンセル待ち（Waitlisted）', $waitlisted],
            ],
        );

        $violations = [];
        if ($applied > $event->capacity) {
            $violations[] = "定員超過: 参加確定 {$applied} 件 > 定員 {$event->capacity}";
        }
        if ($applied < $event->capacity && $waitlisted > 0) {
            $violations[] = "繰り上げ漏れ: 空席 ".($event->capacity - $applied)." があるのにキャンセル待ち {$waitlisted} 件が残存";
        }

        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->error($violation);
            }

            return self::FAILURE;
        }

        $this->info('整合性OK');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfVerifyTest`
Expected: PASS（3件）

- [ ] **Step 5: Pint を実行してコミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/PerfVerify.php tests/Feature/Console/PerfVerifyTest.php
git commit -m "feat: 性能試験後の整合性検証コマンド perf:verify（定員超過・繰り上げ漏れ検出）"
```

---

### Task 5: perf:snapshot — 観測スナップショット（JSONL）と毎時スケジュール

**Files:**
- Create: `app/Console/Commands/PerfSnapshot.php`
- Modify: `routes/console.php`（毎時スケジュール追加）
- Modify: `config/app.php`（`perf_monitoring` 設定追加）
- Test: `tests/Feature/Console/PerfSnapshotTest.php`

**Interfaces:**
- Produces: artisan コマンド `perf:snapshot`。`storage/logs/perf-snapshots.jsonl` に1行 JSON を追記（sessions/jobs/failed_jobs/cache/users/events/event_attendances の行数、MySQL の Threads_connected・Innodb_row_lock_waits）。`PERF_MONITORING=true` のとき毎時自動実行

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Console/PerfSnapshotTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

test('perf:snapshot がテーブル行数とDB状態をJSONLに追記する', function () {
    $path = storage_path('logs/perf-snapshots.jsonl');
    File::delete($path);

    $this->artisan('perf:snapshot')->assertSuccessful();
    $this->artisan('perf:snapshot')->assertSuccessful();

    $lines = array_filter(explode("\n", File::get($path)));
    expect($lines)->toHaveCount(2);

    $snapshot = json_decode($lines[0], true);
    expect($snapshot)->toHaveKeys(['at', 'tables', 'mysql'])
        ->and($snapshot['tables'])->toHaveKeys([
            'sessions', 'jobs', 'failed_jobs', 'cache', 'users', 'events', 'event_attendances',
        ])
        ->and($snapshot['mysql'])->toHaveKey('Threads_connected');
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfSnapshotTest`
Expected: FAIL（コマンド未定義）

- [ ] **Step 3: コマンドとスケジュールを実装**

```bash
./vendor/bin/sail artisan make:command PerfSnapshot --no-interaction
```

`app/Console/Commands/PerfSnapshot.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PerfSnapshot extends Command
{
    protected $signature = 'perf:snapshot';

    protected $description = 'ロングラン観測用のスナップショット（テーブル行数・DB状態）をJSONLに追記する';

    public function handle(): int
    {
        $tables = collect([
            'sessions', 'jobs', 'failed_jobs', 'cache', 'users', 'events', 'event_attendances',
        ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $mysql = collect(DB::select(
            "SHOW GLOBAL STATUS WHERE Variable_name IN
             ('Threads_connected', 'Innodb_row_lock_waits', 'Innodb_row_lock_time', 'Slow_queries')"
        ))->mapWithKeys(fn (object $row): array => [$row->Variable_name => $row->Value]);

        $line = json_encode([
            'at' => now()->toIso8601String(),
            'tables' => $tables,
            'mysql' => $mysql,
        ], JSON_UNESCAPED_UNICODE);

        File::append(storage_path('logs/perf-snapshots.jsonl'), $line."\n");
        $this->line($line);

        return self::SUCCESS;
    }
}
```

`config/app.php` の配列に追加（`'timezone'` などと同じ階層）:

```php
    /*
    |--------------------------------------------------------------------------
    | 性能試験の観測モード
    |--------------------------------------------------------------------------
    | true のとき perf:snapshot が毎時スケジュール実行される（v7 性能試験用）。
    */
    'perf_monitoring' => env('PERF_MONITORING', false),
```

`routes/console.php` に追加:

```php
use Illuminate\Support\Facades\Schedule;

if (config('app.perf_monitoring')) {
    Schedule::command('perf:snapshot')->hourly();
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `./vendor/bin/sail artisan test --compact --filter=PerfSnapshotTest`
Expected: PASS（1件）

- [ ] **Step 5: スケジュール登録を確認**

Run: `PERF_MONITORING=true ./vendor/bin/sail artisan schedule:list`
Expected: `perf:snapshot` が hourly で表示される（`PERF_MONITORING` 未設定時は表示されない）

- [ ] **Step 6: Pint を実行してコミット**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/PerfSnapshot.php routes/console.php config/app.php tests/Feature/Console/PerfSnapshotTest.php
git commit -m "feat: ロングラン観測用 perf:snapshot コマンドと毎時スケジュール（PERF_MONITORINGゲート）"
```

---

### Task 6: k6 共通ジャーニーライブラリ

**Files:**
- Create: `perf/k6/lib/journey.js`
- Create: `perf/k6/smoke.js`（ライブラリの動作確認用ミニシナリオ）

**Interfaces:**
- Produces（後続タスクが import する関数・定数）:
  - `SLO_THRESHOLDS`（k6 thresholds オブジェクト）
  - `login(userIndex)` — `perf_user_{i}@perf.test` でCSRF込みログイン
  - `browse()` — 一覧＋対象イベント詳細の閲覧（type:read タグ）
  - `applyToEvent()` — 対象イベントへ申込POST（type:apply タグ、ハイブリッドなので `attendance_mode` 送信）
  - `myAttendances()` — マイ参加予定の確認（type:read タグ)
  - `logout()` — ログアウトPOST
  - 環境変数: `BASE_URL`（既定 `http://localhost`）、`TARGET_EVENT_ID`（必須）、`TEST_ACCOUNTS`（アカウント総数・既定5000）

- [ ] **Step 1: journey.js を作成**

`perf/k6/lib/journey.js`:

```js
// v7 性能試験の共通ジャーニー。
// 前提: perf:seed 済み（perf_user_{i}@perf.test / perf-pass-2026 / TARGET_EVENT_ID）
import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE = __ENV.BASE_URL || 'http://localhost';
export const TARGET_EVENT_ID = __ENV.TARGET_EVENT_ID;
export const TEST_ACCOUNTS = Number(__ENV.TEST_ACCOUNTS || 5000);

// スペックのSLO（判断基準）をそのまま合否判定に使う
export const SLO_THRESHOLDS = {
  'http_req_duration{type:read}': ['p(95)<500'],
  'http_req_duration{type:auth}': ['p(95)<1000'],
  'http_req_duration{type:apply}': ['p(95)<1000'],
  http_req_failed: ['rate<0.01'],
};

// BladeフォームからCSRFトークンを抜き出す
export function extractToken(body) {
  const m = String(body).match(/name="_token"\s+value="([^"]+)"/);
  return m ? m[1] : null;
}

// ログイン: GET /login でトークン取得 → POST /login（bcrypt検証＋セッション作成）
export function login(userIndex) {
  const page = http.get(`${BASE}/login`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) {
    fail('CSRFトークンが /login から取得できない');
  }
  const res = http.post(`${BASE}/login`, {
    _token: token,
    email: `perf_user_${userIndex}@perf.test`,
    password: 'perf-pass-2026',
    remember: '1',
  }, { tags: { type: 'auth' } });
  check(res, { 'ログイン成功（/loginに戻されていない）': (r) => r.status === 200 && !r.url.endsWith('/login') });
  return res;
}

// 閲覧: 一覧 → 対象イベント詳細（リロード含め2〜3回相当は呼び出し側で回数調整）
export function browse() {
  http.get(`${BASE}/`, { tags: { type: 'read' } });
  http.get(`${BASE}/events/${TARGET_EVENT_ID}`, { tags: { type: 'read' } });
}

// 申込: 詳細ページからトークンを取り直してPOST（ハイブリッドなので attendance_mode 必須）
// 満席後の「キャンセル待ちに登録」も同じPOSTなので区別せず投げる
export function applyToEvent() {
  const page = http.get(`${BASE}/events/${TARGET_EVENT_ID}`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) {
    return null; // 申込フォームが無い（自分が主催者等）ケースは負荷対象外として黙って抜ける
  }
  const res = http.post(`${BASE}/events/${TARGET_EVENT_ID}/attendances`, {
    _token: token,
    attendance_mode: Math.random() < 0.9 ? 'online' : 'in_person', // 会場200/オンライン1800の比率相当
  }, { tags: { type: 'apply' } });
  check(res, { '申込POSTが2xxで完了（リダイレクト追跡後）': (r) => r.status === 200 });
  return res;
}

// マイ参加予定の確認（申込ジャーニーの終点）
export function myAttendances() {
  http.get(`${BASE}/my/attendances`, { tags: { type: 'read' } });
}

// ログアウト（実利用では少数派。呼び出し側で約1割に制限する）
export function logout() {
  const page = http.get(`${BASE}/`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) return;
  http.post(`${BASE}/logout`, { _token: token }, { tags: { type: 'auth' } });
}

// VUごとに重複しない試験アカウント番号を返す（1..TEST_ACCOUNTS を循環）
export function pickUserIndex() {
  return ((__VU - 1) % TEST_ACCOUNTS) + 1;
}

// 会員登録（ロングランで低頻度に混入。usersテーブルの成長を24hで観測するため）
export function registerNewUser() {
  const page = http.get(`${BASE}/register`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) return;
  const suffix = `${Date.now()}_${__VU}_${__ITER}`;
  const res = http.post(`${BASE}/register`, {
    _token: token,
    name: `【perf】登録${suffix}`,
    email: `reg_${suffix}@perf.test`,
    password: 'perf-pass-2026',
    password_confirmation: 'perf-pass-2026',
  }, { tags: { type: 'auth' } });
  check(res, { '会員登録が2xxで完了': (r) => r.status === 200 });
}

// マイページ回遊（参加済み一覧・プロフィール。ロングランで低頻度に混入）
export function browseMyPages() {
  http.get(`${BASE}/my/attended-events`, { tags: { type: 'read' } });
  http.get(`${BASE}/profile`, { tags: { type: 'read' } });
}
```

- [ ] **Step 2: smoke.js を作成**

`perf/k6/smoke.js`:

```js
// ライブラリの動作確認用ミニシナリオ（1VU×3周）。負荷はかけない。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, logout, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

export const options = {
  vus: 1,
  iterations: 3,
  thresholds: SLO_THRESHOLDS,
};

export default function () {
  login(pickUserIndex() + __ITER); // 周回ごとに別アカウント
  browse();
  applyToEvent();
  myAttendances();
  logout();
  sleep(0.5);
}
```

- [ ] **Step 3: ローカルで動作確認**

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan perf:seed --test-accounts=10 --users=0 --events=0 --published-events=5 --attendances=0 --force
# 出力の TARGET_EVENT_ID=<id> を控える
k6 run --env BASE_URL=http://localhost --env TARGET_EVENT_ID=<id> --env TEST_ACCOUNTS=10 perf/k6/smoke.js
```

Expected: `checks` がすべて成功（`✓ ログイン成功` `✓ 申込POSTが2xxで完了`）、thresholds クリア、iteration 3 完了

- [ ] **Step 4: DB側でも申込が入ったことを確認**

```bash
./vendor/bin/sail artisan perf:verify <TARGET_EVENT_ID>
```

Expected: `参加確定（Applied）` が 1 以上、`整合性OK`、exit 0

- [ ] **Step 5: コミット**

```bash
git add perf/k6/lib/journey.js perf/k6/smoke.js
git commit -m "feat: k6共通ジャーニー（CSRFログイン・閲覧・申込）とスモークシナリオ"
```

---

### Task 7: ramp.js — 試験1(a) 限界探索ランプ

**Files:**
- Create: `perf/k6/ramp.js`

**Interfaces:**
- Consumes: `lib/journey.js` の全関数と `SLO_THRESHOLDS`
- Produces: `k6 run perf/k6/ramp.js` で 5→10→25→50→100 RPS の段階負荷。`--env SMOKE=1` で30秒×2段の縮小版

- [ ] **Step 1: ramp.js を作成**

```js
// 試験1(a) 限界探索ランプ: 5→10→25→50→100 RPS 相当を各5分維持し、
// SLO違反が最初に出る負荷帯（限界RPS）を特定する。
// 1ジャーニー≒7リクエストなので arrival rate ≒ RPS/7 で設定する。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, logout, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

const SMOKE = !!__ENV.SMOKE;
const stageDuration = SMOKE ? '30s' : '5m';
// [iter/s] ≒ [目標RPS]/7 → 5,10,25,50,100 RPS ≒ 1,2,4,7,14 iter/s
const rates = SMOKE ? [1, 2] : [1, 2, 4, 7, 14];

export const options = {
  scenarios: {
    ramp: {
      executor: 'ramping-arrival-rate',
      startRate: rates[0],
      timeUnit: '1s',
      preAllocatedVUs: 50,
      maxVUs: 500,
      stages: rates.map((target) => ({ duration: stageDuration, target })),
    },
  },
  thresholds: SLO_THRESHOLDS,
};

export default function () {
  const user = pickUserIndex();
  login(user);
  browse();
  applyToEvent();
  myAttendances();
  if (Math.random() < 0.1) {
    logout(); // 実利用に合わせ約1割のみログアウト（残りはセッション放置）
  }
  sleep(0.2);
}
```

- [ ] **Step 2: 縮小版でローカル実行**

```bash
k6 run --env BASE_URL=http://localhost --env TARGET_EVENT_ID=<id> --env TEST_ACCOUNTS=10 --env SMOKE=1 perf/k6/ramp.js
```

Expected: 1分で完走。`dropped_iterations` が僅少で、サマリーに `http_req_duration{type:read}` / `{type:apply}` の p(95) と thresholds 判定が表示される

- [ ] **Step 3: コミット**

```bash
git add perf/k6/ramp.js
git commit -m "feat: 限界探索ランプシナリオ ramp.js（5→100 RPS段階増加・SLO自動判定）"
```

---

### Task 8: spike.js — 試験1(b) スパイク再現

**Files:**
- Create: `perf/k6/spike.js`

**Interfaces:**
- Consumes: `lib/journey.js`
- Produces: `k6 run perf/k6/spike.js` で4フェーズ（ウォームアップ3分→殺到1分≒100 RPS→後続10分≒10〜20 RPS→クールダウン3分）。`--env SMOKE=1` で各フェーズ30秒の縮小版

- [ ] **Step 1: spike.js を作成**

```js
// 試験1(b) スパイク再現: 受付開始直後の殺到（1分・約100 RPS）と、
// 限界超過時の壊れ方・負荷が去った後の自力回復（クールダウン）を観察する。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

const SMOKE = !!__ENV.SMOKE;
const m = (min) => (SMOKE ? '30s' : `${min}m`);

// 閲覧のみのジャーニー（ウォームアップ・クールダウン用、約2リクエスト）
export function browseOnly() {
  browse();
  sleep(0.2);
}

// 申込ジャーニー（殺到・後続用、約7リクエスト）
export function applyJourney() {
  login(pickUserIndex());
  browse();
  applyToEvent();
  myAttendances();
  sleep(0.2);
}

export const options = {
  scenarios: {
    // ① ウォームアップ: 日常ピーク帯 3〜5 RPS（閲覧2req×2 iter/s ≒ 4 RPS）
    warmup: {
      executor: 'constant-arrival-rate',
      exec: 'browseOnly',
      rate: 2, timeUnit: '1s',
      duration: m(3),
      preAllocatedVUs: 20, maxVUs: 100,
    },
    // ② 殺到: 1分・約100 RPS（7req×14 iter/s）
    rush: {
      executor: 'constant-arrival-rate',
      exec: 'applyJourney',
      rate: 14, timeUnit: '1s',
      duration: m(1),
      startTime: m(3),
      preAllocatedVUs: 100, maxVUs: 1000,
    },
    // ③ 後続: 10分・約10〜20 RPS（7req×2 iter/s ≒ 14 RPS）＋背景閲覧
    followup: {
      executor: 'constant-arrival-rate',
      exec: 'applyJourney',
      rate: 2, timeUnit: '1s',
      duration: m(10),
      startTime: SMOKE ? '60s' : '4m',
      preAllocatedVUs: 50, maxVUs: 300,
    },
    // ④ クールダウン: 3分・閲覧のみ（ベースライン復帰の確認）
    cooldown: {
      executor: 'constant-arrival-rate',
      exec: 'browseOnly',
      rate: 2, timeUnit: '1s',
      duration: m(3),
      startTime: SMOKE ? '90s' : '14m',
      preAllocatedVUs: 20, maxVUs: 100,
    },
  },
  thresholds: SLO_THRESHOLDS,
};
```

> 注: フェーズ別の p95 比較（②→③で劣化したまま④で戻らないか）は、k6 サマリーではなく実行ログの時系列（`--out json=spike-result.json`）をレポート時に集計する。実施手順は Task 10 の runbook に記載。

- [ ] **Step 2: 縮小版でローカル実行**

```bash
k6 run --env BASE_URL=http://localhost --env TARGET_EVENT_ID=<id> --env TEST_ACCOUNTS=10 --env SMOKE=1 perf/k6/spike.js
```

Expected: 4シナリオが順に起動して2分で完走。エラー率が threshold 内

- [ ] **Step 3: スパイク後の整合性を確認**

```bash
./vendor/bin/sail artisan perf:verify <TARGET_EVENT_ID>
```

Expected: `整合性OK`（定員超過0・繰り上げ漏れ0）、exit 0

- [ ] **Step 4: コミット**

```bash
git add perf/k6/spike.js
git commit -m "feat: スパイク再現シナリオ spike.js（4フェーズ・殺到1分100RPS相当）"
```

---

### Task 9: longrun.js — 試験2 24時間ロングラン

**Files:**
- Create: `perf/k6/longrun.js`

**Interfaces:**
- Consumes: `lib/journey.js`
- Produces: `k6 run perf/k6/longrun.js` で24時間（`--env HOURS=24` 既定）のベース負荷3〜5 RPS＋毎時10 RPS×5分の小ピーク。`--env HOURS=0.2` などで短縮可

- [ ] **Step 1: longrun.js を作成**

```js
// 試験2 ロングラン: 3〜5 RPS を24時間継続し、毎時5分だけ10 RPSの小ピークを入れる。
// メモリ・テーブル行数・応答時間の「傾き」を見る試験（観測は perf:snapshot が毎時実施）。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, logout, registerNewUser, browseMyPages, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

const HOURS = Number(__ENV.HOURS || 24);

// 1時間 = 55分ベース（6 iter/10s ≒ 4 RPS）＋ 5分小ピーク（14 iter/10s ≒ 10 RPS）
// HOURS が小数（短縮版）の場合は比率を保って縮める。秒単位に丸めて指定する
const baseSec = Math.round(55 * 60 * (HOURS >= 1 ? 1 : HOURS));
const peakSec = Math.round(5 * 60 * (HOURS >= 1 ? 1 : HOURS));
const cycles = Math.max(Math.round(HOURS), 1);

const stages = [];
for (let i = 0; i < cycles; i++) {
  stages.push({ duration: `${baseSec}s`, target: 6 });
  stages.push({ duration: `${peakSec}s`, target: 14 });
}

export const options = {
  scenarios: {
    longrun: {
      executor: 'ramping-arrival-rate',
      startRate: 6,
      timeUnit: '10s', // 0.6 iter/s（≒4 RPS）を表現するため10秒単位
      preAllocatedVUs: 30,
      maxVUs: 300,
      stages,
    },
  },
  thresholds: SLO_THRESHOLDS,
};

export default function () {
  if (Math.random() < 0.05) {
    registerNewUser(); // 低頻度の会員登録（usersテーブル成長の観測用）
    sleep(0.5);
    return;
  }
  const user = pickUserIndex();
  login(user);
  browse();
  // 申込とキャンセルのサイクル（複数イベント分散はTARGET_EVENT_ID中心＋一覧閲覧で代替）
  applyToEvent();
  myAttendances();
  if (Math.random() < 0.2) {
    browseMyPages(); // 低頻度のマイページ回遊（参加済み一覧・プロフィール）
  }
  if (Math.random() < 0.1) {
    logout(); // 約1割のみログアウト。9割はセッション放置（sessionsテーブル肥大の再現条件）
  }
  sleep(0.5);
}
```

> 注: スペックの「申込→数分後にキャンセル→別ユーザーが申込」は、同一アカウントが周回ごとに `applyToEvent()` を呼ぶことで「すでに申し込み済みです」エラー（4xx扱い・SLO対象外）とキャンセル待ち登録が混ざる形になる。キャンセルPOST（DELETE 相当）を厳密に混ぜる改良は初回実施の結果を見てから判断する（YAGNI）。

- [ ] **Step 2: 短縮版でローカル実行**

```bash
k6 run --env BASE_URL=http://localhost --env TARGET_EVENT_ID=<id> --env TEST_ACCOUNTS=10 --env HOURS=0.05 perf/k6/longrun.js
```

Expected: 3分で完走（ベース→小ピークの2段が1サイクル）。thresholds 判定が表示される

- [ ] **Step 3: コミット**

```bash
git add perf/k6/longrun.js
git commit -m "feat: 24時間ロングランシナリオ longrun.js（3〜5RPS＋毎時小ピーク・時間可変）"
```

---

### Task 10: 実施手順書（runbook）とレポート雛形

**Files:**
- Modify: `perf/README.md`（実行方法・実施手順を追記）
- Create: `docs/test/v7-performance-report.md`

**Interfaces:**
- Consumes: Task 1〜9 の全成果物
- Produces: 実施時にそのまま従える手順書と、スペックのレポート構成（試験条件→SLO判定→限界RPS→リスクマトリクス→時系列→改善提案）に沿った記入用雛形

- [ ] **Step 1: perf/README.md の「実行方法・実施手順」を置き換え**

「## 実行方法・実施手順（runbook）」セクションを以下に置き換える:

````markdown
## 実行方法（ローカル検証）

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan perf:seed --test-accounts=10 --users=0 --events=0 --published-events=5 --attendances=0 --force
# TARGET_EVENT_ID=<id> が出力される
k6 run --env BASE_URL=http://localhost --env TARGET_EVENT_ID=<id> --env TEST_ACCOUNTS=10 perf/k6/smoke.js
```

## 本番（Railway）実施手順

前提: メンター承認済みスペックに基づき、Railway 無料プランの本番へ「あえて壊れる負荷」をかける。
即死しても終了せず、負荷を下げて計測可能な帯域を見つけレポーティングまでやり切る。

### 0. 事前準備（必須）

1. **DBバックアップ**（CLAUDE.md の規約。シードとテストデータを丸ごと巻き戻せるようにする）
   ```bash
   railway run mysqldump -u <user> -p<pass> ai_connpass > backup-before-perf.sql
   ```
2. **メール実送信の停止**: Railway の環境変数を `MAIL_MAILER=log` に変更（キャンセル待ち登録でメール送信が走るため。SMTPプロバイダの制限・ブラックリスト入りを防ぐ）
3. **観測の有効化**: `PERF_MONITORING=true` を設定（perf:snapshot が毎時実行される。スケジューラが動いていることを `railway run php artisan schedule:list` で確認）
4. **シード投入**（数時間かかる想定。進捗ログが10万件ごとに出る）
   ```bash
   railway run php artisan perf:seed --force
   # 出力の TARGET_EVENT_ID=<id> を控える
   ```

### 1. スモーク（1VU で疎通確認）

```bash
k6 run --env BASE_URL=https://aiconnpass-production.up.railway.app \
       --env TARGET_EVENT_ID=<id> perf/k6/smoke.js
```

### 2. 試験1(a) 限界探索ランプ（約25分）

```bash
k6 run --env BASE_URL=https://... --env TARGET_EVENT_ID=<id> \
       --out json=results/ramp.json perf/k6/ramp.js
```

- SLO違反が最初に出た段＝限界RPS。Railway のメトリクス（CPU/メモリ/DB）と突き合わせ、
  最初に音を上げたコンポーネントを記録する
- **即死した場合**: rates を下げた縮小版（`--env SMOKE=1` か rates 編集）で再実行し、
  計測可能な帯域で限界値を確定させる

### 3. 試験1(b) スパイク再現（約17分）＋整合性検証

```bash
k6 run --env BASE_URL=https://... --env TARGET_EVENT_ID=<id> \
       --out json=results/spike.json perf/k6/spike.js
railway run php artisan perf:verify <id>   # 定員超過0・繰り上げ漏れ0を確認
```

### 4. 試験2 ロングラン（24時間）

```bash
nohup k6 run --env BASE_URL=https://... --env TARGET_EVENT_ID=<id> \
       --out json=results/longrun.json perf/k6/longrun.js > results/longrun.log 2>&1 &
```

- Mac がスリープしないよう `caffeinate` を併用する
- 毎時スナップショットは本番側の `storage/logs/perf-snapshots.jsonl` に蓄積される。
  終了後に `railway ssh` などで回収する
- **アプリコンテナ・キューワーカーのメモリ推移は Railway のメトリクス画面で観測する**
  （perf:snapshot はテーブル行数とDB状態のみ。メモリの傾きはRailway側のグラフを毎時記録 or スクリーンショット）

### 5. レポート作成と後始末

1. `docs/test/v7-performance-report.md` に結果を記入
2. 環境変数を元に戻す（`MAIL_MAILER`・`PERF_MONITORING`）
3. テストデータを消す: バックアップから復元
   ```bash
   railway run mysql -u <user> -p<pass> ai_connpass < backup-before-perf.sql
   ```

## フェーズ別 p95 の集計（レポート用）

`--out json=` の結果から jq でフェーズ別に p95 を出す例:

```bash
jq -r 'select(.type=="Point" and .metric=="http_req_duration") | [.data.time, .data.value] | @csv' \
  results/spike.json > results/spike-duration.csv
# 時刻でフェーズに切り分けて表計算やスクリプトでp95を算出する
```
````

- [ ] **Step 2: レポート雛形を作成**

`docs/test/v7-performance-report.md`:

```markdown
# v7 性能試験・ロングランテスト 結果レポート

- **実施日:** （記入）
- **スペック:** docs/superpowers/specs/2026-07-11-v7-performance-test-design.md
- **実施手順:** perf/README.md
- **実行環境:** Railway 無料プラン（単一サービス・MySQL 8.4）／負荷生成: ローカルMac＋k6

## 1. 試験条件の要約

| 項目 | 値 |
|---|---|
| シード規模 | users（記入）・events（記入）・attendances（記入） |
| 対象イベント | ID（記入）・定員2,000・ハイブリッド |
| 試験1(a) ランプ | 5→10→25→50→100 RPS（各5分） |
| 試験1(b) スパイク | 殺到1分 約100 RPS |
| 試験2 ロングラン | 3〜5 RPS × 24h＋毎時10 RPS×5分 |

## 2. SLO 判定表

| 指標 | 閾値 | 実測値 | 判定 |
|---|---|---|---|
| 閲覧系 GET の p95 | 500ms 以下 | （記入） | （○/×） |
| 申込 POST の p95 | 1,000ms 以下 | （記入） | （○/×） |
| エラー率（5xx） | 1% 未満 | （記入） | （○/×） |
| 定員超過 | 0件 | perf:verify の結果（記入） | （○/×） |
| メモリの傾き | 24時間で水平収束 | （記入） | （○/×） |
| 応答時間の経時劣化 | 1時間目比 +20% 以内 | （記入） | （○/×） |

## 3. 限界 RPS とボトルネックの序列

- **限界 RPS（全SLOを満たす最大負荷）:** （記入）
- **通常ピーク（3〜5 RPS）に対する余裕倍率:** （記入）
- **スパイク要求（100 RPS）とのギャップ:** （記入）
- **最初に音を上げたコンポーネントと根拠:** （記入。例: MySQL接続枯渇 / アプリCPU / ロック待ち）

## 4. リスクマトリクス

| エンドポイント | 深刻度（整合性破壊 > 5xx > 遅延） | 負荷の集中度 | 優先度 |
|---|---|---|---|
| （記入） | | | |

## 5. 時系列（ロングラン）

- 毎時 p95 の推移: （グラフ or 表を記入。1時間目と24時間目の比較）
- メモリ・テーブル行数の傾き: （perf-snapshots.jsonl から記入。線形増加があれば「何日で限界か」の外挿を書く）

## 6. 改善提案の優先順位

1. （記入。リスクマトリクス右上から順に。スケール構成の変更案＝Redis分離・コネクションプール等を含む）
```

- [ ] **Step 3: ドキュメントの整合を確認してコミット**

Run: `grep -n "perf:seed\|perf:verify\|perf:snapshot\|TARGET_EVENT_ID" perf/README.md docs/test/v7-performance-report.md | head`
Expected: コマンド名・環境変数名が実装（Task 2〜9）と一致している

```bash
git add perf/README.md docs/test/v7-performance-report.md
git commit -m "docs: v7性能試験の実施手順書（runbook）とレポート雛形を追加"
```

---

## 実施フェーズ（実装完了後・計画外の運用作業）

Task 1〜10 が完了すると「実行可能な状態」になる。実際の本番実施（runbook の手順0〜5）は
コード変更を伴わない運用作業のため本計画のタスクには含めない。実施はユーザーと日程を
合わせて行う（24時間ロングランは Mac を起動したままにする必要がある）。
