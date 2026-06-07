# オンライン・ハイブリッドイベント対応 実装プラン

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** イベントにオンライン・ハイブリッド参加を追加し、申し込み時に参加モードを選択できるようにする。

**Architecture:** `events` テーブルに `online_url`・`online_password` を追加、`event_attendances` に `attendance_mode` を追加。定員ロジックは変更なし。新規 `AttendanceMode` Enum でモードを管理し、`EventAttendanceService::apply()` にモードを渡す。オンラインURLは申し込み済みユーザーのみ表示。

**Tech Stack:** Laravel 13 / PHP 8.4 / Blade / Pest v4

---

## ファイル一覧

| 操作 | ファイル |
|---|---|
| 新規作成 | `database/migrations/XXXX_add_online_fields_to_events.php` |
| 新規作成 | `database/migrations/XXXX_add_attendance_mode_to_event_attendances.php` |
| 新規作成 | `app/Enums/AttendanceMode.php` |
| 修正 | `app/Models/Event.php` |
| 修正 | `app/Models/EventAttendance.php` |
| 修正 | `database/factories/EventFactory.php` |
| 修正 | `database/factories/EventAttendanceFactory.php` |
| 修正 | `app/Http/Requests/Event/StoreEventRequest.php` |
| 修正 | `app/Http/Requests/Event/UpdateEventRequest.php` |
| 修正 | `app/Http/Controllers/EventAttendanceController.php` |
| 修正 | `app/Services/EventAttendanceService.php` |
| 修正 | `resources/views/events/create.blade.php` |
| 修正 | `resources/views/events/edit.blade.php` |
| 修正 | `resources/views/events/show.blade.php` |
| 修正（テスト追加） | `tests/Feature/EventTest.php` |
| 修正（テスト追加） | `tests/Feature/EventAttendanceTest.php` |

---

### Task 1: マイグレーション・Enum・Model・Factory 更新

**Files:**
- Create: `app/Enums/AttendanceMode.php`
- Create: migrations (2件)
- Modify: `app/Models/Event.php`
- Modify: `app/Models/EventAttendance.php`
- Modify: `database/factories/EventFactory.php`
- Modify: `database/factories/EventAttendanceFactory.php`

このタスクはテストなし（マイグレーションはスキーマ変更のみ、テストは Task 2・3 で実施）。

- [ ] **Step 1: AttendanceMode Enum を作成**

`app/Enums/AttendanceMode.php` を新規作成:

```php
<?php

namespace App\Enums;

enum AttendanceMode: string
{
    case Online = 'online';
    case InPerson = 'in_person';

    public function label(): string
    {
        return match($this) {
            self::Online => 'オンライン',
            self::InPerson => '対面',
        };
    }
}
```

- [ ] **Step 2: events テーブルへのマイグレーションを作成**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
php artisan make:migration add_online_fields_to_events --no-interaction
```

生成されたファイルを以下の内容に編集:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('online_url', 2048)->nullable()->after('location');
            $table->string('online_password')->nullable()->after('online_url');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['online_url', 'online_password']);
        });
    }
};
```

- [ ] **Step 3: event_attendances テーブルへのマイグレーションを作成**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
php artisan make:migration add_attendance_mode_to_event_attendances --no-interaction
```

生成されたファイルを以下の内容に編集:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendances', function (Blueprint $table): void {
            $table->string('attendance_mode')->nullable()->after('waitlisted_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendances', function (Blueprint $table): void {
            $table->dropColumn('attendance_mode');
        });
    }
};
```

- [ ] **Step 4: マイグレーション実行**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
php artisan migrate --no-interaction
```

期待: `Migrating:` 2行 → `Migrated:` 2行

- [ ] **Step 5: Event モデルを更新**

`app/Models/Event.php` の `#[Fillable([...])]` に追加:

```php
#[Fillable([
    'user_id',
    'title',
    'description',
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

`casts()` メソッドは変更不要（`online_url`・`online_password` は string のまま）。

- [ ] **Step 6: EventAttendance モデルを更新**

`app/Models/EventAttendance.php` の `#[Fillable([...])]` に追加:

```php
#[Fillable([
    'event_id',
    'user_id',
    'status',
    'applied_at',
    'cancelled_at',
    'attended_at',
    'waitlisted_at',
    'attendance_mode',
    'google_calendar_event_id',
])]
```

