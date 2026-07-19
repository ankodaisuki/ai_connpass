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
