<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PerfSnapshot extends Command
{
    protected $signature = 'perf:snapshot';

    protected $description = 'ロングラン観測用のスナップショット（テーブル行数・DB状態）をJSONLに追記する';

    /**
     * SHOW GLOBAL STATUS で取得するMySQLステータス変数名。
     *
     * @var array<int, string>
     */
    private const MYSQL_STATUS_VARIABLES = [
        'Threads_connected', 'Innodb_row_lock_waits', 'Innodb_row_lock_time', 'Slow_queries',
    ];

    public function handle(): int
    {
        $tables = collect([
            'sessions', 'jobs', 'failed_jobs', 'cache', 'users', 'events', 'event_attendances',
        ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $mysql = $this->fetchMysqlStatus();

        $line = json_encode([
            'at' => now()->toIso8601String(),
            'tables' => $tables,
            'mysql' => $mysql,
        ], JSON_UNESCAPED_UNICODE);

        File::append(storage_path('logs/perf-snapshots.jsonl'), $line."\n");
        $this->line($line);

        return self::SUCCESS;
    }

    /**
     * MySQL接続時のみ SHOW GLOBAL STATUS を実行する。
     * テスト等で sqlite 接続の場合はMySQL専用構文が使えないため、
     * 同じキー構造をnull値で返す。
     *
     * @return Collection<string, string|null>
     */
    private function fetchMysqlStatus(): Collection
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return collect(self::MYSQL_STATUS_VARIABLES)->mapWithKeys(fn (string $name): array => [$name => null]);
        }

        $placeholders = implode(', ', array_fill(0, count(self::MYSQL_STATUS_VARIABLES), '?'));

        return collect(DB::select(
            "SHOW GLOBAL STATUS WHERE Variable_name IN ({$placeholders})",
            self::MYSQL_STATUS_VARIABLES
        ))->mapWithKeys(fn (object $row): array => [$row->Variable_name => $row->Value]);
    }
}
