# UC6 リマインドメール配信機能 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 主催者（オーナー＋承諾済み合同主催者）がイベント詳細ページから参加者（Applied のみ）へリマインドメールを非同期送信し、配信結果を受信者単位で確認・再送できる機能を実装する。

**Architecture:** `event_reminders`（配信ヘッダ）と `event_reminder_recipients`（受信者明細）の2テーブルを新設。`EventReminderService::send()` がヘッダ作成＋受信者明細一括作成＋ジョブ dispatch を担い、`SendEventReminderJob` が 1 recipient = 1 job で非同期メール送信する。

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 12, database キュー (`QUEUE_CONNECTION=database`), Tailwind CSS v4

---

## ファイル対応表（作成・変更）

| 種別 | パス |
|------|------|
| 新規 | `app/Enums/ReminderRecipientStatus.php` |
| 新規 | `database/migrations/2026_06_16_000001_create_event_reminders_table.php` |
| 新規 | `database/migrations/2026_06_16_000002_create_event_reminder_recipients_table.php` |
| 新規 | `app/Models/EventReminder.php` |
| 新規 | `app/Models/EventReminderRecipient.php` |
| 新規 | `database/factories/EventReminderFactory.php` |
| 新規 | `database/factories/EventReminderRecipientFactory.php` |
| 新規 | `app/Mail/EventReminderMail.php` |
| 新規 | `resources/views/mail/event-reminder.blade.php` |
| 新規 | `app/Jobs/SendEventReminderJob.php` |
| 新規 | `app/Services/EventReminderService.php` |
| 新規 | `app/Http/Controllers/EventReminderController.php` |
| 変更 | `app/Models/Event.php` — `reminders()` リレーション追加 |
| 変更 | `app/Policies/EventPolicy.php` — `sendReminder()` 追加 |
| 変更 | `routes/web.php` — 2ルート追加 |
| 変更 | `resources/views/events/show.blade.php` — リマインドセクション追加 |
| 新規 | `tests/Feature/Reminder/ReminderStoreTest.php` |
| 新規 | `tests/Feature/Reminder/ReminderResendTest.php` |
| 新規 | `tests/Feature/Reminder/ReminderUiTest.php` |
| 新規 | `tests/Feature/Mail/EventReminderMailTest.php` |
| 新規 | `tests/Unit/Models/EventReminderTest.php` |
| 新規 | `tests/Unit/Models/EventReminderRecipientTest.php` |

---

## Task 1: Enum + マイグレーション

**Files:**
- Create: `app/Enums/ReminderRecipientStatus.php`
- Create: `database/migrations/2026_06_16_000001_create_event_reminders_table.php`
- Create: `database/migrations/2026_06_16_000002_create_event_reminder_recipients_table.php`

- [ ] **Step 1: Enum を作成する**

```bash
php artisan make:enum ReminderRecipientStatus --no-interaction
```

作成されたファイル `app/Enums/ReminderRecipientStatus.php` を以下の内容に置き換える:

```php
<?php

namespace App\Enums;

enum ReminderRecipientStatus: int
{
    case Pending = 0;
    case Sent = 1;
    case Failed = 2;
}
```

- [ ] **Step 2: event_reminders マイグレーションを作成する**

```bash
php artisan make:migration create_event_reminders_table --no-interaction
```

生成されたファイル（`database/migrations/2026_06_16_xxxxxx_create_event_reminders_table.php`）を以下に置き換える（ファイル名の timestamp 部分はそのままにする）:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminders');
    }
};
```

- [ ] **Step 3: event_reminder_recipients マイグレーションを作成する**

```bash
php artisan make:migration create_event_reminder_recipients_table --no-interaction
```

生成されたファイルを以下に置き換える:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reminder_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_reminder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->unsignedTinyInteger('status')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminder_recipients');
    }
};
```

- [ ] **Step 4: マイグレーションを実行する**

```bash
php artisan migrate --no-interaction
```

Expected: `event_reminders` と `event_reminder_recipients` テーブルが作成される。

- [ ] **Step 5: コミット**

```bash
git add app/Enums/ReminderRecipientStatus.php database/migrations/
git commit -m "feat: ReminderRecipientStatus enumとリマインドテーブルマイグレーションを追加"
```

---

## Task 2: モデル + ファクトリ + ユニットテスト

