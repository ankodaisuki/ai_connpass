# V5 合同主催機能 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **前提（重要）:** 本計画は ADR-0001=C案 / ADR-0002=B案 / ADR-0003=B案 を実装範囲とする。**ただし3本のADRは現時点で `Proposed`（レビュー待ち）であり、実装着手は3本が `Accepted` になってからとする。** 本ドキュメントは Accepted 前の準備としての計画である。

**Goal:** 1つのイベントを複数人で主催できる「合同主催」機能を、個別招待・承認制・2階層権限（オーナー／合同主催者）で提供する。

**Architecture:** 既存の単一オーナー（`events.user_id`）は据え置き、**合同主催者だけを中間テーブル `event_organizers`（招待状態つき）で多対多管理する**。権限は `EventPolicy` に集約し「オーナーのみ」と「主催者（オーナー＋承諾済み合同主催者）」を区別する。招待は既存メール基盤を再利用した承認制（pending→accepted/declined）。オーナー離脱時は「黙って消さない」方針で、承諾済み合同主催者がいれば自動でオーナーを引き継ぎ、いなければイベントを中止扱いにする。

**Tech Stack:** Laravel 13 / PHP 8.4 / Eloquent / Blade / PHPUnit（クラス記法・既存テストに準拠）/ Mailable / Tailwind v4

---

## 設計の前提と命名（全タスク共通・先に固定）

実装中に名前がブレないよう、ここで確定する。後続タスクはこの名前を厳守すること。

### データモデル

- **`events.user_id` は引き続き「現在のオーナー」を表す唯一の正**（既存コードが多用しているため変更しない）。
- 合同主催者は中間テーブル **`event_organizers`** で管理する。オーナーはこの表に入れない（owner は `user_id` で表現）。
- 招待状態の enum: `App\Enums\OrganizerInvitationStatus`（`Pending=0` / `Accepted=1` / `Declined=2`）。

### `event_organizers` テーブル列

| 列 | 型 | 説明 |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | FK→events, cascadeOnDelete | |
| `user_id` | FK→users, cascadeOnDelete | 招待された合同主催者 |
| `status` | unsignedTinyInteger | `OrganizerInvitationStatus`。デフォルト `0`(Pending) |
| `invited_at` | timestamp | 招待日時 |
| `responded_at` | timestamp nullable | 承諾／辞退した日時 |
| `created_at`/`updated_at` | timestamps | |

制約: `unique(event_id, user_id)`（同じ人を二重招待しない）。ソフトデリートは使わない（除名＝行を物理削除）。

### モデル / メソッド命名（厳守）

- モデル `App\Models\EventOrganizer`
- `Event` の追加リレーション/メソッド:
  - `eventOrganizers(): HasMany<EventOrganizer>` — 全状態の招待レコード
  - `acceptedCoOrganizers(): BelongsToMany<User>` — 承諾済みの合同主催者（公開表示・権限用）
  - `isOwner(User $user): bool`
  - `isAcceptedCoOrganizer(User $user): bool`
  - `isOrganizer(User $user): bool` — `isOwner($user) || isAcceptedCoOrganizer($user)`
- `EventPolicy` メソッド: `update` / `delete` / `updateAttendance`（既存）＋ `inviteOrganizer` / `removeOrganizer` / `transferOwnership`（新規）

### 権限表（ADR-0002 B案）

| 操作 | Policy メソッド | 許可 |
|---|---|---|
| 編集 | `update` | オーナー＋承諾済み合同主催者 |
| 出欠記録 | `updateAttendance` | オーナー＋承諾済み合同主催者 |
| 参加者一覧閲覧 | （コントローラ判定） | オーナー＋承諾済み合同主催者 |
| 削除 | `delete` | オーナーのみ |
| 合同主催者の招待 | `inviteOrganizer` | オーナーのみ |
| 合同主催者の除名 | `removeOrganizer` | オーナーのみ |
| オーナー移譲 | `transferOwnership` | オーナーのみ |

### 招待・公開表示（ADR-0003 B案）

- 招待は承認制。招待時に被招待者へ `OrganizerInvitedMail` を送る。
- 公開ページ（イベント詳細）には **`status=Accepted` の合同主催者のみ** を表示。`Pending`/`Declined` は出さない。

### オーナー離脱・移譲（ADR-0002 論点5＋手動移譲）

- **自動引き継ぎ:** オーナー退会時、対象イベントに承諾済み合同主催者がいれば、**最も早く承諾した1人**へオーナーを移す（`events.user_id` を更新し、その人の `event_organizers` 行を削除）。
- **中止:** 承諾済み合同主催者がいなければイベントを中止扱い（Private化＋ソフトデリート＋参加者へ中止メール）。
- **手動移譲:** オーナーが任意で承諾済み合同主催者の1人にオーナーを譲れる。**旧オーナーは承諾済みの合同主催者として残す**（`event_organizers` に Accepted 行を作る）。

---

## File Structure

**新規作成**
- `app/Enums/OrganizerInvitationStatus.php` — 招待状態 enum
- `database/migrations/XXXX_create_event_organizers_table.php` — 中間テーブル
- `app/Models/EventOrganizer.php` — 中間テーブルのモデル
- `database/factories/EventOrganizerFactory.php` — テスト用ファクトリ
- `app/Mail/OrganizerInvitedMail.php` — 招待通知メール
- `resources/views/mail/organizer-invited.blade.php` — 招待メール本文
- `app/Http/Controllers/EventOrganizerController.php` — 招待/承諾/辞退/除名
- `app/Http/Controllers/EventOwnershipController.php` — 手動オーナー移譲
- `app/Http/Controllers/MyOrganizerInvitationController.php` — 被招待者の招待一覧
- `resources/views/my/organizer-invitations.blade.php` — 招待一覧画面
- `app/Services/EventCancellationService.php` — 中止処理（既存destroyから抽出・再利用）
- `app/Services/EventOwnershipService.php` — オーナー離脱時の引き継ぎ/中止判断

**修正**
- `app/Policies/EventPolicy.php` — 権限を主催者ベースに拡張＋新メソッド
- `app/Models/Event.php` — リレーション＆判定メソッド追加
- `app/Http/Controllers/EventController.php` — `destroy` を `EventCancellationService` 利用に置換
- `app/Http/Controllers/EventAttendanceController.php` — 出欠/参加者閲覧の権限を主催者ベースに
- `app/Observers/UserObserver.php` — 退会時にオーナー離脱処理を呼ぶ
- `routes/web.php` — 招待/承諾/辞退/除名/移譲/招待一覧のルート
- `resources/views/events/show.blade.php` — 合同主催者の公開表示＋主催者向け管理UI（権限で出し分け）

---

## Task 1: 招待状態 enum

**Files:**
- Create: `app/Enums/OrganizerInvitationStatus.php`
- Test: `tests/Unit/OrganizerInvitationStatusTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Enums\OrganizerInvitationStatus;
use PHPUnit\Framework\TestCase;

class OrganizerInvitationStatusTest extends TestCase
{
    public function test_has_expected_int_values(): void
    {
        $this->assertSame(0, OrganizerInvitationStatus::Pending->value);
        $this->assertSame(1, OrganizerInvitationStatus::Accepted->value);
        $this->assertSame(2, OrganizerInvitationStatus::Declined->value);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OrganizerInvitationStatusTest`
