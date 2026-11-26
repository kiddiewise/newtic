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

// Release stale bank transfer reservations; never expire bank orders with
// orders with an unreviewed/verified bank payment notification.
Schedule::command('orders:expire-unpaid --limit=50')->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onSuccess(fn () => SystemHealthRecorder::record('bank_expiry', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('bank_expiry', false, 'command_failed'));

// Verify paid attempts and safely release orders for which no token request was started.
Schedule::command('paytr:reconcile-pending --limit=10')->everyFiveMinutes()->withoutOverlapping(4)
    ->onSuccess(fn () => SystemHealthRecorder::record('paytr_reconcile', true, 'command_completed'))
    ->onFailure(fn () => SystemHealthRecorder::record('paytr_reconcile', false, 'command_failed'));

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

// Bounded Web Push delivery; no long-lived worker is required on shared hosting.
Schedule::command('admin:push-orders')->everyMinute()->withoutOverlapping(3);


// Explicit operator-only sandbox diagnostic; no scheduler, no invoice mutation.
// Only fixed status codes and counts are printed, never secrets or raw SOAP.
Artisan::command('edm:diagnose {identifier}', function () {
    $identifier = (string) $this->argument('identifier');
    if (!preg_match('/^[0-9]{10,11}$/D',$identifier) || $identifier==='11111111111') {
        $this->error('INVALID_IDENTIFIER');
        return 1;
    }
    try {
        $settings = \App\Models\EdmSetting::query()->find(1);
        if (!$settings?->configured() || $settings->probe_status!=='ok') {
            $this->line('EDM_DIAGNOSTIC_ACCOUNT_NOT_VERIFIED');
            return 1;
        }
        $client = app(\App\Services\Invoices\EdmTestClient::class);
        foreach (['company','taxpayer'] as $kind) {
            $result = $client->{$kind}($settings->username,$settings->password,$identifier);
            $data = $result['data'] ?? [];
            $diagnostic = $result['diagnostic'] ?? '';
            $safe = preg_match('/^(?:Login|Lookup|Logout): [A-Za-z0-9_]+$/D',$diagnostic) ? $diagnostic : '';
            $this->line(json_encode([
                'operation'=>$kind,'status'=>$result['status'],'diagnostic'=>$safe,
                'series_count'=>count($data['series'] ?? []),
                'unreadable_series'=>(int)($data['unreadable_series'] ?? 0),
                'pk_count'=>count($data['aliases'] ?? []),
                'gb_count'=>count($data['sender_aliases'] ?? []),
                'unverified_records'=>(int)($data['unverified_records'] ?? 0),
                'series_warning_reasons'=>$data['series_warning_reasons']??[],
                'recipient_warning_reasons'=>$data['recipient_warning_reasons']??[],
            ],JSON_THROW_ON_ERROR));
        }
        return 0;
    } catch (\Throwable) {
        $this->error('EDM_DIAGNOSTIC_UNAVAILABLE');
        return 1;
    }
})->purpose('Read-only EDM test discovery diagnostic with redacted results');


// Restricted one-shot staging experiment. The deployment workflow supplies
// the digest of the exact synthetic XML checked with pinned GIB rules.
// No credentials, customer information, raw SOAP or XML in CLI output.
Artisan::command('edm:smoke-load {date} {sha256} {confirmation}', function () {
    try {
        $result=app(\App\Services\Invoices\EdmTestClient::class)->runControlledSmoke(
            (string)$this->argument('date'),(string)$this->argument('sha256'),(string)$this->argument('confirmation'));
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return 0;
    } catch (\Throwable $e) {
        $safe=$e instanceof \DomainException && preg_match('/^SMOKE_[A-Z_]+$/D',$e->getMessage())?$e->getMessage():'SMOKE_EXECUTION_UNCONFIRMED';
        $this->error($safe);
        return 1;
    }
})->purpose('One isolated unsigned EDM sandbox draft; never sends or allocates a fiscal number');
