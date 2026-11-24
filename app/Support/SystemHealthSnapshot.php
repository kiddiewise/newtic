<?php

namespace App\Support;

use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Models\ShippingProvider;
use App\Models\ShippingShipment;
use App\Models\ShippingStatusEvent;
use App\Models\SystemHealthMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Provider-agnostic, read-only admin diagnostics; no external API calls. */
final class SystemHealthSnapshot
{
    private const TASKS = [
        'email_process' => ['E-posta kuyruğu', 'Her dakika', 5],
        'marketing_process' => ['Kampanya işlemleri', 'Her dakika', 5],
        'shipping_sync' => ['Kargo kontrolü', '15 dakikada bir', 45],
        'merchant_sync' => ['Google Merchant', '15 dakikada bir', 45],
        'paytr_reconcile' => ['Bekleyen PayTR ödeme kontrolü', '5 dakikada bir', 15],
        'bank_expiry' => ['Ödenmemiş havale kontrolü', '15 dakikada bir', 45],
        'analytics_prune' => ['Analitik veri temizliği', 'Her gün 03.25', 1560],
        'photo_prune' => ['Sahipsiz fotoğraf temizliği', 'Her gün 03.45', 1560],
    ];

    public function collect(): array
    {
        $monitors = SystemHealthMonitor::query()->get()->keyBy('key');
        $checks = [
            $this->scheduler($monitors),
            $this->backup($monitors),
            $this->paytr($monitors),
            $this->shipping($monitors),
            $this->disk(),
            $this->database(),
        ];
        $logs = $this->logs();
        $checks[] = $this->item(
            'error_logs', 'Hata kayıtları', $logs['status'],
            $logs['summary'], $logs['latest']
        );

        $tasks = [];
        foreach (self::TASKS as $key => [$label, $cadence, $minutes]) {
            $record = $monitors->get($key);
            $status = $this->timedStatus($record, $minutes);
            $tasks[] = [
                'label' => $label,
                'cadence' => $cadence,
                'status' => $status,
                'last_at' => $record?->last_checked_at,
                'detail' => $record?->status === 'error'
                    ? 'Son çalıştırma başarısız'
                    : ($record ? 'Komut çalıştırıldı' : 'Henüz çalışma kaydı yok'),
            ];
        }

        $version = $this->revision();
        return [
            'checks' => $checks,
            'counts' => [
                'ok' => count(array_filter($checks, fn ($c) => $c['status'] === 'ok')),
                'warning' => count(array_filter($checks, fn ($c) => $c['status'] === 'warning')),
                'unknown' => count(array_filter($checks, fn ($c) => $c['status'] === 'unknown')),
                'disabled' => count(array_filter($checks, fn ($c) => $c['status'] === 'disabled')),
            ],
            'tasks' => $tasks,
            'logs' => $logs['entries'],
            'version' => $version,
            'runtime' => [
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'queue' => (string) config('queue.default', 'unknown'),
                'cache' => (string) config('cache.default', 'unknown'),
            ],
            'security' => $this->security(),
            'checked_at' => now(),
        ];
    }

    private function item(string $key, string $label, string $status, string $summary, mixed $time = null, string $hint = ''): array
    {
        return compact('key', 'label', 'status', 'summary', 'hint') + ['last_at' => $time];
    }

    private function timedStatus(?SystemHealthMonitor $record, int $minutes): string
    {
        if (!$record?->last_checked_at) return 'unknown';
        if ($record->status === 'error' || $record->last_checked_at->lt(now()->subMinutes($minutes))) {
            return 'warning';
        }
        return 'ok';
    }

    private function scheduler(Collection $monitors): array
    {
        $record = $monitors->get('scheduler');
        return $this->item('scheduler', 'Zamanlanmış görevler',
            $this->timedStatus($record, 5),
            !$record ? 'Laravel zamanlayıcısından henüz sinyal alınmadı'
                : ($this->timedStatus($record, 5) === 'warning'
                    ? 'Zamanlayıcı beklenen aralıkta çalışmıyor'
                    : 'Laravel zamanlayıcısı çalışıyor'),
            $record?->last_success_at,
            'Sunucunun her dakika php artisan schedule:run çalıştırması gerekir.');
    }

