<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process a bounded transactional email outbox once a minute. No persistent
// queue worker is required on typical Hostinger shared plans. Disabled until
// SMTP is verified and an administrator explicitly enables sending.
Schedule::command('email:process --limit=25')->everyMinute()->withoutOverlapping(3);
// Marketing is completely separate from transactional delivery. A strict
// SMTP/merchant/IYS gate and bounded recipient batches prevent bursts.
Schedule::command('marketing:process --limit=10')->everyMinute()->withoutOverlapping(5);

// Reconcile missed per-shipment callbacks; canceled/failed/draft never callback.
// Requires a 1-minute Laravel cron runner, or the equivalent 15-minute direct
// shipping:sync cron configured on staging Hostinger. Never runs on live domains.
Schedule::command('shipping:sync --limit=20')
    ->everyFifteenMinutes()->withoutOverlapping(12);

// Requires a configured Hostinger cron calling php artisan schedule:run.
Schedule::command('merchant:sync --steps=6')->everyFifteenMinutes()->withoutOverlapping(13);

// Release stale bank transfer reservations; never expire PAYTR payments or
// orders with an unreviewed/verified bank payment notification.
Schedule::command('orders:expire-unpaid --limit=50')->everyFifteenMinutes()
    ->withoutOverlapping(10);

// Privacy: automatically purge events after 90 days and expired sessions.
Schedule::command('analytics:prune')->dailyAt('03:25')->withoutOverlapping(15);

// Remove only abandoned private cart-photo drafts. Order copies are never touched.
Schedule::command('customer-photos:prune-drafts --days=7 --limit=200')
    ->dailyAt('03:45')->withoutOverlapping(30);