**Files:**
- Create: `app/Models/EventReminder.php`
- Create: `app/Models/EventReminderRecipient.php`
- Create: `database/factories/EventReminderFactory.php`
- Create: `database/factories/EventReminderRecipientFactory.php`
- Create: `tests/Unit/Models/EventReminderTest.php`
- Create: `tests/Unit/Models/EventReminderRecipientTest.php`

- [ ] **Step 1: ユニットテストを書く（失敗するはず）**

```bash
php artisan make:test --unit --phpunit Unit/Models/EventReminderTest --no-interaction
```

`tests/Unit/Models/EventReminderTest.php` を以下の内容に置き換える:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_belongs_to_event(): void
    {
        $reminder = EventReminder::factory()->create();

        $this->assertInstanceOf(Event::class, $reminder->event);
    }

    public function test_reminder_belongs_to_sender(): void
    {
        $reminder = EventReminder::factory()->create();

        $this->assertInstanceOf(User::class, $reminder->sentBy);
    }

    public function test_reminder_has_many_recipients(): void
    {
        $reminder = EventReminder::factory()->create();
        EventReminderRecipient::factory()->count(3)->create(['event_reminder_id' => $reminder->id]);

        $this->assertCount(3, $reminder->recipients);
    }
}
```

```bash
php artisan make:test --unit --phpunit Unit/Models/EventReminderRecipientTest --no-interaction
```

`tests/Unit/Models/EventReminderRecipientTest.php` を以下の内容に置き換える:

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\ReminderRecipientStatus;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReminderRecipientTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_belongs_to_reminder(): void
    {
        $recipient = EventReminderRecipient::factory()->create();

        $this->assertInstanceOf(EventReminder::class, $recipient->reminder);
    }

    public function test_recipient_status_is_cast_to_enum(): void
    {
        $recipient = EventReminderRecipient::factory()->create(['status' => ReminderRecipientStatus::Pending]);

        $this->assertSame(ReminderRecipientStatus::Pending, $recipient->status);
    }

    public function test_recipient_sent_at_is_cast_to_datetime(): void
    {
        $recipient = EventReminderRecipient::factory()->create(['sent_at' => now()]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $recipient->sent_at);
    }
}
```

- [ ] **Step 2: テストが失敗することを確認する**

```bash
php artisan test --compact tests/Unit/Models/EventReminderTest.php tests/Unit/Models/EventReminderRecipientTest.php
```

Expected: FAIL（EventReminder クラスが存在しないため）

- [ ] **Step 3: EventReminder モデルを作成する**

```bash
php artisan make:model EventReminder --factory --no-interaction
```

`app/Models/EventReminder.php` を以下の内容に置き換える:

```php
<?php

namespace App\Models;

use Database\Factories\EventReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'sent_by_user_id',
    'subject',
    'body',
    'total_count',
    'sent_count',
    'failed_count',
])]
class EventReminder extends Model
{
    /** @use HasFactory<EventReminderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return HasMany<EventReminderRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(EventReminderRecipient::class);
    }
}
```

- [ ] **Step 4: EventReminderRecipient モデルを作成する**

```bash
php artisan make:model EventReminderRecipient --factory --no-interaction
```

`app/Models/EventReminderRecipient.php` を以下の内容に置き換える:

```php
<?php

namespace App\Models;

use App\Enums\ReminderRecipientStatus;
use Database\Factories\EventReminderRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_reminder_id',
    'user_id',
    'email',
    'status',
    'error',
    'sent_at',
])]
class EventReminderRecipient extends Model
{
    /** @use HasFactory<EventReminderRecipientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReminderRecipientStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventReminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(EventReminder::class, 'event_reminder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: EventReminderFactory を書く**

`database/factories/EventReminderFactory.php` を以下の内容に置き換える:

```php
<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReminder>
 */
class EventReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'sent_by_user_id' => User::factory(),
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'total_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
        ];
    }
}
```

- [ ] **Step 6: EventReminderRecipientFactory を書く**

`database/factories/EventReminderRecipientFactory.php` を以下の内容に置き換える:

```php
<?php

namespace Database\Factories;

use App\Enums\ReminderRecipientStatus;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReminderRecipient>
 */
class EventReminderRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_reminder_id' => EventReminder::factory(),
            'user_id' => User::factory(),
            'email' => $this->faker->safeEmail(),
            'status' => ReminderRecipientStatus::Pending,
            'error' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReminderRecipientStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReminderRecipientStatus::Failed,
            'error' => 'Connection timed out',
        ]);
    }
}
```

- [ ] **Step 7: テストを実行して合格を確認する**

```bash
php artisan test --compact tests/Unit/Models/EventReminderTest.php tests/Unit/Models/EventReminderRecipientTest.php
```

Expected: PASS (6 tests)

- [ ] **Step 8: Pint で整形する**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: コミット**

```bash
git add app/Models/EventReminder.php app/Models/EventReminderRecipient.php \
        database/factories/EventReminderFactory.php database/factories/EventReminderRecipientFactory.php \
        tests/Unit/Models/EventReminderTest.php tests/Unit/Models/EventReminderRecipientTest.php
git commit -m "feat: EventReminder/EventReminderRecipientモデル・ファクトリ・ユニットテストを追加"
```

---

## Task 3: Event モデルにリレーション追加 + Policy + ルート

**Files:**
- Modify: `app/Models/Event.php`
- Modify: `app/Policies/EventPolicy.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Reminder/ReminderPolicyTest.php` （このタスクでは Policy の認証だけをテスト）

- [ ] **Step 1: Policy テストを書く（失敗するはず）**

```bash
php artisan make:test --phpunit Feature/Reminder/ReminderPolicyTest --no-interaction
```

`tests/Feature/Reminder/ReminderPolicyTest.php` を以下の内容に置き換える:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_send_reminder(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $this->assertTrue($owner->can('sendReminder', $event));
    }

    public function test_accepted_co_organizer_can_send_reminder(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $this->assertTrue($coOrganizer->can('sendReminder', $event));
    }

    public function test_pending_co_organizer_cannot_send_reminder(): void
    {
        $owner = User::factory()->create();
        $pendingUser = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventCoOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $pendingUser->id]);

        $this->assertFalse($pendingUser->can('sendReminder', $event));
    }

    public function test_regular_user_cannot_send_reminder(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($other->can('sendReminder', $event));
    }

    public function test_unauthenticated_user_cannot_send_reminder_via_route(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);

        $this->post(route('events.reminders.store', $event), [
            'subject' => 'テスト',
            'body' => '本文',
        ])->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: テストが失敗することを確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderPolicyTest.php
```

Expected: FAIL（`sendReminder` ability が存在しないため）

- [ ] **Step 3: Event モデルに reminders() リレーションを追加する**

`app/Models/Event.php` の `isOrganizer()` メソッドの直前（最後の `}` の手前）に以下を追加する:

```php
    /**
     * このイベントのリマインド配信ヘッダ一覧
     *
     * @return HasMany<EventReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }
```

また、use 文に `EventReminder` を追加する（`HasMany` はすでにインポート済みのため不要）:

```php
use App\Models\EventReminder;
```

- [ ] **Step 4: EventPolicy に sendReminder を追加する**

`app/Policies/EventPolicy.php` の末尾 `}` の手前に追加する:

```php
    /**
     * リマインド送信はオーナーまたは承諾済み合同主催者に許可
     */
    public function sendReminder(User $user, Event $event): bool
    {
        return $event->isOrganizer($user);
    }
```

- [ ] **Step 5: ルートを追加する**

`routes/web.php` の `Route::patch('events/{event}/ownership', ...)` 行の直後に追加する:

```php
    Route::post('events/{event}/reminders', [EventReminderController::class, 'store'])->name('events.reminders.store');
    Route::post('events/{event}/reminders/{reminder}/resend', [EventReminderController::class, 'resend'])->scopeBindings()->name('events.reminders.resend');
```

`routes/web.php` の冒頭 use ブロックに追加する:

```php
use App\Http\Controllers\EventReminderController;
```

- [ ] **Step 6: テストを実行して合格を確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderPolicyTest.php
```

Expected: PASS (5 tests)

- [ ] **Step 7: Pint で整形する**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: コミット**

```bash
git add app/Models/Event.php app/Policies/EventPolicy.php routes/web.php \
        tests/Feature/Reminder/ReminderPolicyTest.php