    private function backup(Collection $monitors): array
    {
        $record = $monitors->get('backup');
        return $this->item('backup', 'Son başarılı yedek',
            !$record ? 'unknown' : ($record->status === 'error' ? 'warning' : 'ok'),
            !$record ? 'Doğrulanmış yedekleme kaydı yok'
                : ($record->status === 'error' ? 'Son yedekleme başarısız bildirildi' : 'Yedekleme tamamlandı olarak bildirildi'),
            $record?->last_success_at,
            'Gerçek yedekleme işlemi başarıyla bitince CLI üzerinden kayıt gönderilir. NEWTIC kendi başına yedek alındığını varsaymaz.');
    }

    private function paytr(Collection $monitors): array
    {
        $settings = PaytrSetting::query()->find(1);
        $enabled = PaytrTestAccess::available($settings);
        $record = $monitors->get('paytr_callback');
        $processed = PaytrPaymentAttempt::query()->whereNotNull('processed_at')
            ->orderByDesc('processed_at')->first();
        $pending = PaytrPaymentAttempt::query()->where('status', 'pending')->count();
        $observed = $record?->last_success_at;
        if ($processed?->processed_at && (!$observed || $processed->processed_at->gt($observed))) {
            $observed = $processed->processed_at;
        }
        $rejectedLater = $record?->status === 'error' && $record->last_failure_at
            && (!$observed || $record->last_failure_at->gt($observed));

        $state = !$enabled ? 'disabled' : ($rejectedLater ? 'warning' : ($observed ? 'ok' : 'unknown'));
        $summary = !$enabled ? 'Bu kurulumda PAYTR bildirim kabulü etkin değil'
            : ($rejectedLater ? 'En son PAYTR bildirimi reddedildi'
                : ($observed ? 'İmzalı ödeme sonucu işlendi' : 'Henüz doğrulanmış bildirim kaydı yok'));
        return $this->item('paytr', 'PAYTR bildirimleri', $state, $summary, $observed,
            $pending.' bekleyen işlem · Müşterinin ödeme başarısızlığı tek başına webhook hatası değildir.');
    }

    private function shipping(Collection $monitors): array
    {
        $provider = ShippingProvider::query()->where('provider', 'kargoentegrator')->first();
        if (!$provider?->hasKey() || !$provider->is_enabled || !ShippingTestAccess::allowed()) {
            return $this->item('shipping', 'Kargo senkronizasyonu', 'disabled',
                'Bu kurulumda kargo bağlantısı etkin değil');
        }
        $total = ShippingShipment::query()->count();
        $issues = ShippingShipment::query()
            ->where(fn ($q) => $q->where('state', 'uncertain')->orWhere('sync_failure_count', '>=', 2))
            ->count();
        $lastSync = ShippingShipment::query()->whereNotNull('last_synced_at')->max('last_synced_at');
        $lastCallback = ShippingStatusEvent::query()->where('source', 'callback')
            ->orderByDesc('created_at')->value('created_at');
        $last = $lastSync ? Carbon::parse($lastSync) : null;
        if ($lastCallback && (!$last || Carbon::parse($lastCallback)->gt($last))) {
            $last = Carbon::parse($lastCallback);
        }

        $monitor = $monitors->get('shipping_sync');
        $state = $issues ? 'warning' : ($last ? 'ok' : 'unknown');
        if ($monitor?->status === 'error' && $monitor->last_failure_at
            && (!$last || $monitor->last_failure_at->gt($last))) $state = 'warning';
        return $this->item('shipping', 'Kargo senkronizasyonu', $state,
            $issues ? $issues.' gönderi teknik inceleme bekliyor'
                : ($last ? 'Kargo verisi alındı' : 'Henüz başarılı eşitleme veya bildirim yok'),
            $last, $total.' gönderi kaydı · '.$issues.' teknik sorun');
    }

    private function database(): array
    {
        try {
            DB::select('SELECT 1');
            return $this->item('database', 'Veritabanı', 'ok', 'Veritabanı sorgusu başarılı', now());
        } catch (Throwable $exception) {
            return $this->item('database', 'Veritabanı', 'warning', 'Veritabanı sorgusu başarısız');
        }
    }