`casts()` メソッドに追加:

```php
protected function casts(): array
{
    return [
        'applied_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'attended_at' => 'datetime',
        'waitlisted_at' => 'datetime',
        'status' => AttendanceStatus::class,
        'attendance_mode' => \App\Enums\AttendanceMode::class,
    ];
}
```

use 文に追加:
```php
use App\Enums\AttendanceMode;
```

- [ ] **Step 7: EventFactory を更新**

`database/factories/EventFactory.php` にオンラインイベント用の state を追加（`definition()` の末尾、`draft()` の前）:

```php
/**
 * オンラインイベントを生成
 */
public function online(): static
{
    return $this->state(fn (array $attributes) => [
        'prefecture' => 'オンライン',
        'location' => null,
        'online_url' => 'https://zoom.us/j/123456789',
        'online_password' => null,
    ]);
}

/**
 * ハイブリッドイベントを生成
 */
public function hybrid(): static
{
    return $this->state(fn (array $attributes) => [
        'prefecture' => 'ハイブリッド',
        'online_url' => 'https://zoom.us/j/987654321',
        'online_password' => null,
    ]);
}
```

- [ ] **Step 8: EventAttendanceFactory を更新**

`database/factories/EventAttendanceFactory.php` の `definition()` に `attendance_mode` を追加:

```php
public function definition(): array
{
    return [
        'event_id' => Event::factory(),
        'user_id' => User::factory(),
        'status' => AttendanceStatus::Applied,
        'applied_at' => now(),
        'cancelled_at' => null,
        'attendance_mode' => \App\Enums\AttendanceMode::InPerson,
    ];
}
```

既存の `waitlisted()` state に `attendance_mode` を追加:

```php
public function waitlisted(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => AttendanceStatus::Waitlisted,
        'waitlisted_at' => now(),
        'applied_at' => null,
        'attendance_mode' => \App\Enums\AttendanceMode::InPerson,
    ]);
}
```

- [ ] **Step 9: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 10: 既存テストが壊れていないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact
```

期待: 全件 PASS

- [ ] **Step 11: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add app/Enums/AttendanceMode.php app/Models/Event.php app/Models/EventAttendance.php \
    database/migrations/ database/factories/EventFactory.php database/factories/EventAttendanceFactory.php
git commit -m "feat: AttendanceMode Enum・online_url/password・attendance_mode カラムを追加"
```

---

### Task 2: バリデーション更新 + テスト

**Files:**
- Modify: `app/Http/Requests/Event/StoreEventRequest.php`
- Modify: `app/Http/Requests/Event/UpdateEventRequest.php`
- Test: `tests/Feature/EventTest.php`

- [ ] **Step 1: テストを書く**

`tests/Feature/EventTest.php` の store テストセクション末尾に追加。

まず先頭 `use` に追加（既存の use 文の後）:
```php
use App\Enums\AttendanceMode;
```

テスト追加（store セクション末尾）:

```php
    /** オンラインイベントは online_url 必須 */
    public function test_store_requires_online_url_for_online_event(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'テストイベント',
            'description' => '説明',
            'category' => 1,
            'prefecture' => 'オンライン',
            'location' => null,
            'event_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(5)->addHours(2)->format('Y-m-d\TH:i'),
            'capacity' => 30,
        ]);

        $response->assertSessionHasErrors(['online_url']);
    }

    /** ハイブリッドイベントは online_url 必須 */
    public function test_store_requires_online_url_for_hybrid_event(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'テストイベント',
            'description' => '説明',
            'category' => 1,
            'prefecture' => 'ハイブリッド',
            'location' => '渋谷会場',
            'event_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(5)->addHours(2)->format('Y-m-d\TH:i'),
            'capacity' => 30,
        ]);

        $response->assertSessionHasErrors(['online_url']);
    }

    /** 対面イベントは online_url 不要 */
    public function test_store_does_not_require_online_url_for_in_person_event(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'テストイベント',
            'description' => '説明',
            'category' => 1,
            'prefecture' => '東京都',
            'location' => '渋谷会場',
            'event_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(5)->addHours(2)->format('Y-m-d\TH:i'),
            'capacity' => 30,
        ]);

        $response->assertSessionMissing('errors');
    }

    /** オンラインイベントは location 不要 */
    public function test_store_does_not_require_location_for_online_event(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'title' => 'テストイベント',
            'description' => '説明',
            'category' => 1,
            'prefecture' => 'オンライン',
            'location' => null,
            'online_url' => 'https://zoom.us/j/123456789',
            'event_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(5)->addHours(2)->format('Y-m-d\TH:i'),
            'capacity' => 30,
        ]);

        $response->assertSessionDoesntHaveErrors(['location']);
    }
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_store_requires_online_url|test_store_does_not_require"
```

