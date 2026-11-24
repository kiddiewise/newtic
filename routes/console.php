<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\SystemHealthRecorder;

Schedule::command('health:heartbeat')->everyMinute()->withoutOverlapping(2);

 
// Public release metadata is checked quietly. No storefront request calls GitHub.
Schedule::command('newtic:check-updates')->everySixHours()->withoutOverlapping(15);

// Only an admin-confirmed update runs. The scheduler can complete it during maintenance.
Schedule::command('newtic:apply-update')->everyMinute()
    ->withoutOverlapping(120)->evenInMaintenanceMode();


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process a bounded transactional email outbox once a minute. No persistent
// queue worker is required on typical Hostinger shared plans. Disabled until
// SMTP is verified and an administrator explicitly enables sending.
Schedule::command('email:process --limit=25')->everyMinute()->withoutOverlapping(3)
    ->onSuccess(fn () => SystemHealthRecorder::record('email_process', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('email_process', false, 'command_failed'));
// Marketing is completely separate from transactional delivery. A strict
// SMTP/merchant/IYS gate and bounded recipient batches prevent bursts.
Schedule::command('marketing:process --limit=10')->everyMinute()->withoutOverlapping(5)
    ->onSuccess(fn () => SystemHealthRecorder::record('marketing_process', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('marketing_process', false, 'command_failed'));

// Reconcile missed per-shipment callbacks; canceled/failed/draft never callback.
// Requires a 1-minute Laravel schedule runner; the integration enforces its own environment gate.
Schedule::command('shipping:sync --limit=20')
    ->everyFifteenMinutes()->withoutOverlapping(12)
    ->onSuccess(fn () => SystemHealthRecorder::record('shipping_sync', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('shipping_sync', false, 'command_failed'));

// Requires a provider-neutral one-minute Laravel scheduler runner.
Schedule::command('merchant:sync --steps=6')->everyFifteenMinutes()->withoutOverlapping(13)
    ->onSuccess(fn () => SystemHealthRecorder::record('merchant_sync', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('merchant_sync', false, 'command_failed'));

// Release stale bank transfer reservations; never expire PAYTR payments or
// orders with an unreviewed/verified bank payment notification.
Schedule::command('orders:expire-unpaid --limit=50')->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onSuccess(fn () => SystemHealthRecorder::record('bank_expiry', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('bank_expiry', false, 'command_failed'));

// Read-only PayTR reconciliation. Never retry the refund POST.
Schedule::command('returns:reconcile --limit=10')->everyFifteenMinutes()->withoutOverlapping(10);

// Privacy: automatically purge events after 90 days and expired sessions.
Schedule::command('analytics:prune')->dailyAt('03:25')->withoutOverlapping(15)
    ->onSuccess(fn () => SystemHealthRecorder::record('analytics_prune', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('analytics_prune', false, 'command_failed'));

// Remove only abandoned private cart-photo drafts. Order copies are never touched.
Schedule::command('customer-photos:prune-drafts --days=7 --limit=200')
    ->dailyAt('03:45')->withoutOverlapping(30)
    ->onSuccess(fn () => SystemHealthRecorder::record('photo_prune', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('photo_prune', false, 'command_failed'));