Expected: FAIL（`OrganizerInvitationStatus` が存在しない）

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Enums;

/**
 * 合同主催者の招待状態
 */
enum OrganizerInvitationStatus: int
{
    case Pending = 0;
    case Accepted = 1;
    case Declined = 2;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=OrganizerInvitationStatusTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Enums/OrganizerInvitationStatus.php tests/Unit/OrganizerInvitationStatusTest.php
git commit -m "feat: 合同主催者の招待状態 enum を追加"
```

---

## Task 2: `event_organizers` テーブル・モデル・ファクトリ

**Files:**
- Create: `database/migrations/XXXX_create_event_organizers_table.php`（`php artisan make:migration` で生成）
- Create: `app/Models/EventOrganizer.php`
- Create: `database/factories/EventOrganizerFactory.php`
- Test: `tests/Unit/EventOrganizerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOrganizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_event_and_user(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        $organizer = EventOrganizer::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($organizer->event->is($event));
        $this->assertTrue($organizer->user->is($user));
    }

    public function test_status_is_cast_to_enum(): void
    {
        $organizer = EventOrganizer::factory()->create([
            'status' => OrganizerInvitationStatus::Accepted,
        ]);

        $this->assertSame(OrganizerInvitationStatus::Accepted, $organizer->fresh()->status);
    }

    public function test_factory_accepted_state(): void
    {
        $organizer = EventOrganizer::factory()->accepted()->create();

        $this->assertSame(OrganizerInvitationStatus::Accepted, $organizer->status);
        $this->assertNotNull($organizer->responded_at);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventOrganizerTest`
Expected: FAIL（テーブル・モデルが無い）

- [ ] **Step 3: Create migration**

`php artisan make:migration create_event_organizers_table --no-interaction` を実行し、生成ファイルの中身を次にする。

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_organizers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('invited_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_organizers');
    }
};
```

- [ ] **Step 4: Create the model**

`app/Models/EventOrganizer.php`:

```php
<?php

namespace App\Models;

use App\Enums\OrganizerInvitationStatus;
use Database\Factories\EventOrganizerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'user_id',
    'status',
    'invited_at',
    'responded_at',
])]
class EventOrganizer extends Model
{
    /** @use HasFactory<EventOrganizerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizerInvitationStatus::class,
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * 紐づくイベント
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 招待された合同主催者
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Create the factory**

`database/factories/EventOrganizerFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventOrganizer>
 */
class EventOrganizerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => OrganizerInvitationStatus::Pending,
            'invited_at' => now(),
            'responded_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizerInvitationStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizerInvitationStatus::Declined,
            'responded_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventOrganizerTest`
Expected: PASS（3件）

- [ ] **Step 7: Commit**

```bash
git add database/migrations/*_create_event_organizers_table.php app/Models/EventOrganizer.php database/factories/EventOrganizerFactory.php tests/Unit/EventOrganizerTest.php
git commit -m "feat: event_organizers テーブル・モデル・ファクトリを追加"
```

---

## Task 3: Event のリレーション＆判定メソッド

**Files:**
- Modify: `app/Models/Event.php`
- Test: `tests/Unit/EventOrganizerRelationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOrganizerRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_co_organizers_only_includes_accepted(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->create();
        $pending = User::factory()->create();

        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $accepted->id]);
        EventOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id]);

        $ids = $event->acceptedCoOrganizers->pluck('id');

        $this->assertTrue($ids->contains($accepted->id));
        $this->assertFalse($ids->contains($pending->id));
    }

    public function test_is_owner(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($event->isOwner($owner));
        $this->assertFalse($event->isOwner(User::factory()->create()));
    }

    public function test_is_organizer_includes_owner_and_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $pending = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);
        EventOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id]);

        $this->assertTrue($event->isOrganizer($owner));
        $this->assertTrue($event->isOrganizer($coOrganizer));
        $this->assertFalse($event->isOrganizer($pending));
        $this->assertFalse($event->isAcceptedCoOrganizer($owner)); // オーナーは合同主催者ではない
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventOrganizerRelationTest`
Expected: FAIL（メソッド未定義）

- [ ] **Step 3: Add relations and methods to `app/Models/Event.php`**

`use` 句に追加:

```php
use App\Enums\OrganizerInvitationStatus;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

`waitlistAttendances()` メソッドの後ろ（クラス末尾の `}` の直前）に追加:

```php
    /**
     * このイベントの合同主催者の招待レコード（全状態）
     *
     * @return HasMany<EventOrganizer, $this>
     */
    public function eventOrganizers(): HasMany
    {
        return $this->hasMany(EventOrganizer::class);
    }

