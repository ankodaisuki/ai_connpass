<?php

namespace App\Console\Commands;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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
    private function seedTestAccounts(string $hash, Carbon $now): int
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
        $allIds = $count > 0 ? range(1, $count) : [];
        foreach (array_chunk($allIds, max($chunk, 1)) as $ids) {
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
    private function seedTargetEvent(int $organizerId, Carbon $now): int
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
    private function seedPublishedEvents(int $organizerId, Carbon $now): void
    {
        $count = (int) $this->option('published-events');
        $chunk = (int) $this->option('chunk');
        $prefectures = ['東京都', '大阪府', '愛知県', 'オンライン', '福岡県'];

        $allIds = $count > 0 ? range(1, $count) : [];
        foreach (array_chunk($allIds, max($chunk, 1)) as $ids) {
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
     *
     * 空のDBで試験すると実態より良い数字が出るため、想定時点までに蓄積している
     * はずの行数を事前に入れる（スペック「事前データ」参照）。
     */
    private function seedBulkData(string $hash, int $organizerId, Carbon $now): void
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
}
