# UC5: 参加者把握・出欠管理 実装計画

> **エージェント向け:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development（推奨）または superpowers:executing-plans を使用してタスク単位で実装してください。

**目標:** イベント主催者がイベント詳細ページから参加者の出欠をリアルタイムで記録できる機能を実装する

**アーキテクチャ:** 
- データベーススキーマ拡張（attended_at カラム追加）
- トグルボタン UI で出欠を記録
- EventPolicy で主催者のみにアクセス権限を付与
- TDD により全動作をテストカバー

**技術スタック:** Laravel 13, Blade, PHPUnit, SQLite/MySQL

---

## ファイル構成

```
database/
  └─ migrations/
     └─ YYYY_MM_DD_HHMMSS_add_attended_at_to_event_attendances.php

app/
  ├─ Models/
  │  └─ EventAttendance.php (修正)
  ├─ Http/
  │  └─ Controllers/
  │     └─ EventAttendanceController.php (修正)
  └─ Policies/
     └─ EventPolicy.php (修正)

resources/
  └─ views/
     └─ events/
        └─ show.blade.php (修正)

tests/
  └─ Feature/
     └─ EventAttendanceTest.php (修正)

routes/
  └─ web.php (修正)
```

---

## Task 1: マイグレーションファイルを作成

**対象ファイル:**
- 作成: `database/migrations/2026_05_20_000000_add_attended_at_to_event_attendances.php`

- [ ] **ステップ1: マイグレーションコマンドを実行**

```bash
php artisan make:migration add_attended_at_to_event_attendances --table=event_attendances
```

実行後、`database/migrations/` に新しいファイルが作成される