    /**
     * 承諾済みの合同主催者（公開表示・権限判定に使用）
     *
     * @return BelongsToMany<User, $this>
     */
    public function acceptedCoOrganizers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_organizers')
            ->wherePivot('status', OrganizerInvitationStatus::Accepted->value)
            ->withPivot(['status', 'invited_at', 'responded_at'])
            ->withTimestamps();
    }

    /**
     * 指定ユーザーがこのイベントのオーナー（作成者）か
     */
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * 指定ユーザーが承諾済みの合同主催者か
     */
    public function isAcceptedCoOrganizer(User $user): bool
    {
        return $this->eventOrganizers()
            ->where('user_id', $user->id)
            ->where('status', OrganizerInvitationStatus::Accepted)
            ->exists();
    }

    /**
     * 指定ユーザーが主催者（オーナー or 承諾済み合同主催者）か
     */
    public function isOrganizer(User $user): bool
    {
        return $this->isOwner($user) || $this->isAcceptedCoOrganizer($user);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventOrganizerRelationTest`
Expected: PASS（3件）

- [ ] **Step 5: Commit**

```bash
git add app/Models/Event.php tests/Unit/EventOrganizerRelationTest.php
git commit -m "feat: Event に合同主催者リレーションと主催者判定メソッドを追加"
```

---

## Task 4: EventPolicy を主催者ベースに拡張

**Files:**
- Modify: `app/Policies/EventPolicy.php`
- Test: `tests/Feature/EventOrganizerPolicyTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOrganizerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): array
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        return [$event, $owner, $coOrganizer, $stranger];
    }

    public function test_update_allowed_for_owner_and_accepted_co_organizer(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        $this->assertTrue($owner->can('update', $event));
        $this->assertTrue($coOrganizer->can('update', $event));
        $this->assertFalse($stranger->can('update', $event));
    }

    public function test_update_attendance_allowed_for_owner_and_accepted_co_organizer(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        $this->assertTrue($owner->can('updateAttendance', $event));
        $this->assertTrue($coOrganizer->can('updateAttendance', $event));
        $this->assertFalse($stranger->can('updateAttendance', $event));
    }

    public function test_delete_allowed_for_owner_only(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        $this->assertTrue($owner->can('delete', $event));
        $this->assertFalse($coOrganizer->can('delete', $event));
        $this->assertFalse($stranger->can('delete', $event));
    }

    public function test_invite_remove_transfer_allowed_for_owner_only(): void
    {
        [$event, $owner, $coOrganizer, $stranger] = $this->makeEvent();

        foreach (['inviteOrganizer', 'removeOrganizer', 'transferOwnership'] as $ability) {
            $this->assertTrue($owner->can($ability, $event), $ability);
            $this->assertFalse($coOrganizer->can($ability, $event), $ability);
            $this->assertFalse($stranger->can($ability, $event), $ability);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventOrganizerPolicyTest`
Expected: FAIL（合同主催者が update/updateAttendance できず、新メソッドも無い）

- [ ] **Step 3: Replace `app/Policies/EventPolicy.php` body**

```php
<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * イベントに対するアクセス制御
 *
 * 閲覧系 (view/viewAny) はコントローラ内で判定するため Policy には含めない。
 */
class EventPolicy
{
    /**
     * 更新はオーナーまたは承諾済み合同主催者に許可
     */
    public function update(User $user, Event $event): bool
    {
        return $event->isOrganizer($user);
    }

    /**
     * 削除はオーナーのみ許可
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }

    /**
     * 出欠記録はオーナーまたは承諾済み合同主催者に許可
     */
    public function updateAttendance(User $user, Event $event): bool
    {
        return $event->isOrganizer($user);
    }

    /**
     * 合同主催者の招待はオーナーのみ許可
     */
    public function inviteOrganizer(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }

    /**
     * 合同主催者の除名はオーナーのみ許可
     */
    public function removeOrganizer(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }

    /**
     * オーナーの移譲はオーナーのみ許可
     */
    public function transferOwnership(User $user, Event $event): bool
    {
        return $event->isOwner($user);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventOrganizerPolicyTest`
Expected: PASS（4件）

- [ ] **Step 5: Regression check（既存のEvent編集/削除テスト）**

Run: `php artisan test --compact --filter=EventTest`
Expected: PASS（既存のオーナー権限テストが壊れていないこと）

- [ ] **Step 6: Commit**

```bash
git add app/Policies/EventPolicy.php tests/Feature/EventOrganizerPolicyTest.php
git commit -m "feat: EventPolicy を主催者ベースに拡張し招待/除名/移譲権限を追加"
```

---

## Task 5: 招待通知メール

**Files:**
- Create: `app/Mail/OrganizerInvitedMail.php`
- Create: `resources/views/mail/organizer-invited.blade.php`
- Test: `tests/Feature/OrganizerInvitedMailTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Mail\OrganizerInvitedMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerInvitedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_renders_with_event_title_and_inviter(): void
    {
        $inviter = User::factory()->create(['name' => '招待 太郎']);
        $event = Event::factory()->create(['title' => 'Laravel 勉強会 #5', 'user_id' => $inviter->id]);

        $mail = new OrganizerInvitedMail($event, $inviter);
        $rendered = $mail->render();

        $this->assertStringContainsString('Laravel 勉強会 #5', $rendered);
        $this->assertStringContainsString('招待 太郎', $rendered);
        $this->assertStringContainsString(route('my.organizer-invitations'), $rendered);
        $this->assertStringContainsString('合同主催', $mail->envelope()->subject);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OrganizerInvitedMailTest`
Expected: FAIL（Mailクラス・ルート未定義）。※`route('my.organizer-invitations')` は Task 9 で定義するが、本テストを通すには先にルートが必要。Task 9 のルート定義を先に入れてもよいが、ここでは **Step 3 でルートも併せて追加** する。

- [ ] **Step 3: Add the route placeholder（招待一覧ルート）**

`routes/web.php` の `Route::middleware('auth')->group(function () {` ブロック内、`my/attendances` 行の下に追加:

```php
    Route::get('my/organizer-invitations', [\App\Http\Controllers\MyOrganizerInvitationController::class, 'index'])->name('my.organizer-invitations');
```

（コントローラ実体は Task 9 で作成。ルート名解決だけ先に通す。）

- [ ] **Step 4: Create the Mailable**

`app/Mail/OrganizerInvitedMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizerInvitedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly User $inviter,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【合同主催のお願い】{$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.organizer-invited');
    }
}
```

- [ ] **Step 5: Create the mail view**

`resources/views/mail/organizer-invited.blade.php`:

```blade
<p>{{ $inviter->name }} さんから、イベント「{{ $event->title }}」の合同主催に招待されました。</p>

<p>下記のページから、招待を承諾または辞退できます。</p>

<p><a href="{{ route('my.organizer-invitations') }}">{{ route('my.organizer-invitations') }}</a></p>

<p>承諾すると、あなたはこのイベントの合同主催者として、イベントの編集や出欠記録ができるようになります。承諾するまで、あなたの名前が公開ページに表示されることはありません。</p>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=OrganizerInvitedMailTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Mail/OrganizerInvitedMail.php resources/views/mail/organizer-invited.blade.php routes/web.php tests/Feature/OrganizerInvitedMailTest.php
git commit -m "feat: 合同主催の招待通知メールを追加"
```

---

## Task 6: 合同主催者の招待（store）

**Files:**
- Create: `app/Http/Controllers/EventOrganizerController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrganizerInvitationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\OrganizerInvitationStatus;
use App\Mail\OrganizerInvitedMail;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrganizerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_existing_user_by_email(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->post(route('events.organizers.store', $event), [
            'email' => 'invitee@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_organizers', [
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending->value,
        ]);
        Mail::assertSent(OrganizerInvitedMail::class);
    }

    public function test_non_owner_cannot_invite(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $this->actingAs($coOrganizer)
            ->post(route('events.organizers.store', $event), ['email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_inviting_unknown_email_returns_validation_error(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.organizers.store', $event), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('event_organizers', 0);
    }

    public function test_cannot_invite_the_owner_themselves(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.organizers.store', $event), ['email' => 'owner@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('event_organizers', 0);
    }

    public function test_cannot_invite_same_user_twice(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'dup@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $invitee->id]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.organizers.store', $event), ['email' => 'dup@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('event_organizers', 1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OrganizerInvitationTest`
Expected: FAIL（ルート・コントローラ未定義）

- [ ] **Step 3: Add routes**

`routes/web.php` の `auth` グループ内、イベント関連ルートの並びに追加:

```php
    Route::post('events/{event}/organizers', [\App\Http\Controllers\EventOrganizerController::class, 'store'])->name('events.organizers.store');
```

ファイル冒頭の `use` 群に追加:

```php
use App\Http\Controllers\EventOrganizerController;
```

（追加後は `[\App\Http\...]` のフルパス記法ではなく `[EventOrganizerController::class, 'store']` に置き換えてよい。既存ルートの記法に合わせること。）

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/EventOrganizerController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\OrganizerInvitationStatus;
use App\Mail\OrganizerInvitedMail;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EventOrganizerController extends Controller
{
    use AuthorizesRequests;

    /**
     * 合同主催者をメールアドレスで招待する（承認制・Pending で作成）
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('inviteOrganizer', $event);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $invitee = User::query()->where('email', $validated['email'])->first();

        if ($invitee === null) {
            throw ValidationException::withMessages([
                'email' => 'そのメールアドレスのユーザーが見つかりません。',
            ]);
        }

        if ($event->isOwner($invitee)) {
            throw ValidationException::withMessages([
                'email' => 'オーナー自身を合同主催者に招待することはできません。',
            ]);
        }

        if ($event->eventOrganizers()->where('user_id', $invitee->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'このユーザーはすでに招待済みです。',
            ]);
        }

        $event->eventOrganizers()->create([
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
            'invited_at' => now(),
        ]);

        /** @var User $owner */
        $owner = $request->user();

        try {
            Mail::to($invitee->email)->send(new OrganizerInvitedMail($event, $owner));
        } catch (\Throwable $e) {
            Log::warning('合同主催の招待メール送信に失敗', [
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('events.show', $event)->with('success', '合同主催者を招待しました。');
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OrganizerInvitationTest`
Expected: PASS（5件）

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EventOrganizerController.php routes/web.php tests/Feature/OrganizerInvitationTest.php
git commit -m "feat: 合同主催者の招待（承認制・メール通知）を追加"
```

---

## Task 7: 招待の承諾・辞退（accept / decline）

**Files:**
- Modify: `app/Http/Controllers/EventOrganizerController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrganizerInvitationResponseTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerInvitationResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitee_can_accept(): void
    {
        $invitee = User::factory()->create();
        $event = Event::factory()->create();
        $invitation = EventOrganizer::factory()->create([
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($invitee)
            ->patch(route('organizer-invitations.accept', $invitation))
            ->assertRedirect(route('my.organizer-invitations'));

        $invitation->refresh();
        $this->assertSame(OrganizerInvitationStatus::Accepted, $invitation->status);
        $this->assertNotNull($invitation->responded_at);
    }

    public function test_invitee_can_decline(): void
    {
        $invitee = User::factory()->create();
        $invitation = EventOrganizer::factory()->create([
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($invitee)
            ->patch(route('organizer-invitations.decline', $invitation))
            ->assertRedirect(route('my.organizer-invitations'));

        $this->assertSame(OrganizerInvitationStatus::Declined, $invitation->fresh()->status);
    }

    public function test_other_user_cannot_respond_to_invitation(): void
    {
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $invitation = EventOrganizer::factory()->create([
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($stranger)
            ->patch(route('organizer-invitations.accept', $invitation))
            ->assertForbidden();

        $this->assertSame(OrganizerInvitationStatus::Pending, $invitation->fresh()->status);
    }

    public function test_cannot_respond_to_already_resolved_invitation(): void
    {
        $invitee = User::factory()->create();
        $invitation = EventOrganizer::factory()->accepted()->create(['user_id' => $invitee->id]);

        $this->actingAs($invitee)
            ->patch(route('organizer-invitations.decline', $invitation))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OrganizerInvitationResponseTest`
Expected: FAIL（ルート・メソッド未定義）

- [ ] **Step 3: Add routes**

`routes/web.php` の `auth` グループ内に追加:

```php
    Route::patch('organizer-invitations/{eventOrganizer}/accept', [EventOrganizerController::class, 'accept'])->name('organizer-invitations.accept');
    Route::patch('organizer-invitations/{eventOrganizer}/decline', [EventOrganizerController::class, 'decline'])->name('organizer-invitations.decline');
```

- [ ] **Step 4: Add accept / decline methods to `EventOrganizerController`**

`use` に追加:

```php
use Symfony\Component\HttpFoundation\Response;
```

メソッド追加:

```php
    /**
     * 被招待者が招待を承諾する
     */
    public function accept(EventOrganizer $eventOrganizer): RedirectResponse
    {
        $this->authorizeResponse($eventOrganizer);

        $eventOrganizer->update([
            'status' => OrganizerInvitationStatus::Accepted,
            'responded_at' => now(),
        ]);

        return redirect()->route('my.organizer-invitations')->with('success', '合同主催の招待を承諾しました。');
    }

    /**
     * 被招待者が招待を辞退する
     */
    public function decline(EventOrganizer $eventOrganizer): RedirectResponse
    {
        $this->authorizeResponse($eventOrganizer);

        $eventOrganizer->update([
            'status' => OrganizerInvitationStatus::Declined,
            'responded_at' => now(),
        ]);

        return redirect()->route('my.organizer-invitations')->with('success', '合同主催の招待を辞退しました。');
    }

    /**
     * 招待に応答できるのは「被招待者本人」かつ「Pending のまま」の場合のみ
     */
    private function authorizeResponse(EventOrganizer $eventOrganizer): void
    {
        abort_unless(
            $eventOrganizer->user_id === auth()->id()
                && $eventOrganizer->status === OrganizerInvitationStatus::Pending,
            Response::HTTP_FORBIDDEN,
        );
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OrganizerInvitationResponseTest`
Expected: PASS（4件）

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EventOrganizerController.php routes/web.php tests/Feature/OrganizerInvitationResponseTest.php
git commit -m "feat: 合同主催の招待を承諾・辞退する機能を追加"
```

---

## Task 8: 合同主催者の除名（destroy）

**Files:**
- Modify: `app/Http/Controllers/EventOrganizerController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrganizerRemovalTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_remove_co_organizer(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $organizer = EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('events.organizers.destroy', [$event, $organizer]))
            ->assertRedirect(route('events.show', $event));

        $this->assertDatabaseMissing('event_organizers', ['id' => $organizer->id]);
    }

    public function test_co_organizer_cannot_remove_others(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $organizer = EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($coOrganizer)
            ->delete(route('events.organizers.destroy', [$event, $organizer]))
            ->assertForbidden();

        $this->assertDatabaseHas('event_organizers', ['id' => $organizer->id]);
    }

    public function test_cannot_remove_organizer_belonging_to_another_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $otherEvent = Event::factory()->create(['user_id' => $owner->id]);
        $organizer = EventOrganizer::factory()->accepted()->create(['event_id' => $otherEvent->id]);

        $this->actingAs($owner)
            ->delete(route('events.organizers.destroy', [$event, $organizer]))
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OrganizerRemovalTest`
Expected: FAIL（ルート・メソッド未定義）

- [ ] **Step 3: Add route（scoped binding でイベント所属を強制）**

`routes/web.php` の `auth` グループ内に追加:

```php
    Route::delete('events/{event}/organizers/{eventOrganizer}', [EventOrganizerController::class, 'destroy'])
        ->scopeBindings()
        ->name('events.organizers.destroy');
```

- [ ] **Step 4: Add destroy method to `EventOrganizerController`**

```php
    /**
     * 合同主催者を除名する（招待レコードを削除）
     */
    public function destroy(Event $event, EventOrganizer $eventOrganizer): RedirectResponse
    {
        $this->authorize('removeOrganizer', $event);

        $eventOrganizer->delete();

        return redirect()->route('events.show', $event)->with('success', '合同主催者を外しました。');
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OrganizerRemovalTest`
Expected: PASS（3件）

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EventOrganizerController.php routes/web.php tests/Feature/OrganizerRemovalTest.php
git commit -m "feat: オーナーが合同主催者を除名する機能を追加"
```

---

## Task 9: 被招待者の招待一覧ページ

**Files:**
- Create: `app/Http/Controllers/MyOrganizerInvitationController.php`
- Create: `resources/views/my/organizer-invitations.blade.php`
- Test: `tests/Feature/MyOrganizerInvitationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOrganizerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_my_pending_invitations(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $pendingEvent = Event::factory()->create(['title' => 'pending-event']);
        EventOrganizer::factory()->create(['event_id' => $pendingEvent->id, 'user_id' => $me->id]);

        $acceptedEvent = Event::factory()->create(['title' => 'accepted-event']);
        EventOrganizer::factory()->accepted()->create(['event_id' => $acceptedEvent->id, 'user_id' => $me->id]);

        $othersEvent = Event::factory()->create(['title' => 'others-event']);
        EventOrganizer::factory()->create(['event_id' => $othersEvent->id, 'user_id' => $other->id]);

        $response = $this->actingAs($me)->get(route('my.organizer-invitations'));

        $response->assertOk();
        $response->assertSee('pending-event');
        $response->assertDontSee('accepted-event');
        $response->assertDontSee('others-event');
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('my.organizer-invitations'))->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MyOrganizerInvitationTest`
Expected: FAIL（コントローラ・ビュー未定義）

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/MyOrganizerInvitationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\OrganizerInvitationStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;

class MyOrganizerInvitationController extends Controller
{
    /**
     * 自分宛ての保留中（Pending）の合同主催招待一覧
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $invitations = $user->organizerInvitations()
            ->where('status', OrganizerInvitationStatus::Pending)
            ->with('event.user')
            ->latest('invited_at')
            ->get();

        return view('my.organizer-invitations', compact('invitations'));
    }
}
```

- [ ] **Step 4: Add `organizerInvitations` relation to `app/Models/User.php`**

`use` に追加:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```
（既存で未import の場合のみ。`HasMany` は既に import 済みなので、その場合は変更不要。）

`hasGoogleCalendarConnected()` メソッドの下に追加:

```php
    /**
     * このユーザー宛ての合同主催の招待一覧
     *
     * @return HasMany<EventOrganizer, $this>
     */
    public function organizerInvitations(): HasMany
    {
        return $this->hasMany(EventOrganizer::class);
    }
```

- [ ] **Step 5: Create the view**

`resources/views/my/organizer-invitations.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="mb-6 text-xl font-bold text-slate-800 dark:text-slate-100">合同主催の招待</h1>

        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                {{ session('success') }}
            </div>
        @endif

        @forelse ($invitations as $invitation)
            <div class="mb-3 flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                <div>
                    <a href="{{ route('events.show', $invitation->event) }}" class="font-medium text-slate-800 hover:underline dark:text-slate-100">
                        {{ $invitation->event->title }}
                    </a>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $invitation->event->user->name }} さんからの招待
                    </p>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('organizer-invitations.accept', $invitation) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                            承諾
                        </button>
                    </form>
                    <form method="POST" action="{{ route('organizer-invitations.decline', $invitation) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">
                            辞退
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500 dark:text-slate-400">現在、保留中の招待はありません。</p>
        @endforelse
    </div>
</x-app-layout>
```

> **注意:** レイアウトコンポーネント名（`<x-app-layout>`）は既存の `resources/views/my/attendances.blade.php` 等を開いて実際の名前に合わせること。異なる場合はそれに置換する。

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter=MyOrganizerInvitationTest`
Expected: PASS（2件）

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/MyOrganizerInvitationController.php resources/views/my/organizer-invitations.blade.php app/Models/User.php tests/Feature/MyOrganizerInvitationTest.php
git commit -m "feat: 被招待者の合同主催招待一覧ページを追加"
```

---

## Task 10: 公開ページに合同主催者を表示（ADR-0003 論点4）

**Files:**
- Modify: `resources/views/events/show.blade.php`
- Modify: `app/Http/Controllers/EventController.php`（`show` で `acceptedCoOrganizers` を eager load）
- Test: `tests/Feature/EventOrganizerDisplayTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOrganizerDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_co_organizer_is_shown_on_public_page(): void
    {
        $owner = User::factory()->create(['name' => 'オーナー花子']);
        $accepted = User::factory()->create(['name' => '承諾ジョン']);
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $accepted->id]);

        $response = $this->get(route('events.show', $event));

        $response->assertSee('オーナー花子');
        $response->assertSee('承諾ジョン');
    }

    public function test_pending_and_declined_are_hidden_on_public_page(): void
    {
        $owner = User::factory()->create();
        $pending = User::factory()->create(['name' => '保留ペンディング']);
        $declined = User::factory()->create(['name' => '辞退デクライン']);
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id]);
        EventOrganizer::factory()->declined()->create(['event_id' => $event->id, 'user_id' => $declined->id]);

        $response = $this->get(route('events.show', $event));

        $response->assertDontSee('保留ペンディング');
        $response->assertDontSee('辞退デクライン');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventOrganizerDisplayTest`
Expected: FAIL（合同主催者が表示されない）

- [ ] **Step 3: Eager load in `EventController::show`**

`app/Http/Controllers/EventController.php` の `show` メソッド内、既存の `$event->load([...])` の配列に `'acceptedCoOrganizers'` を追加する。具体的には:

```php
        $event->load(['user', 'acceptedCoOrganizers', 'attendances' => function ($query) {
            $query->where('status', AttendanceStatus::Applied)
                ->with('user')
                ->orderBy('applied_at', 'asc');
        }]);
```

- [ ] **Step 4: Update the view**

`resources/views/events/show.blade.php` の主催表示部分（`<span class="text-sm">主催: {{ $event->user->name }}</span>` の箇所、おおよそ81-83行目）を次に置換する:

```blade
                <span class="text-sm">主催: {{ $event->user->name }}</span>
                @foreach ($event->acceptedCoOrganizers as $coOrganizer)
                    <span class="text-sm text-slate-500 dark:text-slate-400">/ {{ $coOrganizer->name }}</span>
                @endforeach
```

> **注意:** 既存マークアップの構造（アバター丸 + span）に合わせ、合同主催者にも同じ見た目を付けるのが望ましい。最低限「承諾済みの合同主催者名が表示される」ことを満たせばテストは通る。既存の主催者アバターブロック（80-83行目付近）を参考に整える。

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventOrganizerDisplayTest`
Expected: PASS（2件）

- [ ] **Step 6: Commit**

```bash
git add resources/views/events/show.blade.php app/Http/Controllers/EventController.php tests/Feature/EventOrganizerDisplayTest.php
git commit -m "feat: イベント詳細に承諾済み合同主催者を表示"
```

---

## Task 11: 主催者向け管理UI（招待フォーム・除名・権限で出し分け）

**Files:**
- Modify: `resources/views/events/show.blade.php`
- Test: `tests/Feature/EventManagementUiTest.php`

ADR-0002 B案の「画面にボタンを出すか出さないか」を実装する。オーナーには招待フォーム・除名ボタン・削除ボタンを出し、合同主催者には編集・出欠系のみ。

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_invite_and_delete_controls(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertSee('合同主催者を招待');
        $response->assertSee(route('events.organizers.store', $event));
    }

    public function test_co_organizer_does_not_see_invite_or_delete_controls(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $response = $this->actingAs($coOrganizer)->get(route('events.show', $event));

        $response->assertDontSee('合同主催者を招待');
        $response->assertDontSee(route('events.destroy', $event));
    }

    public function test_co_organizer_sees_edit_control(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $response = $this->actingAs($coOrganizer)->get(route('events.show', $event));

        $response->assertSee(route('events.edit', $event));
    }
}
```

> **重要（既存テスト依存の確認）:** 現状の show.blade は編集/削除ボタンを `@if ($event->user_id === auth()->id())` で出している（236行目・35-51行目付近）。このタスクで権限判定を `$event->isOrganizer(auth()->user())`（編集・出欠）と `$event->isOwner(auth()->user())`（削除・招待・除名）に置き換える。`auth()->user()` が null の公開閲覧でも壊れないよう、`auth()->check() && ...` でガードすること。

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventManagementUiTest`
Expected: FAIL（招待UIが無い／合同主催者に編集ボタンが出ていない）

- [ ] **Step 3: Update `resources/views/events/show.blade.php`**

3-1. 編集ボタンの表示条件を主催者ベースに（35行目付近・236行目付近の `@if ($event->user_id === auth()->id())` を次に置換）:

```blade
@if (auth()->check() && $event->isOrganizer(auth()->user()))
```

3-2. 削除フォーム（42-51行目付近）の表示条件をオーナー限定に。編集ボタンと削除ボタンが同じ `@if` ブロックに入っている場合は、削除部分だけ内側で次のようにネストする:

```blade
@if (auth()->check() && $event->isOwner(auth()->user()))
    {{-- 削除フォーム（既存のまま） --}}
@endif
```

3-3. 主催者向け管理セクション（既存の「出欠管理（主催者のみ）」ブロック付近、353行目以降）に合同主催者の招待・一覧・除名UIを追加。オーナー限定で表示:

```blade
@if (auth()->check() && $event->isOwner(auth()->user()))
    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">合同主催者</h3>

        @foreach ($event->eventOrganizers()->with('user')->get() as $organizer)
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="text-slate-700 dark:text-slate-200">
                    {{ $organizer->user->name }}
                    <span class="ml-1 text-xs text-slate-400">
                        @switch($organizer->status)
                            @case(\App\Enums\OrganizerInvitationStatus::Pending) （招待中） @break
                            @case(\App\Enums\OrganizerInvitationStatus::Accepted) （承諾済み） @break
                            @case(\App\Enums\OrganizerInvitationStatus::Declined) （辞退） @break
                        @endswitch
                    </span>
                </span>
                <form method="POST" action="{{ route('events.organizers.destroy', [$event, $organizer]) }}"
                    onsubmit="return confirm('この合同主催者を外しますか？')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-red-600 hover:underline">外す</button>
                </form>
            </div>
        @endforeach

        <form method="POST" action="{{ route('events.organizers.store', $event) }}" class="mt-3 flex gap-2">
            @csrf
            <input type="email" name="email" required placeholder="招待する人のメールアドレス"
                class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900" />
            <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                合同主催者を招待
            </button>
        </form>
        @error('email')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </section>
@endif
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventManagementUiTest`
Expected: PASS（3件）

- [ ] **Step 5: Regression check（既存の詳細ページテスト）**

Run: `php artisan test --compact --filter=EventTest`
Expected: PASS（既存の編集/削除ボタン表示テストが壊れていないこと。壊れた場合は既存テストの期待が `user_id===auth` 前提なので、主催者ベースの新仕様に合わせて期待値を調整する。ただし**テストの削除はしない**。）

- [ ] **Step 6: Commit**

```bash
git add resources/views/events/show.blade.php tests/Feature/EventManagementUiTest.php
git commit -m "feat: イベント詳細に主催者向け管理UI（招待/除名/権限出し分け）を追加"
```

---

## Task 12: 出欠・参加者閲覧の権限を主催者ベースに

**Files:**
- Modify: `app/Http/Controllers/EventAttendanceController.php`
- Modify: `resources/views/events/show.blade.php`（参加者一覧/出欠管理の表示条件）
- Test: `tests/Feature/CoOrganizerAttendanceTest.php`

現状の出欠記録は `updateAttendance` Policy（Task 4 で主催者ベース化済み）を使っているか確認し、参加者一覧の表示条件（show.blade の `353行目: 出欠管理（主催者のみ）`）も主催者ベースにする。

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoOrganizerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_co_organizer_can_record_attendance(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);
        $attendance = EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $this->actingAs($coOrganizer)
            ->patch(route('events.attendances.update', [$event, $attendance]))
            ->assertRedirect();

        $this->assertNotNull($attendance->fresh()->attended_at);
    }

    public function test_co_organizer_sees_attendee_list(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $participant = User::factory()->create(['name' => '参加者サム']);
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
        ]);

        $this->actingAs($coOrganizer)
            ->get(route('events.show', $event))
            ->assertSee('参加者サム');
    }
}
```

> **注意:** `events.attendances.update` の出欠トグルの実挙動（`attended_at` のセット）は `EventAttendanceController::update` の既存実装に依存する。テストの assert は既存実装の挙動に合わせて調整すること（トグルなら2回呼ぶと null に戻る等）。まず既存 `EventAttendanceController::update` を読んで期待値を確定してからテストを書く。

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CoOrganizerAttendanceTest`
Expected: FAIL（参加者一覧が合同主催者に出ない、または権限不足）

- [ ] **Step 3: Confirm `EventAttendanceController::update` uses `updateAttendance` Policy**

`app/Http/Controllers/EventAttendanceController.php` の `update` メソッドを開き、認可が `Gate::authorize('updateAttendance', $event)` または `$this->authorize('updateAttendance', $event)` になっているか確認する。`$event->user_id === auth()->id()` のような直書きになっている場合は Policy 呼び出しに置換する:

```php
        Gate::authorize('updateAttendance', $event);
```

（Task 4 で `updateAttendance` Policy は主催者ベース済みなので、これで合同主催者も記録可能になる。）

- [ ] **Step 4: Update participant-list visibility in `show.blade.php`**

出欠管理/参加者一覧の表示条件（353行目付近 `{{-- 出欠管理（主催者のみ） --}}` 周辺の `@if`）を主催者ベースに置換:

```blade
@if (auth()->check() && $event->isOrganizer(auth()->user()))
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=CoOrganizerAttendanceTest`
Expected: PASS（2件）

- [ ] **Step 6: Regression check**

Run: `php artisan test --compact --filter=EventAttendanceTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EventAttendanceController.php resources/views/events/show.blade.php tests/Feature/CoOrganizerAttendanceTest.php
git commit -m "feat: 出欠記録・参加者一覧を合同主催者にも許可"
```

---

## Task 13: 中止処理を `EventCancellationService` に抽出（DRY）

**Files:**
- Create: `app/Services/EventCancellationService.php`
- Modify: `app/Http/Controllers/EventController.php`（`destroy` をサービス呼び出しに置換）
- Test: `tests/Feature/EventCancellationServiceTest.php`

オーナー離脱時（Task 14）の中止処理と、既存の削除時の中止処理を共通化する。

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\EventCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_soft_deletes_event_and_notifies_attendees(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
        ]);

        app(EventCancellationService::class)->cancel($event);

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertSame(EventStatus::Private, $event->fresh()->status);
        Mail::assertSent(EventCancelledMail::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EventCancellationServiceTest`
Expected: FAIL（サービス未定義）

- [ ] **Step 3: Create the service**

`app/Services/EventCancellationService.php`:

```php
<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EventCancellationService
{
    /**
     * イベントを中止扱いにする。
     * Private 化 → ソフトデリート → 申込中/キャンセル待ちの参加者へ中止メール。
     */
    public function cancel(Event $event): void
    {
        $attendees = $event->attendances()
            ->with('user')
            ->whereIn('status', [AttendanceStatus::Applied, AttendanceStatus::Waitlisted])
            ->get()
            ->map(fn (EventAttendance $a) => $a->user);

        $event->update(['status' => EventStatus::Private]);
        $event->delete();

        foreach ($attendees as $attendee) {
            try {
                Mail::to($attendee->email)->send(new EventCancelledMail($event));
            } catch (\Throwable $e) {
                Log::warning('イベント中止通知メール送信に失敗', [
                    'user_id' => $attendee->id,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

- [ ] **Step 4: Refactor `EventController::destroy` to use the service**

`app/Http/Controllers/EventController.php`:
- コンストラクタに `EventCancellationService` を注入（既存の `EventService` と並べる）:

```php
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventCancellationService $eventCancellationService,
    ) {}
```

- `destroy` メソッドの中身（中止メール送信ロジック）を次に置換:

```php
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $this->eventCancellationService->cancel($event);

        return redirect()->route('events.index')->with('success', 'イベントを削除しました。');
    }
```

- 不要になった `use` を整理（`AttendanceStatus`/`EventAttendance`/`Mail`/`Log`/`EventCancelledMail` が他で未使用なら削除）。`use App\Services\EventCancellationService;` を追加。

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=EventCancellationServiceTest`
Expected: PASS

- [ ] **Step 6: Regression check（既存の削除テスト）**

Run: `php artisan test --compact --filter=EventTest`
Expected: PASS（削除→中止メールの既存テストが通ること）

- [ ] **Step 7: Commit**

```bash
git add app/Services/EventCancellationService.php app/Http/Controllers/EventController.php tests/Feature/EventCancellationServiceTest.php
git commit -m "refactor: イベント中止処理を EventCancellationService に抽出"
```

---

## Task 14: オーナー退会時の自動引き継ぎ／中止（ADR-0002 論点5）

**Files:**
- Create: `app/Services/EventOwnershipService.php`
- Modify: `app/Observers/UserObserver.php`
- Test: `tests/Feature/OwnerDeactivationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\OrganizerInvitationStatus;
use App\Enums\UserStatus;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OwnerDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ownership_transfers_to_earliest_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $first->id,
            'invited_at' => Carbon::parse('2026-06-01 10:00'),
        ]);
        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $second->id,
            'invited_at' => Carbon::parse('2026-06-05 10:00'),
        ]);

        $owner->update(['status' => UserStatus::Inactive]);

        $this->assertSame($first->id, $event->fresh()->user_id);
        // 新オーナーになった人の合同主催レコードは消える
        $this->assertDatabaseMissing('event_organizers', [
            'event_id' => $event->id,
            'user_id' => $first->id,
        ]);
        // イベントは中止されない
        $this->assertNull($event->fresh()->deleted_at);
    }

    public function test_event_is_cancelled_when_no_accepted_co_organizer(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        // 保留中の招待しかない → 引き継ぎ対象なし
        EventOrganizer::factory()->create([
            'event_id' => $event->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);

        $owner->update(['status' => UserStatus::Inactive]);

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        Mail::assertSent(EventCancelledMail::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OwnerDeactivationTest`
Expected: FAIL（引き継ぎ/中止ロジック未実装）

- [ ] **Step 3: Create the service**

`app/Services/EventOwnershipService.php`:

```php
<?php

namespace App\Services;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;

class EventOwnershipService
{
    public function __construct(
        private readonly EventCancellationService $cancellationService,
    ) {}

    /**
     * 退会するユーザーが所有する全イベントを処理する。
     * 承諾済み合同主催者がいれば最も早く招待された1人へ引き継ぎ、
     * いなければイベントを中止扱いにする（黙って消さない）。
     */
    public function handleOwnerDeactivation(User $user): void
    {
        $user->events()->get()->each(function (Event $event): void {
            $this->resolve($event);
        });
    }

    private function resolve(Event $event): void
    {
        $successor = $event->eventOrganizers()
            ->where('status', OrganizerInvitationStatus::Accepted)
            ->orderBy('invited_at')
            ->orderBy('id')
            ->first();

        if ($successor instanceof EventOrganizer) {
            $event->update(['user_id' => $successor->user_id]);
            $successor->delete();

            return;
        }

        $this->cancellationService->cancel($event);
    }
}
```

- [ ] **Step 4: Hook into `UserObserver::updated`**

`app/Observers/UserObserver.php`:
- コンストラクタに `EventOwnershipService` を注入:

```php
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly EventOwnershipService $eventOwnershipService,
    ) {}
```

- `updated` メソッドの Inactive 分岐内、`syncCalendarOnDeactivation($user)` 呼び出しの後に追加:

```php
            $this->eventOwnershipService->handleOwnerDeactivation($user);
```

- `use App\Services\EventOwnershipService;` を追加。

> **順序の注意:** 既存の「自分の申込を Cancelled にする」処理（`$user->eventAttendances()->...->update(...)`）はそのまま残す。オーナー離脱処理は所有イベントに対するもので、申込キャンセルとは独立。両方が走ってよい。

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OwnerDeactivationTest`
Expected: PASS（2件）

- [ ] **Step 6: Regression check（退会まわり）**

Run: `php artisan test --compact --filter=UserDeactivationTest && php artisan test --compact --filter=ProfileWithdrawalTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Services/EventOwnershipService.php app/Observers/UserObserver.php tests/Feature/OwnerDeactivationTest.php
git commit -m "feat: オーナー退会時の自動引き継ぎ・中止処理を追加"
```

---

## Task 15: 手動オーナー移譲

**Files:**
- Create: `app/Http/Controllers/EventOwnershipController.php`
- Modify: `app/Services/EventOwnershipService.php`（移譲メソッド追加）
- Modify: `routes/web.php`
- Modify: `resources/views/events/show.blade.php`（移譲UI）
- Test: `tests/Feature/OwnershipTransferTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_transfer_to_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($owner)
            ->patch(route('events.ownership.update', $event), ['user_id' => $coOrganizer->id])
            ->assertRedirect(route('events.show', $event));

        // 新オーナーに切り替わる
        $this->assertSame($coOrganizer->id, $event->fresh()->user_id);
        // 新オーナーの合同主催レコードは消える
        $this->assertDatabaseMissing('event_organizers', [
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);
        // 旧オーナーは承諾済み合同主催者として残る
        $this->assertDatabaseHas('event_organizers', [
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'status' => OrganizerInvitationStatus::Accepted->value,
        ]);
    }

    public function test_cannot_transfer_to_non_accepted_co_organizer(): void
    {
        $owner = User::factory()->create();
        $pending = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->create([
            'event_id' => $event->id,
            'user_id' => $pending->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->patch(route('events.ownership.update', $event), ['user_id' => $pending->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame($owner->id, $event->fresh()->user_id);
    }

    public function test_co_organizer_cannot_transfer_ownership(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventOrganizer::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $coOrganizer->id,
        ]);

        $this->actingAs($coOrganizer)
            ->patch(route('events.ownership.update', $event), ['user_id' => $coOrganizer->id])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OwnershipTransferTest`
Expected: FAIL（ルート・コントローラ未定義）

- [ ] **Step 3: Add transfer method to `EventOwnershipService`**

`use App\Enums\OrganizerInvitationStatus;` は既に import 済み。次のメソッドを追加:

```php
    /**
     * オーナーを承諾済み合同主催者へ手動で移譲する。
     * 旧オーナーは承諾済みの合同主催者として残す。
     */
    public function transferTo(Event $event, User $newOwner): void
    {
        $previousOwnerId = $event->user_id;

        // 新オーナーの合同主催レコードを削除
        $event->eventOrganizers()->where('user_id', $newOwner->id)->delete();

        // オーナーを差し替え
        $event->update(['user_id' => $newOwner->id]);

        // 旧オーナーを承諾済み合同主催者として残す
        $event->eventOrganizers()->updateOrCreate(
            ['user_id' => $previousOwnerId],
            [
                'status' => OrganizerInvitationStatus::Accepted,
                'invited_at' => now(),
                'responded_at' => now(),
            ],
        );
    }
```

`use App\Models\User;` が未importなら追加（Task 14 で追加済みのはず）。

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/EventOwnershipController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\EventOwnershipService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventOwnershipController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly EventOwnershipService $ownershipService) {}

    /**
     * オーナーを承諾済みの合同主催者へ移譲する
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('transferOwnership', $event);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $isAcceptedCoOrganizer = $event->eventOrganizers()
            ->where('user_id', $validated['user_id'])
            ->where('status', OrganizerInvitationStatus::Accepted)
            ->exists();

        if (! $isAcceptedCoOrganizer) {
            throw ValidationException::withMessages([
                'user_id' => '承諾済みの合同主催者にのみオーナーを移譲できます。',
            ]);
        }

        /** @var User $newOwner */
        $newOwner = User::query()->findOrFail($validated['user_id']);

        $this->ownershipService->transferTo($event, $newOwner);

        return redirect()->route('events.show', $event)->with('success', 'オーナーを移譲しました。');
    }
}
```

- [ ] **Step 5: Add route**

`routes/web.php` の `auth` グループ内に追加:

```php
    Route::patch('events/{event}/ownership', [EventOwnershipController::class, 'update'])->name('events.ownership.update');
```

`use App\Http\Controllers\EventOwnershipController;` を追加。

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OwnershipTransferTest`
Expected: PASS（3件）

- [ ] **Step 7: Add transfer UI to `show.blade.php`（オーナーのみ・承諾済み合同主催者がいる場合）**

Task 11 で追加した「合同主催者」管理セクション内（オーナー限定ブロック）に、承諾済み合同主催者への移譲フォームを追加:

```blade
        @php($acceptedCoOrganizers = $event->acceptedCoOrganizers)
        @if ($acceptedCoOrganizers->isNotEmpty())
            <form method="POST" action="{{ route('events.ownership.update', $event) }}" class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-700"
                onsubmit="return confirm('オーナーを移譲すると、あなたは合同主催者になります。よろしいですか？')">
                @csrf
                @method('PATCH')
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">オーナーを移譲する</label>
                <div class="mt-1 flex gap-2">
                    <select name="user_id" required class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900">
                        @foreach ($acceptedCoOrganizers as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md border border-amber-500 px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 dark:text-amber-400">
                        移譲する
                    </button>
                </div>
                @error('user_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>
        @endif
```

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/EventOwnershipController.php app/Services/EventOwnershipService.php routes/web.php resources/views/events/show.blade.php tests/Feature/OwnershipTransferTest.php
git commit -m "feat: オーナーの手動移譲機能を追加"
```

---

## Task 16: 全体回帰・整形・最終確認

**Files:** （変更なし。検証のみ）

- [ ] **Step 1: Pint で整形**

Run: `vendor/bin/pint --dirty --format agent`
Expected: 変更ファイルが整形される（差分があればコミット対象）

- [ ] **Step 2: 関連テストをまとめて実行**

Run:
```bash
php artisan test --compact --filter='Organizer|Ownership|CoOrganizer|EventCancellation|OwnerDeactivation'
```
Expected: 全 PASS

- [ ] **Step 3: 全テストスイート実行（ユーザー承認のうえ）**

Run: `php artisan test --compact`
Expected: 全 PASS。既存テストが新仕様で落ちる場合は、期待値を新仕様（主催者ベース権限）に合わせて修正する。**テストの削除はしない。**

- [ ] **Step 4: 整形差分があればコミット**

```bash
git add -A
git commit -m "style: Pint による整形"
```

---

## Self-Review（計画作成者によるチェック結果）

**1. Spec coverage（ADR要件→タスク対応）**
- ADR-0001 C案（V5は個別招待のみ・グループ無し）→ 招待ベースの実装のみ（Task 6-9）。グループ概念は登場せず ✓
- ADR-0002 B案・権限表 → Task 4（Policy）/ Task 11-12（UI出し分け）✓
- ADR-0002 論点5（黙って消さない・自動引き継ぎ・中止）→ Task 13-14 ✓
- ADR-0002 手動移譲（V5に含める）→ Task 15 ✓
- ADR-0003 B案（承認制 pending/accepted/declined・辞退可）→ Task 6-7 ✓
- ADR-0003 論点4（公開はacceptedのみ）→ Task 10 ✓

**2. Placeholder scan** — 各実装ステップに実コードを記載。ビューのレイアウト名（`<x-app-layout>`）と既存 show.blade の行番号・既存 `EventAttendanceController::update` の挙動は「現物を確認して合わせる」と明記（コードベース依存のため）。

**3. Type consistency** — `OrganizerInvitationStatus` / `isOwner` / `isOrganizer` / `isAcceptedCoOrganizer` / `eventOrganizers` / `acceptedCoOrganizers` / `EventCancellationService::cancel` / `EventOwnershipService::handleOwnerDeactivation` / `transferTo` を全タスクで統一。ルート名 `events.organizers.store|destroy` / `organizer-invitations.accept|decline` / `my.organizer-invitations` / `events.ownership.update` を統一。

**留意（実装者へ）:** 既存 `EventTest` / `EventAttendanceTest` は「オーナーのみ」前提のアサーションを含む可能性がある。Task 4・11・12 の回帰チェックで落ちたら、新仕様（主催者ベース）に期待値を更新すること（削除は不可）。