git commit -m "feat: Event.reminders()リレーション・sendReminderポリシー・ルートを追加"
```

---

## Task 4: Mailable + メールビュー + メールテスト

**Files:**
- Create: `app/Mail/EventReminderMail.php`
- Create: `resources/views/mail/event-reminder.blade.php`
- Create: `tests/Feature/Mail/EventReminderMailTest.php`

- [ ] **Step 1: メールテストを書く（失敗するはず）**

`tests/Feature/Mail/EventReminderMailTest.php` を新規作成する:

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\EventReminderMail;
use App\Models\EventReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReminderMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_renders_with_subject_and_body(): void
    {
        $reminder = EventReminder::factory()->create([
            'subject' => '明日の持ち物について',
            'body' => 'ノートPCをお持ちください。',
        ]);

        $mail = new EventReminderMail($reminder);
        $rendered = $mail->render();

        $this->assertStringContainsString('ノートPCをお持ちください。', $rendered);
        $this->assertSame('明日の持ち物について', $mail->envelope()->subject);
    }
}
```

- [ ] **Step 2: テストが失敗することを確認する**

```bash
php artisan test --compact tests/Feature/Mail/EventReminderMailTest.php
```

Expected: FAIL（`EventReminderMail` クラスが存在しないため）

- [ ] **Step 3: Mailable を作成する**

```bash
php artisan make:mail EventReminderMail --no-interaction
```

`app/Mail/EventReminderMail.php` を以下の内容に置き換える:

```php
<?php

namespace App\Mail;

use App\Models\EventReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly EventReminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reminder->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-reminder');
    }
}
```

- [ ] **Step 4: メールビューを作成する**

`resources/views/mail/event-reminder.blade.php` を新規作成する:

```html
<p>{{ $reminder->event->title }} の主催者からお知らせがあります。</p>

<p style="white-space: pre-wrap;">{{ $reminder->body }}</p>

<p>このメールはイベントの主催者から送信されました。</p>
```

- [ ] **Step 5: テストを実行して合格を確認する**

```bash
php artisan test --compact tests/Feature/Mail/EventReminderMailTest.php
```

Expected: PASS (1 test)

- [ ] **Step 6: Pint で整形する**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: コミット**

```bash
git add app/Mail/EventReminderMail.php resources/views/mail/event-reminder.blade.php \
        tests/Feature/Mail/EventReminderMailTest.php
git commit -m "feat: EventReminderMailとメールビューを追加"
```

---

## Task 5: ジョブ（SendEventReminderJob）

**Files:**
- Create: `app/Jobs/SendEventReminderJob.php`
- Create: `tests/Feature/Reminder/ReminderJobTest.php`

- [ ] **Step 1: ジョブのテストを書く（失敗するはず）**

```bash
php artisan make:test --phpunit Feature/Reminder/ReminderJobTest --no-interaction
```

`tests/Feature/Reminder/ReminderJobTest.php` を以下の内容に置き換える:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Mail\EventReminderMail;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_mail_and_marks_recipient_as_sent(): void
    {
        Mail::fake();
        $reminder = EventReminder::factory()->create(['total_count' => 1, 'sent_count' => 0]);
        $recipient = EventReminderRecipient::factory()->create([
            'event_reminder_id' => $reminder->id,
            'status' => ReminderRecipientStatus::Pending,
        ]);

        (new SendEventReminderJob($recipient))->handle();

        Mail::assertSent(EventReminderMail::class, function ($mail) use ($recipient) {
            return $mail->hasTo($recipient->email);
        });

        $recipient->refresh();
        $this->assertSame(ReminderRecipientStatus::Sent, $recipient->status);
        $this->assertNotNull($recipient->sent_at);

        $reminder->refresh();
        $this->assertSame(1, $reminder->sent_count);
    }

    public function test_job_is_idempotent_for_already_sent_recipient(): void
    {
        Mail::fake();
        $reminder = EventReminder::factory()->create(['sent_count' => 1]);
        $recipient = EventReminderRecipient::factory()->sent()->create([
            'event_reminder_id' => $reminder->id,
        ]);

        (new SendEventReminderJob($recipient))->handle();

        Mail::assertNothingSent();
        $reminder->refresh();
        $this->assertSame(1, $reminder->sent_count);
    }

    public function test_job_records_failure_on_final_failure(): void
    {
        $reminder = EventReminder::factory()->create(['total_count' => 1]);
        $recipient = EventReminderRecipient::factory()->create([
            'event_reminder_id' => $reminder->id,
            'status' => ReminderRecipientStatus::Pending,
        ]);

        $exception = new \RuntimeException('接続タイムアウト');
        (new SendEventReminderJob($recipient))->failed($exception);

        $recipient->refresh();
        $this->assertSame(ReminderRecipientStatus::Failed, $recipient->status);
        $this->assertStringContainsString('接続タイムアウト', $recipient->error);

        $reminder->refresh();
        $this->assertSame(1, $reminder->failed_count);
    }
}
```

- [ ] **Step 2: テストが失敗することを確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderJobTest.php
```