    private function disk(): array
    {
        $path = storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total === false || $free === false || $total <= 0) {
            return $this->item('disk', 'Disk kullanımı', 'unknown', 'Yerel depolama bilgisi alınamıyor',
                null, 'Uzak nesne depolama bu hesaba dahil değildir.');
        }
        $used = max(0, min(100, round((1 - $free / $total) * 100, 1)));
        return $this->item('disk', 'Disk kullanımı', $used >= 90 ? 'warning' : 'ok',
            '%'.number_format($used, 1, ',', '.').' dolu · '.number_format($free / 1073741824, 1, ',', '.').' GB boş',
            now(), 'Yalnızca Laravel sunucusunun yerel dosya sistemi ölçülür.');
    }

    /**
     * Read-only security posture, with safe booleans only. Never render an
     * application key or other secret or suggest a remediation that mutates
     * the live configuration from an HTTP request.
     */
    private function security(): array
    {
        $secureSession = config('session.secure');
        $checks = [
            ['label' => 'HTTPS', 'status' => request()->isSecure() ? 'ok' : 'warning',
                'detail' => request()->isSecure() ? 'Yönetim bağlantısı HTTPS kullanıyor' : 'HTTPS algılanmadı; ters proxy ayarlarını inceleyin'],
            ['label' => 'Hata ayıklama', 'status' => config('app.debug', false) ? 'warning' : 'ok',
                'detail' => config('app.debug', false) ? 'APP_DEBUG açık; canlı ortamda kapatılmalı' : 'Detaylı hata ekranları kapalı'],
            ['label' => 'Şifreleme anahtarı', 'status' => filled(config('app.key')) ? 'ok' : 'warning',
                'detail' => filled(config('app.key')) ? 'Uygulama anahtarı mevcut' : 'Uygulama anahtarı yapılandırılmamış'],
            ['label' => 'Güvenli oturum çerezi',
                'status' => $secureSession === null ? 'unknown' : ($secureSession ? 'ok' : 'warning'),
                'detail' => $secureSession === null ? 'Çerez güvenlik ayarı sunucu tarafından belirleniyor'
                    : ($secureSession ? 'HTTPS-Only oturum çerezi etkin' : 'Oturum çerezinde secure bayrağı kapalı')],
            ['label' => 'HttpOnly oturum çerezi', 'status' => config('session.http_only', true) ? 'ok' : 'warning',
                'detail' => config('session.http_only', true) ? 'JavaScript ile oturum çerezine erişim engelli' : 'HttpOnly bayrağı etkin değil'],
        ];
        return $checks;
    }

    private function revision(): string
    {
        foreach ([public_path('build/newtic-revision.txt'), base_path('.newtic-release-revision')] as $file) {
            if (!is_file($file) || !is_readable($file)) continue;
            $sha = trim((string) @file_get_contents($file));
            if (preg_match('/^[a-f0-9]{7,40}$/iD', $sha)) return substr($sha, 0, 12);
        }
        return 'Sürüm kaydı bulunamadı';
    }

    /**
     * Only timestamps and severity from a bounded tail of the local Laravel
     * log. Never send stack traces, log bodies or request metadata to a browser.
     */
    private function logs(): array
    {
        $paths = glob(storage_path('logs/laravel*.log')) ?: [];
        $paths = array_values(array_filter($paths, fn ($path) => is_file($path) && is_readable($path)));
        usort($paths, fn ($a, $b) => (int) @filemtime($b) <=> (int) @filemtime($a));
        $entries = [];
        foreach (array_slice($paths, 0, 2) as $path) {
            $handle = @fopen($path, 'rb');
            if (!$handle) continue;
            $size = @filesize($path);
            if (is_int($size) && $size > 262144) fseek($handle, -262144, SEEK_END);
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^\[([^\]]{10,40})\]\s+[\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/i', $line, $matches)) {
                    $entries[] = ['at' => $matches[1], 'level' => strtoupper($matches[2])];
                }
            }
            fclose($handle);
        }
        usort($entries, fn ($a, $b) => strcmp($b['at'], $a['at']));
        $entries = array_slice($entries, 0, 8);
        $latest = $entries[0]['at'] ?? null;
        return [
            'entries' => $entries,
            'status' => $entries ? 'warning' : ($paths ? 'ok' : 'unknown'),
            'summary' => $entries ? count($entries).' son hata kaydı (içerik gizli)'
                : ($paths ? 'Okunabilen log kesitinde kritik hata yok' : 'Yerel Laravel log dosyası bulunamadı'),
            'latest' => $latest,
        ];
    }
}
