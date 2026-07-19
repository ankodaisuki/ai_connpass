<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