Expected: FAIL（`SendEventReminderJob` クラスが存在しないため）

- [ ] **Step 3: ジョブを作成する**

```bash
php artisan make:job SendEventReminderJob --no-interaction
```

`app/Jobs/SendEventReminderJob.php` を以下の内容に置き換える:

```php
<?php

namespace App\Jobs;

use App\Enums\ReminderRecipientStatus;
use App\Mail\EventReminderMail;
use App\Models\EventReminderRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEventReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly EventReminderRecipient $recipient) {}

    public function handle(): void
    {
        $this->recipient->refresh();

        if ($this->recipient->status === ReminderRecipientStatus::Sent) {
            return;
        }

        Mail::to($this->recipient->email)->send(new EventReminderMail($this->recipient->reminder));

        $this->recipient->update([
            'status' => ReminderRecipientStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->recipient->reminder->increment('sent_count');
    }

    public function failed(\Throwable $exception): void
    {
        $this->recipient->update([
            'status' => ReminderRecipientStatus::Failed,
            'error' => $exception->getMessage(),
        ]);

        $this->recipient->reminder->increment('failed_count');

        Log::error('リマインドメール送信に最終失敗', [
            'recipient_id' => $this->recipient->id,
            'email' => $this->recipient->email,
            'reminder_id' => $this->recipient->event_reminder_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: テストを実行して合格を確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderJobTest.php
```

Expected: PASS (3 tests)

- [ ] **Step 5: Pint で整形する**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: コミット**

```bash
git add app/Jobs/SendEventReminderJob.php tests/Feature/Reminder/ReminderJobTest.php
git commit -m "feat: SendEventReminderJobを追加（冪等性・失敗記録対応）"
```

---

## Task 6: サービス（EventReminderService）

**Files:**
- Create: `app/Services/EventReminderService.php`
- Create: `tests/Feature/Reminder/ReminderStoreTest.php`
- Create: `tests/Feature/Reminder/ReminderResendTest.php`

- [ ] **Step 1: 送信サービスのテストを書く（失敗するはず）**

```bash
php artisan make:test --phpunit Feature/Reminder/ReminderStoreTest --no-interaction
```

