<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 孤児カバー画像の定期回収（安全網）
Schedule::command('covers:prune-orphans')->weekly();

if (config('app.perf_monitoring')) {
    Schedule::command('perf:snapshot')->hourly();
}
