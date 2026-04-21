<?php

declare(strict_types=1);

use App\Console\Commands\PruneWorkbenches;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// REQ-M6-005: hard-delete soft-deleted workbenches past the 30-day
// recovery window. Runs once a day at 03:15 server-time; the cascade on
// `snapshots.workbench_id` removes every descendant row.
Schedule::command(PruneWorkbenches::class)->dailyAt('03:15');