`tests/Feature/Reminder/ReminderStoreTest.php` を以下の内容に置き換える:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\EventReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderStoreTest extends TestCase
{
    use RefreshDatabase;

    private EventReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventReminderService::class);
    }

    public function test_send_creates_reminder_header_and_dispatches_jobs_for_applied_attendees(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $applied1 = User::factory()->create();
        $applied2 = User::factory()->create();
        $cancelled = User::factory()->create();
        $waitlisted = User::factory()->create();

        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $applied1->id, 'status' => AttendanceStatus::Applied, 'applied_at' => now()]);
        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $applied2->id, 'status' => AttendanceStatus::Applied, 'applied_at' => now()]);
        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $cancelled->id, 'status' => AttendanceStatus::Cancelled, 'applied_at' => now(), 'cancelled_at' => now()]);
        EventAttendance::factory()->create(['event_id' => $event->id, 'user_id' => $waitlisted->id, 'status' => AttendanceStatus::Waitlisted, 'waitlisted_at' => now()]);

        $reminder = $this->service->send($event, $owner, '持ち物のお願い', 'ノートPCを持参してください。');

        $this->assertDatabaseHas('event_reminders', [
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'subject' => '持ち物のお願い',
            'total_count' => 2,
        ]);

        $this->assertDatabaseCount('event_reminder_recipients', 2);

        $this->assertDatabaseHas('event_reminder_recipients', [
            'event_reminder_id' => $reminder->id,
            'user_id' => $applied1->id,
            'status' => ReminderRecipientStatus::Pending->value,
        ]);

        $this->assertDatabaseMissing('event_reminder_recipients', [
            'user_id' => $cancelled->id,
        ]);
        $this->assertDatabaseMissing('event_reminder_recipients', [
            'user_id' => $waitlisted->id,
        ]);

        Queue::assertPushed(SendEventReminderJob::class, 2);
    }

    public function test_send_snapshots_email_at_time_of_send(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $participant = User::factory()->create(['email' => 'before@example.com']);
        EventAttendance::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => AttendanceStatus::Applied,
            'applied_at' => now(),
        ]);

        $reminder = $this->service->send($event, $owner, '件名', '本文');

        $this->assertDatabaseHas('event_reminder_recipients', [
            'event_reminder_id' => $reminder->id,
            'email' => 'before@example.com',
        ]);
    }

    public function test_owner_can_trigger_send_via_route(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->post(route('events.reminders.store', $event), [
            'subject' => 'テスト件名',
            'body' => 'テスト本文',
        ]);

        $response->assertRedirect(route('events.show', $event));
        $this->assertDatabaseCount('event_reminders', 1);
    }

    public function test_non_organizer_gets_403_when_trying_to_send(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $this->actingAs($other)
            ->post(route('events.reminders.store', $event), ['subject' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }

    public function test_subject_and_body_are_required(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.reminders.store', $event), [])
            ->assertSessionHasErrors(['subject', 'body']);
    }
}
```

- [ ] **Step 2: 再送テストを書く（失敗するはず）**

```bash
php artisan make:test --phpunit Feature/Reminder/ReminderResendTest --no-interaction
```

`tests/Feature/Reminder/ReminderResendTest.php` を以下の内容に置き換える:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Enums\EventStatus;
use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Models\Event;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_dispatches_jobs_only_for_failed_recipients(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'failed_count' => 2,
        ]);

        $failed1 = EventReminderRecipient::factory()->failed()->create(['event_reminder_id' => $reminder->id]);
        $failed2 = EventReminderRecipient::factory()->failed()->create(['event_reminder_id' => $reminder->id]);
        $sent = EventReminderRecipient::factory()->sent()->create(['event_reminder_id' => $reminder->id]);

        $this->actingAs($owner)
            ->post(route('events.reminders.resend', [$event, $reminder]))
            ->assertRedirect(route('events.show', $event));

        Queue::assertPushed(SendEventReminderJob::class, 2);

        $failed1->refresh();
        $this->assertSame(ReminderRecipientStatus::Pending, $failed1->status);
        $this->assertNull($failed1->error);

        $sent->refresh();
        $this->assertSame(ReminderRecipientStatus::Sent, $sent->status);

        $reminder->refresh();
        $this->assertSame(0, $reminder->failed_count);
    }

    public function test_non_organizer_cannot_resend(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create(['event_id' => $event->id, 'sent_by_user_id' => $owner->id]);

        $this->actingAs($other)
            ->post(route('events.reminders.resend', [$event, $reminder]))
            ->assertForbidden();
    }

    public function test_cannot_resend_reminder_belonging_to_different_event(): void
    {
        $owner = User::factory()->create();
        $event1 = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $event2 = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create(['event_id' => $event2->id, 'sent_by_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('events.reminders.resend', [$event1, $reminder]))
            ->assertNotFound();
    }
}
```

- [ ] **Step 3: テストが失敗することを確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderStoreTest.php tests/Feature/Reminder/ReminderResendTest.php
```

Expected: FAIL（サービスもコントローラも存在しないため）

- [ ] **Step 4: サービスを作成する**

```bash
php artisan make:class Services/EventReminderService --no-interaction
```

`app/Services/EventReminderService.php` を以下の内容に置き換える:

```php
<?php

namespace App\Services;

use App\Enums\ReminderRecipientStatus;
use App\Jobs\SendEventReminderJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventReminderService
{
    public function send(Event $event, User $sender, string $subject, string $body): EventReminder
    {
        $appliedAttendances = $event->appliedAttendances()->with('user')->get();

        return DB::transaction(function () use ($event, $sender, $subject, $body, $appliedAttendances) {
            $reminder = EventReminder::create([
                'event_id' => $event->id,
                'sent_by_user_id' => $sender->id,
                'subject' => $subject,
                'body' => $body,
                'total_count' => $appliedAttendances->count(),
            ]);

            $appliedAttendances->each(function (EventAttendance $attendance) use ($reminder) {
                $recipient = EventReminderRecipient::create([
                    'event_reminder_id' => $reminder->id,
                    'user_id' => $attendance->user_id,
                    'email' => $attendance->user->email,
                    'status' => ReminderRecipientStatus::Pending,
                ]);

                SendEventReminderJob::dispatch($recipient);
            });

            return $reminder;
        });
    }

    public function resend(EventReminder $reminder): void
    {
        $failedRecipients = $reminder->recipients()
            ->where('status', ReminderRecipientStatus::Failed)
            ->get();

        $failedRecipients->each(function (EventReminderRecipient $recipient) {
            $recipient->update([
                'status' => ReminderRecipientStatus::Pending,
                'error' => null,
            ]);
            SendEventReminderJob::dispatch($recipient);
        });

        $reminder->decrement('failed_count', $failedRecipients->count());
    }
}
```

- [ ] **Step 5: コントローラを作成する**

```bash
php artisan make:controller EventReminderController --no-interaction
```

`app/Http/Controllers/EventReminderController.php` を以下の内容に置き換える:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReminder;
use App\Services\EventReminderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventReminderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly EventReminderService $reminderService) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('sendReminder', $event);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $this->reminderService->send($event, $request->user(), $validated['subject'], $validated['body']);

        return redirect()->route('events.show', $event)->with('success', 'リマインドメールを送信しました。');
    }

    public function resend(Event $event, EventReminder $reminder): RedirectResponse
    {
        $this->authorize('sendReminder', $event);

        $this->reminderService->resend($reminder);

        return redirect()->route('events.show', $event)->with('success', '失敗分を再送しました。');
    }
}
```

- [ ] **Step 6: テストを実行して合格を確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderStoreTest.php tests/Feature/Reminder/ReminderResendTest.php
```

Expected: PASS (8 tests)

- [ ] **Step 7: Pint で整形する**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: コミット**

```bash
git add app/Services/EventReminderService.php app/Http/Controllers/EventReminderController.php \
        tests/Feature/Reminder/ReminderStoreTest.php tests/Feature/Reminder/ReminderResendTest.php
git commit -m "feat: EventReminderServiceとEventReminderControllerを追加"
```

---

## Task 7: ビュー（show.blade.php にリマインドセクション追加）

**Files:**
- Modify: `resources/views/events/show.blade.php`
- Create: `tests/Feature/Reminder/ReminderUiTest.php`

- [ ] **Step 1: UI テストを書く（失敗するはず）**

```bash
php artisan make:test --phpunit Feature/Reminder/ReminderUiTest --no-interaction
```

`tests/Feature/Reminder/ReminderUiTest.php` を以下の内容に置き換える:

```php
<?php

namespace Tests\Feature\Reminder;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_reminder_form_on_event_show_page(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertSee('参加者へのリマインド');
        $response->assertSee(route('events.reminders.store', $event));
    }

    public function test_accepted_co_organizer_sees_reminder_form(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $response = $this->actingAs($coOrganizer)->get(route('events.show', $event));

        $response->assertSee('参加者へのリマインド');
    }

    public function test_regular_user_does_not_see_reminder_form(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);

        $response = $this->actingAs($other)->get(route('events.show', $event));

        $response->assertDontSee('参加者へのリマインド');
    }

    public function test_owner_sees_delivery_history_with_resend_button_for_failed(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'subject' => '会場変更のお知らせ',
            'sent_count' => 3,
            'failed_count' => 1,
        ]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertSee('配信履歴');
        $response->assertSee('会場変更のお知らせ');
        $response->assertSee(route('events.reminders.resend', [$event, $reminder]));
    }

    public function test_resend_button_not_shown_when_no_failures(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => EventStatus::Published]);
        EventReminder::factory()->create([
            'event_id' => $event->id,
            'sent_by_user_id' => $owner->id,
            'sent_count' => 5,
            'failed_count' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('events.show', $event));

        $response->assertDontSee('失敗分を再送');
    }
}
```

- [ ] **Step 2: テストが失敗することを確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderUiTest.php
```