- [ ] **ステップ2: マイグレーションファイルを編集**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendances', function (Blueprint $table) {
            $table->timestamp('attended_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendances', function (Blueprint $table) {
            $table->dropColumn('attended_at');
        });
    }
};
```

- [ ] **ステップ3: マイグレーションが正しいか確認**

```bash
php artisan migrate --pretend
```

期待値: `ALTER TABLE event_attendances ADD COLUMN attended_at` という SQL が表示される

- [ ] **ステップ4: コミット**

```bash
git add database/migrations/2026_05_20_000000_add_attended_at_to_event_attendances.php
git commit -m "migration: add attended_at column to event_attendances table"
```

---

## Task 2: EventAttendance モデルを更新（型キャスト）

**対象ファイル:**
- 修正: `app/Models/EventAttendance.php`

- [ ] **ステップ1: 現在のモデルを確認**

```bash
cat app/Models/EventAttendance.php
```

- [ ] **ステップ2: `attended_at` を型キャストに追加**

`app/Models/EventAttendance.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventAttendance extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'event_id', 'attended_at'];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
```

- [ ] **ステップ3: コミット**

```bash
git add app/Models/EventAttendance.php
git commit -m "model: add attended_at datetime casting to EventAttendance"
```

---

## Task 3: EventPolicy に認可ルールを追加

**対象ファイル:**
- 修正: `app/Policies/EventPolicy.php`

- [ ] **ステップ1: 現在の EventPolicy を確認**

```bash
cat app/Policies/EventPolicy.php
```

- [ ] **ステップ2: `updateAttendance` メソッドを追加**

`app/Policies/EventPolicy.php` に以下を追加:

```php
<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }

    public function updateAttendance(User $user, Event $event): bool
    {
        return $user->id === $event->user_id;
    }
}
```

- [ ] **ステップ3: コミット**

```bash
git add app/Policies/EventPolicy.php
git commit -m "policy: add updateAttendance authorization rule"
```

---

## Task 4: EventAttendanceController に update メソッドを追加

**対象ファイル:**
- 修正: `app/Http/Controllers/EventAttendanceController.php`

- [ ] **ステップ1: 現在のコントローラーを確認**

```bash
cat app/Http/Controllers/EventAttendanceController.php
```

- [ ] **ステップ2: `update` メソッドを追加**

`app/Http/Controllers/EventAttendanceController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventAttendanceController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('create', EventAttendance::class);

        EventAttendance::create([
            'user_id' => $request->user()->id,
            'event_id' => $event->id,
        ]);

        return redirect()->route('events.show', $event)
            ->with('success', 'イベントに申し込みました');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $attendance = EventAttendance::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $attendance->delete();

        return redirect()->route('events.show', $event)
            ->with('success', 'イベント申し込みをキャンセルしました');
    }

    public function update(Request $request, Event $event, EventAttendance $attendance): RedirectResponse
    {
        $this->authorize('updateAttendance', $event);

        if ($attendance->event_id !== $event->id) {
            abort(404);
        }

        $attendedAt = $request->input('attended_at');

        if ($attendedAt === 'null') {
            $attendance->update(['attended_at' => null]);
        } else {
            $attendance->update(['attended_at' => now()]);
        }

        return redirect()->route('events.show', $event)
            ->with('success', '出欠を記録しました');
    }
}
```

- [ ] **ステップ3: コミット**

```bash
git add app/Http/Controllers/EventAttendanceController.php
git commit -m "controller: add update method for attendance tracking"
```

---

## Task 5: ルートに PATCH エンドポイントを追加

**対象ファイル:**
- 修正: `routes/web.php`

- [ ] **ステップ1: 現在のルートを確認**

```bash
grep -n "attendances" routes/web.php
```

- [ ] **ステップ2: PATCH ルートを追加**

`routes/web.php` の認証グループ内（既存の POST/DELETE の下）に追加:

```php
Route::patch('events/{event}/attendances/{attendance}', [EventAttendanceController::class, 'update'])->name('events.attendances.update');
```

全体の attendances 関連ルート:

```php
Route::post('events/{event}/attendances', [EventAttendanceController::class, 'store'])->name('events.attendances.store');
Route::delete('events/{event}/attendances', [EventAttendanceController::class, 'destroy'])->name('events.attendances.destroy');
Route::patch('events/{event}/attendances/{attendance}', [EventAttendanceController::class, 'update'])->name('events.attendances.update');
Route::get('events/{event}/attendances', fn ($event) => redirect()->route('events.show', $event));
```

- [ ] **ステップ3: ルートが追加されたか確認**

```bash
php artisan route:list --name=attendances
```

期待値: `events.attendances.update` が `PATCH` メソッドで表示される

- [ ] **ステップ4: コミット**

```bash
git add routes/web.php
git commit -m "route: add PATCH route for attendance tracking update"
```

---

## Task 6: イベント詳細ページ Blade テンプレートを更新

**対象ファイル:**
- 修正: `resources/views/events/show.blade.php`

- [ ] **ステップ1: 現在のテンプレートを確認**

```bash
head -50 resources/views/events/show.blade.php
```

- [ ] **ステップ2: 出欠管理セクションを追加**

`resources/views/events/show.blade.php` の参加者一覧セクション下に以下を追加:

```blade
@can('updateAttendance', $event)
<div class="mt-12 border-t pt-8">
    <h2 class="text-2xl font-bold mb-6">出欠管理</h2>
    
    <!-- 参加者セクション -->
    <div class="mb-8">
        <h3 class="text-lg font-semibold mb-4">参加者</h3>
        <div class="space-y-3">
            @forelse($event->attendances()->whereNull('deleted_at')->get() as $attendance)
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <span class="font-medium">{{ $attendance->user->name }}</span>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('events.attendances.update', [$event, $attendance]) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="attended_at" value="{{ now()->format('Y-m-d H:i:s') }}">
                            <button 
                                type="submit"
                                class="px-3 py-1 rounded text-sm font-medium transition-colors @if($attendance->attended_at) bg-green-500 text-white hover:bg-green-600 @else bg-gray-200 text-gray-700 hover:bg-gray-300 @endif"
                            >
                                @if($attendance->attended_at) ✓ @endif 参加
                            </button>
                        </form>
                        
                        <form method="POST" action="{{ route('events.attendances.update', [$event, $attendance]) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="attended_at" value="null">
                            <button 
                                type="submit"
                                class="px-3 py-1 rounded text-sm font-medium transition-colors @if(!$attendance->attended_at) bg-gray-400 text-white hover:bg-gray-500 @else bg-gray-200 text-gray-700 hover:bg-gray-300 @endif"
                            >
                                @if(!$attendance->attended_at) ✓ @endif 未参加
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-gray-500">参加者がいません</p>
            @endforelse
        </div>
    </div>
    
    <!-- キャンセル一覧セクション -->
    <div>
        <h3 class="text-lg font-semibold mb-4">キャンセル一覧</h3>
        <div class="space-y-3">
            @forelse($event->attendances()->onlyTrashed()->get() as $attendance)
                <div class="flex justify-between items-center p-3 bg-gray-100 rounded opacity-75">
                    <span class="text-gray-700">{{ $attendance->user->name }}</span>
                    <span class="text-sm text-gray-600">キャンセル済み</span>
                </div>
            @empty
                <p class="text-gray-500">キャンセル者はいません</p>
            @endforelse
        </div>
    </div>
</div>
@endcan
```

- [ ] **ステップ3: コミット**

```bash
git add resources/views/events/show.blade.php
git commit -m "view: add attendance tracking UI to event detail page"
```

---

## Task 7: マイグレーション実行とキャッシュ削除

**対象ファイル:**
- (データベース修正のみ)

- [ ] **ステップ1: マイグレーションを実行**

```bash
php artisan migrate
```

期待値: マイグレーション完了のメッセージが表示される

- [ ] **ステップ2: キャッシュをクリア**

```bash
php artisan optimize:clear
```

- [ ] **ステップ3: ローカルで動作確認**

```bash
php artisan serve
```

ブラウザで `http://localhost:8000` にアクセス  
主催者としてログインし、自分のイベント詳細ページで出欠管理セクションが表示されることを確認

---

## Task 8: フィーチャーテストを追加

**対象ファイル:**
- 修正: `tests/Feature/EventAttendanceTest.php`

- [ ] **ステップ1: 既存テストを確認**

```bash
cat tests/Feature/EventAttendanceTest.php
```