期待: FAIL

- [ ] **Step 3: StoreEventRequest を更新**

`app/Http/Requests/Event/StoreEventRequest.php` の `rules()` を以下に置き換え:

```php
public function rules(): array
{
    return [
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'category' => ['required', 'integer', Rule::enum(EventCategory::class)],
        'prefecture' => ['required', 'string', 'max:10'],
        'location' => [
            Rule::when(
                $this->prefecture !== 'オンライン',
                ['required', 'string', 'max:255'],
                ['nullable', 'string', 'max:255']
            ),
        ],
        'online_url' => [
            Rule::when(
                in_array($this->prefecture, ['オンライン', 'ハイブリッド'], true),
                ['required', 'url', 'max:2048'],
                ['nullable', 'url', 'max:2048']
            ),
        ],
        'online_password' => ['nullable', 'string', 'max:255'],
        'event_date' => ['required', 'date', 'after:now'],
        'end_date' => ['required', 'date', 'after:event_date'],
        'capacity' => ['required', 'integer', 'min:1'],
        'status' => ['nullable', 'integer', Rule::enum(EventStatus::class)],
    ];
}

/**
 * @return array<string, string>
 */
public function attributes(): array
{
    return [
        'online_url' => 'オンラインURL',
        'online_password' => 'パスワード',
    ];
}
```

- [ ] **Step 4: UpdateEventRequest を更新**

`app/Http/Requests/Event/UpdateEventRequest.php` の `rules()` と `attributes()` を StoreEventRequest と同様に更新（`status` の rule のみ異なる — `required` のまま）:

```php
public function rules(): array
{
    return [
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'category' => ['required', 'integer', Rule::enum(EventCategory::class)],
        'prefecture' => ['required', 'string', 'max:10'],
        'location' => [
            Rule::when(
                $this->prefecture !== 'オンライン',
                ['required', 'string', 'max:255'],
                ['nullable', 'string', 'max:255']
            ),
        ],
        'online_url' => [
            Rule::when(
                in_array($this->prefecture, ['オンライン', 'ハイブリッド'], true),
                ['required', 'url', 'max:2048'],
                ['nullable', 'url', 'max:2048']
            ),
        ],
        'online_password' => ['nullable', 'string', 'max:255'],
        'event_date' => ['required', 'date', 'after:now'],
        'end_date' => ['required', 'date', 'after:event_date'],
        'capacity' => ['required', 'integer', 'min:1'],
        'status' => ['required', 'integer', Rule::enum(EventStatus::class)],
    ];
}

/**
 * @return array<string, string>
 */
public function attributes(): array
{
    return [
        'online_url' => 'オンラインURL',
        'online_password' => 'パスワード',
    ];
}
```

- [ ] **Step 5: テストが通ることを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_store_requires_online_url|test_store_does_not_require"
```

期待: 全件 PASS

- [ ] **Step 6: EventTest 全体を実行して回帰がないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact tests/Feature/EventTest.php
```

期待: 全件 PASS

- [ ] **Step 7: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add app/Http/Requests/Event/StoreEventRequest.php app/Http/Requests/Event/UpdateEventRequest.php \
    tests/Feature/EventTest.php