Expected: FAIL（ビューにリマインドセクションが存在しないため）

- [ ] **Step 3: show.blade.php にリマインドセクションを追加する**

`resources/views/events/show.blade.php` の `@endcan` (inviteOrganizer の終端、ファイル最後の `</x-app-layout>` の直前) を探して、その直後（`</x-app-layout>` の手前）に以下を追加する:

現在のファイル末尾は:
```blade
    @endcan
</x-app-layout>
```

これを:
```blade
    @endcan

    {{-- リマインドメール（オーナー＋承諾済み合同主催者） --}}
    @can('sendReminder', $event)
        <div class="mt-6">
            <section class="rounded-2xl bg-white dark:bg-[#161615] border border-slate-200 dark:border-[#3E3E3A] p-6 sm:p-8 shadow-sm">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <span class="h-1 w-6 rounded-full bg-gradient-to-r {{ $style['gradient'] }}"></span>
                    参加者へのリマインド
                </h2>

                <form method="POST" action="{{ route('events.reminders.store', $event) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">件名</label>
                        <input type="text" name="subject" required value="{{ old('subject') }}"
                            class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" />
                        @error('subject')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">本文</label>
                        <textarea name="body" required rows="5"
                            class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old('body') }}</textarea>
                        @error('body')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">宛先: 申込中の参加者 {{ $event->appliedAttendances()->count() }} 名</span>
                        <button type="submit"
                            class="rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                            リマインドを送信
                        </button>
                    </div>
                </form>

                @php($reminders = $event->reminders()->latest()->get())
                @if ($reminders->isNotEmpty())
                    <div class="mt-6 border-t border-slate-200 dark:border-slate-700 pt-4">
                        <h3 class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-3">配信履歴</h3>
                        @foreach ($reminders as $sentReminder)
                            <div class="mb-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-700 dark:text-slate-200">
                                        {{ $sentReminder->created_at->format('m/d H:i') }}
                                        「{{ Str::limit($sentReminder->subject, 30) }}」
                                    </span>
                                    <span class="text-xs text-slate-500">
                                        成功 {{ $sentReminder->sent_count }} / 失敗 {{ $sentReminder->failed_count }}
                                    </span>
                                </div>
                                @if ($sentReminder->failed_count > 0)
                                    <form method="POST"
                                        action="{{ route('events.reminders.resend', [$event, $sentReminder]) }}"
                                        class="mt-1">
                                        @csrf
                                        <button type="submit" class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                                            失敗分を再送
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    @endcan
</x-app-layout>
```

