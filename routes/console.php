<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconcile missed per-shipment callbacks; canceled/failed/draft never callback.
// Requires a 1-minute Laravel cron runner, or the equivalent 15-minute direct
// shipping:sync cron configured on staging Hostinger. Never runs on live domains.
Schedule::command('shipping:sync --limit=20')
    ->everyFifteenMinutes()->withoutOverlapping(12);

// Requires a configured Hostinger cron calling php artisan schedule:run.
Schedule::command('merchant:sync --steps=6')->everyFifteenMinutes()->withoutOverlapping(13);
