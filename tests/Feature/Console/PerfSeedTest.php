<?php

use App\Enums\AttendanceStatus;
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

    // status内訳: $n % 20 のロジックから、100件なら Applied 80 / Cancelled 15 / Waitlisted 5 で確定する
    expect(DB::table('event_attendances')->where('status', AttendanceStatus::Applied->value)->count())->toBe(80);
    expect(DB::table('event_attendances')->where('status', AttendanceStatus::Cancelled->value)->count())->toBe(15);
    expect(DB::table('event_attendances')->where('status', AttendanceStatus::Waitlisted->value)->count())->toBe(5);

    // 時系列の整合性: Cancelled の cancelled_at は applied_at 以降でなければならない
    // （過去に cancelled_at < applied_at という矛盾が発生するバグが修正された経緯があるための回帰テスト）
    $invalidCancellations = DB::table('event_attendances')
        ->where('status', AttendanceStatus::Cancelled->value)
        ->whereColumn('cancelled_at', '<', 'applied_at')
        ->count();
    expect($invalidCancellations)->toBe(0);

    // 過去イベントは終了済み
    $past = Event::where('title', '【perf】過去イベント1')->firstOrFail();
    expect($past->end_date->isPast())->toBeTrue();
});

test('perf:seed はバルクユーザー投入中にID連続性が崩れると例外を投げて中断する', function () {
    // 本番でのシード投入中に他のユーザー登録が割り込むケースを模して、
    // 最初のバルクユーザーINSERT直後（次のチャンクが始まる前）に無関係な
    // ユーザーを1件差し込み、auto increment IDに欠番を作る。
    $injected = false;
    DB::listen(function ($query) use (&$injected) {
        if ($injected) {
            return;
        }

        $sql = strtolower($query->sql);
        $bindings = implode(',', $query->bindings);
        if (str_contains($sql, 'insert into') && str_contains($sql, 'users') && str_contains($bindings, 'bulk_user_1@perf.test')) {
            $injected = true;
            DB::table('users')->insert([
                'email' => 'interloper@example.com',
                'name' => '割り込みユーザー',
                'password' => Hash::make('interloper'),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    expect(fn () => $this->artisan('perf:seed', [
        '--test-accounts' => 0,
        '--users' => 10,
        '--events' => 0,
        '--published-events' => 0,
        '--attendances' => 0,
        '--chunk' => 3, // 3件ごとにチャンクを分け、1チャンク目の後に割り込ませる
        '--force' => true,
    ])->run())->toThrow(RuntimeException::class, 'ID連続性が崩れています');
});