- [ ] **Step 4: テストを実行して合格を確認する**

```bash
php artisan test --compact tests/Feature/Reminder/ReminderUiTest.php
```

Expected: PASS (5 tests)

- [ ] **Step 5: Pint で整形する**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: コミット**

```bash
git add resources/views/events/show.blade.php tests/Feature/Reminder/ReminderUiTest.php
git commit -m "feat: イベント詳細ページにリマインド送信フォームと配信履歴セクションを追加"
```

---

## Task 8: 全テスト確認 + 最終コミット

- [ ] **Step 1: テストスイート全体を実行する**

```bash
php artisan test --compact
```

Expected: 全テスト PASS。FAIL があれば内容を確認して修正する。

- [ ] **Step 2: ルート一覧を確認して新ルートが正しく登録されているか確認する**

```bash
php artisan route:list --path=events --name=reminder
```

Expected:
```
POST  events/{event}/reminders                  events.reminders.store
POST  events/{event}/reminders/{reminder}/resend events.reminders.resend
```

- [ ] **Step 3: Pint で最終整形する**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: 最終コミット（差分があれば）**

```bash
git add -p
git commit -m "style: Pint整形"
```

---

## 手動確認チェックリスト

実装完了後、以下を手動で確認する（ワーカー起動が必要）:

```bash
# キューワーカーを起動（別ターミナルで）
php artisan queue:work
```

- [ ] オーナーでイベント詳細ページを開き、「参加者へのリマインド」セクションが表示される
- [ ] 件名・本文を入力して送信 → MailHog（または Mailtrap）でメールが受信できる
- [ ] 配信履歴に「成功 N / 失敗 0」が表示される
- [ ] 承諾済み合同主催者でもリマインド送信できる
- [ ] 一般ユーザーにはセクションが非表示である