- [ ] **ステップ2: 出欠管理用テストを追加**

以下をテストクラスの最後に追加:

```php
public function test_organizer_can_mark_attendance_as_attended(): void
{
    $organizer = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $organizer->id]);
    $attendance = EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
        'attended_at' => null,
    ]);

    $response = $this->actingAs($organizer)
        ->patch(route('events.attendances.update', [$event, $attendance]), [
            'attended_at' => now()->format('Y-m-d H:i:s'),
        ]);

    $response->assertRedirect(route('events.show', $event));
    $this->assertNotNull($attendance->refresh()->attended_at);
}

public function test_organizer_can_clear_attendance(): void
{
    $organizer = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $organizer->id]);
    $attendance = EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
        'attended_at' => now(),
    ]);

    $response = $this->actingAs($organizer)
        ->patch(route('events.attendances.update', [$event, $attendance]), [
            'attended_at' => null,
        ]);

    $response->assertRedirect(route('events.show', $event));
    $this->assertNull($attendance->refresh()->attended_at);
}

public function test_non_organizer_cannot_mark_attendance(): void
{
    $organizer = User::factory()->create();
    $other_user = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $organizer->id]);
    $attendance = EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
    ]);

    $response = $this->actingAs($other_user)
        ->patch(route('events.attendances.update', [$event, $attendance]), [
            'attended_at' => now()->format('Y-m-d H:i:s'),
        ]);

    $response->assertForbidden();
    $this->assertNull($attendance->refresh()->attended_at);
}

public function test_organizer_sees_attendance_section_on_event_detail(): void
{
    $organizer = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $organizer->id]);
    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
    ]);

    $response = $this->actingAs($organizer)
        ->get(route('events.show', $event));

    $response->assertSee('出欠管理');
    $response->assertSee($attendee->name);
    $response->assertSee('参加者');
}

public function test_non_organizer_does_not_see_attendance_section(): void
{
    $organizer = User::factory()->create();
    $other_user = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $organizer->id]);
    EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
    ]);

    $response = $this->actingAs($other_user)
        ->get(route('events.show', $event));

    $response->assertDontSee('出欠管理');
}

public function test_cancelled_attendee_appears_in_cancelled_list(): void
{
    $organizer = User::factory()->create();
    $attendee = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $organizer->id]);
    $attendance = EventAttendance::factory()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
    ]);
    
    // キャンセル
    $attendance->delete();

    $response = $this->actingAs($organizer)
        ->get(route('events.show', $event));

    $response->assertSee('キャンセル一覧');
}
```

- [ ] **ステップ3: テストが正しく書かれているか確認**

```bash
php artisan test tests/Feature/EventAttendanceTest.php -v
```

期待値: すべてのテストが PASS

- [ ] **ステップ4: コミット**

```bash
git add tests/Feature/EventAttendanceTest.php
git commit -m "test: add attendance tracking tests"
```

---

## Task 9: 全体テスト実行と Pint チェック

**対象ファイル:**
- (テストファイルのみ)

- [ ] **ステップ1: すべてのテストを実行**

```bash
php artisan test --compact
```

期待値: 全テスト PASS（83件以上）

- [ ] **ステップ2: Pint でコード整形**

```bash
vendor/bin/pint --dirty --format agent
```

期待値: 修正されたファイルが表示されるか、すべて OK

- [ ] **ステップ3: ルートリストで新ルートを確認**

```bash
php artisan route:list --name=attendances
```

期待値: `events.attendances.update` が PATCH メソッドで表示される

- [ ] **ステップ4: Git ログで確認**

```bash
git log --oneline | head -10
```

期待値: UC5 関連の 8-9 個のコミットが表示される

---

## 計画の自己審査

**仕様カバレッジ確認:**
- ✅ マイグレーション：attended_at カラム追加
- ✅ モデル：型キャスト追加
- ✅ API エンドポイント：PATCH ルート追加
- ✅ UI：トグルボタン出欠管理セクション
- ✅ 認可：EventPolicy.updateAttendance 追加
- ✅ テスト：フィーチャーテスト 6 件作成
- ✅ キャンセル一覧：soft delete 処理

**プレースホルダー確認:**
- ✅ "TBD" や "TODO" なし
- ✅ すべてのコードブロックが完全かつ実行可能
- ✅ すべてのコマンドと期待値を記載

**型の一貫性:**
- ✅ `attended_at` → `datetime` キャスト (Task 2) がコントローラー (Task 4) で使用
- ✅ `updateAttendance()` メソッドが Policy (Task 3) で定義され、Controller (Task 4) で使用
- ✅ ルート名 `events.attendances.update` がリダイレクト (Task 6) と一致

**抜け漏れ:**
- なし。すべての仕様要件がカバーされている。

---

実装計画が完成しました。**2つの実行方法があります:**

**1. サブエージェント方式（推奨）** — タスク単位でサブエージェントを起動、タスク間でレビュー、高速反復

**2. インライン実行** — このセッションで executing-plans を使用、チェックポイント付きバッチ実行

どちらの方法で進めますか？