git commit -m "feat: online_url/password・location のバリデーションを追加"
```

---

### Task 3: 申し込みフロー更新（サービス・コントローラー）+ テスト

**Files:**
- Modify: `app/Services/EventAttendanceService.php`
- Modify: `app/Http/Controllers/EventAttendanceController.php`
- Test: `tests/Feature/EventAttendanceTest.php`

- [ ] **Step 1: テストを書く**

`tests/Feature/EventAttendanceTest.php` の store セクション末尾に追加。

まず先頭 `use` に追加:
```php
use App\Enums\AttendanceMode;
```

テスト追加:

```php
    /** ハイブリッドイベントで attendance_mode を指定して申し込めば attendance_mode が保存される */
    public function test_store_saves_attendance_mode_for_hybrid_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->hybrid()->for($owner)->create([
            'status' => EventStatus::Published,
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event), [
                'attendance_mode' => 'online',
            ])
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame(AttendanceMode::Online, $attendance->attendance_mode);
    }

    /** ハイブリッドイベントで attendance_mode なしの申し込みは失敗する */
    public function test_store_fails_without_attendance_mode_for_hybrid_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->hybrid()->for($owner)->create([
            'status' => EventStatus::Published,
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasErrors(['attendance_mode']);
    }

    /** 対面イベントの申し込みは attendance_mode が in_person に自動セットされる */
    public function test_store_auto_sets_in_person_mode_for_in_person_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'status' => EventStatus::Published,
            'prefecture' => '東京都',
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasNoErrors();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertSame(AttendanceMode::InPerson, $attendance->attendance_mode);
    }

    /** オンラインイベントの申し込みは attendance_mode が online に自動セットされる */
    public function test_store_auto_sets_online_mode_for_online_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->online()->for($owner)->create([
            'status' => EventStatus::Published,
        ]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->from(route('events.show', $event))
            ->post(route('events.attendances.store', $event))
            ->assertSessionHasNoErrors();

        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $applicant->id)
            ->first();

        $this->assertSame(AttendanceMode::Online, $attendance->attendance_mode);
    }
```

- [ ] **Step 2: テストが失敗することを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_store_saves_attendance_mode|test_store_fails_without_attendance_mode|test_store_auto_sets"
```

期待: FAIL

- [ ] **Step 3: EventAttendanceController::store() を更新**

`app/Http/Controllers/EventAttendanceController.php` の use に追加:

```php
use App\Enums\AttendanceMode;
use Illuminate\Validation\Rule;
```

`store()` メソッドを以下に置き換え:

```php
/**
 * イベント申し込み
 */
public function store(Request $request, Event $event): RedirectResponse
{
    if ($event->status !== EventStatus::Published) {
        abort(Response::HTTP_NOT_FOUND);
    }

    /** @var User $user */
    $user = auth()->user();

    $attendanceMode = match ($event->prefecture) {
        'オンライン' => AttendanceMode::Online,
        'ハイブリッド' => AttendanceMode::from(
            $request->validate([
                'attendance_mode' => ['required', Rule::enum(AttendanceMode::class)],
            ])['attendance_mode']
        ),
        default => AttendanceMode::InPerson,
    };

    try {
        $result = $this->attendanceService->apply($event, $user, $attendanceMode);
    } catch (AttendanceException $e) {
        return back()->withErrors(['attendance' => $e->getMessage()]);
    }

    $message = $result === AttendanceStatus::Waitlisted
        ? 'キャンセル待ちに登録しました。'
        : '参加申し込みが完了しました。';

    return back()->with('success', $message);
}
```

- [ ] **Step 4: EventAttendanceService::apply() を更新**

`app/Services/EventAttendanceService.php` の use に追加:

```php
use App\Enums\AttendanceMode;
```

`apply()` シグネチャを変更:

```php
public function apply(Event $event, User $user, AttendanceMode $attendanceMode): AttendanceStatus
```

トランザクション内の `EventAttendance::create()` に `attendance_mode` を追加:

```php
$attendance = EventAttendance::create([
    'event_id' => $event->id,
    'user_id' => $user->id,
    'status' => AttendanceStatus::Applied,
    'applied_at' => now(),
    'attendance_mode' => $attendanceMode,
]);
```

既存レコード更新（`$existing->update([...])`）にも追加:

```php
$existing->update([
    'status' => AttendanceStatus::Applied,
    'applied_at' => now(),
    'cancelled_at' => null,
    'waitlisted_at' => null,
    'attendance_mode' => $attendanceMode,
]);
```

`waitlistApply()` の呼び出し元に `$attendanceMode` を渡す:

```php
$waitlistPosition = $this->waitlistApply($event, $user, $existing, $attendanceMode);
```

`waitlistApply()` シグネチャと中身を更新:

```php
private function waitlistApply(Event $event, User $user, ?EventAttendance $existing, AttendanceMode $attendanceMode): int
{
    $waitlistedCount = EventAttendance::query()
        ->where('event_id', $event->id)
        ->where('status', AttendanceStatus::Waitlisted)
        ->count();

    if ($waitlistedCount >= $event->capacity) {
        throw new AttendanceException('キャンセル待ちも満員です。');
    }

    if ($existing !== null) {
        $existing->update([
            'status' => AttendanceStatus::Waitlisted,
            'waitlisted_at' => now(),
            'applied_at' => null,
            'cancelled_at' => null,
            'attendance_mode' => $attendanceMode,
        ]);
        $attendance = $existing;
    } else {
        $attendance = EventAttendance::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => AttendanceStatus::Waitlisted,
            'waitlisted_at' => now(),
            'attendance_mode' => $attendanceMode,
        ]);
    }

    return EventAttendance::query()
        ->where('event_id', $event->id)
        ->where('status', AttendanceStatus::Waitlisted)
        ->where('waitlisted_at', '<=', $attendance->waitlisted_at)
        ->count();
}
```

- [ ] **Step 5: テストが通ることを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact --filter="test_store_saves_attendance_mode|test_store_fails_without_attendance_mode|test_store_auto_sets"
```

期待: 4件 PASS

- [ ] **Step 6: EventAttendanceTest 全体を実行**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact tests/Feature/EventAttendanceTest.php
```

期待: 全件 PASS

- [ ] **Step 7: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add app/Services/EventAttendanceService.php app/Http/Controllers/EventAttendanceController.php \
    tests/Feature/EventAttendanceTest.php
git commit -m "feat: 申し込み時に attendance_mode を保存するよう apply() を更新"
```

---

### Task 4: 作成・編集フォーム UI 更新

**Files:**
- Modify: `resources/views/events/create.blade.php`
- Modify: `resources/views/events/edit.blade.php`

このタスクにユニットテストはなし（バリデーションは Task 2 でカバー済み）。

- [ ] **Step 1: create.blade.php の prefecture 選択肢に「ハイブリッド」を追加**

`resources/views/events/create.blade.php` の prefecture の `@foreach` 行を変更:

変更前:
```blade
@foreach (['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県','オンライン'] as $pref)
```

変更後:
```blade
@foreach (['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県','オンライン','ハイブリッド'] as $pref)
```

- [ ] **Step 2: create.blade.php の location フィールドに id を追加し、online フィールドを追加**

`resources/views/events/create.blade.php` の `<!-- 会場 -->` セクション（location フィールド）を以下に置き換え:

```blade
                <!-- 会場 -->
                <div id="location-field">
                    <label for="location" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        会場 <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="{{ old('location') }}"
                        placeholder="例：渋谷ヒカリエ 8F"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('location') border-red-500 focus:ring-red-500 @enderror"
                    />
                    @error('location')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- オンラインURL・パスワード -->
                <div id="online-fields" class="space-y-4" style="display: none;">
                    <div>
                        <label for="online_url" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                            オンラインURL <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="url"
                            id="online_url"
                            name="online_url"
                            value="{{ old('online_url') }}"
                            placeholder="例：https://zoom.us/j/123456789"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('online_url') border-red-500 focus:ring-red-500 @enderror"
                        />
                        @error('online_url')
                            <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="online_password" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                            パスワード <span class="text-slate-400 font-normal text-xs">（任意）</span>
                        </label>
                        <input
                            type="text"
                            id="online_password"
                            name="online_password"
                            value="{{ old('online_password') }}"
                            placeholder="例：abc123"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('online_password') border-red-500 focus:ring-red-500 @enderror"
                        />
                        @error('online_password')
                            <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
