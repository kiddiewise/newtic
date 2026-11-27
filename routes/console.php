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


// Offline-only: read the exact supplier or official GIB design from a local
// operator-supplied file. No credentials, HTTP, database changes or fiscal number.
// Save only in private storage, refusing overwrite of an existing candidate.
Artisan::command('edm:prepare-second-smoke {template} {--date=}',function () {
    try {
        $path=(string)$this->argument('template');
        if (!is_file($path) || filesize($path)>512*1024) throw new \DomainException('SECOND_XSLT_INVALID');
        $template=file_get_contents($path);
        if (!is_string($template)) throw new \DomainException('SECOND_XSLT_INVALID');
        $date=(string)($this->option('date')?:now('Europe/Istanbul')->toDateString());
        $r=\App\Services\Invoices\EdmSecondSandboxFixture::prepare($date,$template);
        $dir=storage_path('app/private/edm-rehearsals');
        if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new \RuntimeException('SECOND_STORAGE_UNAVAILABLE');
        $file=$dir.'/'.$r['uuid'].'-'.$r['xml_sha256'].'.xml';
        $handle=fopen($file,'xb');
        if (!$handle) throw new \RuntimeException('SECOND_FILE_ALREADY_EXISTS');
        try {
            chmod($file,0600);
            if (fwrite($handle,$r['xml'])!==strlen($r['xml'])) throw new \RuntimeException('SECOND_WRITE_FAILED');
        } finally { fclose($handle); }
        unset($r['xml'],$r['fields']);
        $this->line(json_encode($r,JSON_THROW_ON_ERROR));
        return 0;
    } catch (\Throwable $e) {
        $this->error('SECOND_PREPARATION_FAILED');
        return 1;
    }
})->purpose('Prepare private second sandbox XML offline, never submit');


// Only an SSH operator on the explicitly named staging site may export the
// encrypted trial descriptions to a private, owner-readable local file.
// Never include contents in CLI output or CI logs.
Artisan::command('edm:private-fault-evidence {uuid}',function () {
    try {
        $uuid=(string)$this->argument('uuid');
        if (rtrim((string)config('app.url'),'/')!==\App\Services\Invoices\EdmSandboxSmokeFixture::STAGING_URL
            || !in_array($uuid,[\App\Services\Invoices\EdmSandboxSmokeFixture::UUID,\App\Services\Invoices\EdmSecondSandboxFixture::UUID],true)) {
            throw new \DomainException('FAULT_SCOPE');
        }
        $row=\Illuminate\Support\Facades\DB::table('edm_sandbox_trials')->where('uuid',$uuid)->first();
        if (!$row || !$row->fault_detail_ciphertext) { $this->line('FAULT_DETAILS_NOT_RECORDED'); return 1; }
        $plain=\Illuminate\Support\Facades\Crypt::decryptString($row->fault_detail_ciphertext);
        $dir=storage_path('app/private/edm-rehearsals');
        if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new \RuntimeException('FAULT_STORAGE');
        $file=$dir.'/'.$uuid.'-fault-evidence.json';
        $handle=fopen($file,'xb');
        if (!$handle) throw new \RuntimeException('FAULT_FILE_EXISTS');
        try {
            chmod($file,0600);
            if (fwrite($handle,$plain)!==strlen($plain)) throw new \RuntimeException('FAULT_WRITE');
        } finally { fclose($handle); }
        $this->line('FAULT_DETAILS_WRITTEN_TO_PRIVATE_STORAGE');
        return 0;
    } catch (\Throwable) {
        $this->error('FAULT_EVIDENCE_UNAVAILABLE');
        return 1;
    }
})->purpose('Export redacted EDM sandbox fault descriptions privately, never log plaintext');


// Explicit release-only numbered sandbox trial. Template bytes on stdin,
// pinned by the fixture; no customer payload, no SendInvoice, no retries.
Artisan::command('edm:smoke-load-second {date} {sha256} {confirmation}', function () {
    try {
        $template=stream_get_contents(STDIN,512*1024+1);
        if (!is_string($template)||strlen($template)>512*1024) throw new \DomainException('SECOND_TEMPLATE_INVALID');
        $result=app(\App\Services\Invoices\EdmTestClient::class)->runSecondControlledSmoke(
            (string)$this->argument('date'),$template,(string)$this->argument('sha256'),(string)$this->argument('confirmation'));
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));
        return 0;
    } catch (\Throwable $e) {
        $safe=$e instanceof \DomainException&&preg_match('/^SECOND_[A-Z_]+$/D',$e->getMessage())?$e->getMessage():'SECOND_EXECUTION_UNCONFIRMED';
        $this->error($safe);
        return 1;
    }
})->purpose('One explicitly authorized numbered EDM sandbox draft and private readbacks');


Artisan::command('invoices:process',function(){
    $this->line(json_encode(app(\App\Services\Invoices\InvoiceDispatch::class)->process(),JSON_THROW_ON_ERROR));
    return 0;
})->purpose('Process explicitly requested invoice records and optional completion rule');
Schedule::command('invoices:process')->everyMinute()->withoutOverlapping(15);

// Private pinned template provisioning for staging only. No network calls.
Artisan::command('edm:install-order-template',function(){
    if (!\App\Services\Invoices\InvoiceDispatch::staging()) return 1;
    try {
        $bytes=stream_get_contents(STDIN,512*1024+1);
        \App\Services\Invoices\EdmSecondSandboxFixture::templateKind($bytes);
        $path=\App\Services\Invoices\InvoiceDispatch::templatePath();
        $dir=dirname($path);
        if (!is_dir($dir)&&!mkdir($dir,0700,true)) return 1;
        if (file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)) return 1;
        chmod($path,0600);
        $this->line('ORDER_TEMPLATE_VERIFIED');
        return 0;
    } catch (\Throwable) { $this->error('ORDER_TEMPLATE_INVALID');return 1; }
})->purpose('Install pinned private invoice design on staging');


// Read-only fixed vocabulary diagnostic for public CI. Never prints raw EDM descriptions.
Artisan::command('edm:classify-stored-load-fault',function(){
    $a=\App\Models\EdmSetting::query()->find(1);
    $uuid=\App\Services\Invoices\EdmSecondSandboxFixture::UUID;
    $d=\App\Services\Invoices\EdmStoredFault::read($uuid,$a);
    $this->line(json_encode(['operation'=>'stored_second_fault','hints'=>\App\Services\Invoices\EdmStoredFault::classify($d)],JSON_THROW_ON_ERROR));
    return 0;
})->purpose('Classify encrypted second trial error without exposing provider text');