```

- [ ] **Step 3: create.blade.php に JS を追加**

`create.blade.php` の `</form>` の直後（またはファイル末尾の `@endsection` の前）に追加:

```blade
<script>
(function () {
    const prefectureSelect = document.getElementById('prefecture');
    const locationField = document.getElementById('location-field');
    const onlineFields = document.getElementById('online-fields');

    const initialPref = prefectureSelect ? prefectureSelect.value : '';
    applyVisibility(initialPref);

    prefectureSelect && prefectureSelect.addEventListener('change', function () {
        applyVisibility(this.value);
    });

    function applyVisibility(pref) {
        const isOnline = pref === 'オンライン';
        const isHybrid = pref === 'ハイブリッド';

        locationField.style.display = isOnline ? 'none' : '';
        onlineFields.style.display = (isOnline || isHybrid) ? '' : 'none';
    }
}());
</script>
```

- [ ] **Step 4: edit.blade.php を同様に更新**

`edit.blade.php` は create.blade.php と同じ構造です。以下の変更を適用する:

1. prefecture の `@foreach` 末尾に `'ハイブリッド'` を追加（Step 1 と同じ変更）
2. ただし edit.blade.php では `old()` の代わりに `old('prefecture', $event->prefecture)` のようにデフォルト値がある点に注意してください

`edit.blade.php` を確認して、prefecture の option の selected 条件を確認してから変更する。

location フィールドを以下に置き換え（create と同じ構造だが `old('location', $event->location)` を使用）:

```blade
                <!-- 会場 -->
                <div id="location-field">
                    <label for="location" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                        会場 <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="{{ old('location', $event->location) }}"
                        placeholder="例：渋谷ヒカリエ 8F"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('location') border-red-500 focus:ring-red-500 @enderror"
                    />
                    @error('location')
                        <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- オンラインURL・パスワード -->
                <div id="online-fields" class="space-y-4" style="display: none;">
                    <div>
                        <label for="online_url" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                            オンラインURL <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="url"
                            id="online_url"
                            name="online_url"
                            value="{{ old('online_url', $event->online_url) }}"
                            placeholder="例：https://zoom.us/j/123456789"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('online_url') border-red-500 focus:ring-red-500 @enderror"
                        />
                        @error('online_url')
                            <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="online_password" class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-300">
                            パスワード <span class="text-slate-400 font-normal text-xs">（任意）</span>
                        </label>
                        <input
                            type="text"
                            id="online_password"
                            name="online_password"
                            value="{{ old('online_password', $event->online_password) }}"
                            placeholder="例：abc123"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition @error('online_password') border-red-500 focus:ring-red-500 @enderror"
                        />
                        @error('online_password')
                            <p class="text-red-500 text-sm mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
```

edit.blade.php の JS も create と同じ内容を追加する（`</form>` の後）。

- [ ] **Step 5: 全テストを実行して回帰がないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact
```

期待: 全件 PASS

- [ ] **Step 6: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add resources/views/events/create.blade.php resources/views/events/edit.blade.php
git commit -m "feat: イベント作成・編集フォームにオンラインURL・ハイブリッド対応を追加"
```

---

### Task 5: イベント詳細ページ UI 更新

**Files:**
- Modify: `resources/views/events/show.blade.php`

このタスクにユニットテストはなし（サービス・コントローラー層は Task 3 でカバー済み）。

- [ ] **Step 1: 申し込み済みバッジに attendance_mode を追加**

`resources/views/events/show.blade.php` の「参加申し込み済み」バッジ（`参加申し込み済み` というテキストを含む `<div>`）を探して更新する。

現在の該当箇所（約 210〜215 行）:
```blade
                                <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm font-semibold">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    参加申し込み済み
                                </div>
```

変更後:
```blade
                                <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm font-semibold">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    参加申し込み済み@if ($myAttendance->attendance_mode)（{{ $myAttendance->attendance_mode->label() }}）@endif
                                </div>
```

- [ ] **Step 2: ハイブリッドイベントの申し込みフォームに参加モード選択を追加**

`resources/views/events/show.blade.php` の申し込みフォーム（`@else` ブランチの `<form method="POST" action="{{ route('events.attendances.store', $event) }}">`）を探す（約 272〜281 行）。

対面のみ（`@else` ブランチ）とキャンセル待ち（`@elseif ($isFull)` ブランチ）の両フォームに参加モード選択を追加する。

**通常申し込みフォーム（`@else` ブランチ）の変更前**:
```blade
                        @else
                            <form method="POST" action="{{ route('events.attendances.store', $event) }}">
                                @csrf
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r {{ $style['gradient'] }} text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
                                    参加する
                                </button>
                            </form>
```

**変更後**:
```blade
                        @else
                            <form method="POST" action="{{ route('events.attendances.store', $event) }}">
                                @csrf
                                @if ($event->prefecture === 'ハイブリッド')
                                    <div class="mb-3 space-y-2">
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">参加方法を選択</p>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="in_person" required class="text-indigo-600"> 対面で参加
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="online" required> オンラインで参加
                                        </label>
                                    </div>
                                @elseif ($event->prefecture === 'オンライン')
                                    <input type="hidden" name="attendance_mode" value="online">
                                @else
                                    <input type="hidden" name="attendance_mode" value="in_person">
                                @endif
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r {{ $style['gradient'] }} text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
                                    参加する
                                </button>
                            </form>
```

**キャンセル待ちフォーム（`@elseif ($isFull)` ブランチ）**にも同様の参加モード選択を追加する:

変更前:
```blade
                        @elseif ($isFull)
                            <form method="POST" action="{{ route('events.attendances.store', $event) }}">
                                @csrf
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
                                    キャンセル待ちに登録する
                                </button>
                            </form>
```

変更後:
```blade
                        @elseif ($isFull)
                            <form method="POST" action="{{ route('events.attendances.store', $event) }}">
                                @csrf
                                @if ($event->prefecture === 'ハイブリッド')
                                    <div class="mb-3 space-y-2">
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">参加方法を選択</p>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="in_person" required class="text-indigo-600"> 対面で参加
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="radio" name="attendance_mode" value="online" required> オンラインで参加
                                        </label>
                                    </div>
                                @elseif ($event->prefecture === 'オンライン')
                                    <input type="hidden" name="attendance_mode" value="online">
                                @else
                                    <input type="hidden" name="attendance_mode" value="in_person">
                                @endif
                                <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
                                    キャンセル待ちに登録する
                                </button>
                            </form>
```

- [ ] **Step 3: オンライン参加情報カードを追加**

`resources/views/events/show.blade.php` の「開催場所」表示エリア（`$event->prefecture` / `$event->location` を表示している箇所、約 165〜166 行）の後に、オンライン参加情報カードを追加する。

現在の場所表示:
```blade
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $event->prefecture }}</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $event->location }}</p>
```

変更後（locationをonlineの場合はnull対応 + カード追加）:
```blade
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $event->prefecture }}</p>
                    @if ($event->location)
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $event->location }}</p>
                    @endif
                    @if (in_array($event->prefecture, ['オンライン', 'ハイブリッド']) && $myAttendance !== null)
                        <div class="mt-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 space-y-1">
                            <p class="text-xs font-semibold text-blue-700 dark:text-blue-400">オンライン参加情報</p>
                            <p class="text-sm text-blue-700 dark:text-blue-300 break-all">
                                URL: <a href="{{ $event->online_url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $event->online_url }}</a>
                            </p>
                            @if ($event->online_password)
                                <p class="text-sm text-blue-700 dark:text-blue-300">パスワード: {{ $event->online_password }}</p>
                            @endif
                        </div>
                    @endif
```

- [ ] **Step 4: 主催者向けに入室承認機能の案内を追加**

主催者用の「オンライン参加情報」表示部分（主催者がイベントを見ている場合）を追加する。

`show.blade.php` 内の主催者セクション（`Gate::check` や `$event->user_id === auth()->id()` で囲まれているエリア）内に、オンライン/ハイブリッドの場合のみ以下を追加する。具体的な行は edit ボタンや削除ボタンが含まれるセクションを参照すること。

```blade
@if (in_array($event->prefecture, ['オンライン', 'ハイブリッド']))
    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
        ⚠ 参加者の入室承認機能（待機室・ロビー等）を有効にすることを推奨します。
    </div>
@endif
```

- [ ] **Step 5: 主催者の参加者一覧に参加モードを表示**

`show.blade.php` の主催者参加者一覧（Applied 一覧の `@forelse` ループ）内、名前の右に参加モードを追加する。

現在の該当箇所（約 341 行）:
```blade
                                <span class="flex-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $attendance->user->name }}</span>
```

変更後:
```blade
                                <span class="flex-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $attendance->user->name }}
                                    @if ($attendance->attendance_mode)
                                        <span class="ml-1 text-xs text-slate-500 dark:text-slate-400">（{{ $attendance->attendance_mode->label() }}）</span>
                                    @endif
                                </span>
```

- [ ] **Step 6: 全テストを実行して回帰がないことを確認**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && php artisan test --compact
```

期待: 全件 PASS

- [ ] **Step 7: Pint でフォーマット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass && vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: コミット**

```bash
cd /Users/hans/Desktop/connpass_copy/ai_connpass
git add resources/views/events/show.blade.php
git commit -m "feat: イベント詳細ページにオンライン参加情報・参加モード表示を追加"
```
