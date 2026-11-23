<?php

namespace App\Console\Commands;

use App\Models\SeoSetting;
use App\Services\StoreInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Provider-independent, zero-write installation diagnostic.
 * Never print database passwords, app keys, tokens or customer information.
 * A successful first-install check is NOT approval for real payment or shipping.
 */
final class DiagnoseCustomerInstall extends Command
{
    protected $signature = 'newtic:doctor';
    protected $description = 'Run read-only post-install checks for any NEWTIC customer hosting provider.';

    public function handle(StoreInstaller $installer): int
    {
        $test = app()->environment('testing');
        $healthy = true;
        $checks = [
            'PHP 8.3+' => PHP_VERSION_ID >= 80300,
            'Uygulama şifreleme anahtarı' => filled(config('app.key')),
            'Güvenli uygulama ortamı' => $test || (
                app()->environment('production') && !config('app.debug')
            ),
            'HTTPS mağaza adresi' => $test || str_starts_with((string) config('app.url'), 'https://'),
            'Laravel yazma izinleri' => is_writable(storage_path('app'))
                && is_writable(storage_path('framework'))
                && is_writable(base_path('bootstrap/cache')),
            'Uygulama bağımlılıkları' => file_exists(base_path('vendor/autoload.php')),
            'Ön yüz dosyaları' => $test || (
                is_file(public_path('build/manifest.json'))
                && is_file(public_path('build/newtic-revision.txt'))
            ),
            'Normal sipariş modu' => $test || config('newtic.checkout_test_mode', true) === false,
            'PAYTR canlı tahsilatı kapalı' => !config('newtic.paytr_card_payments_enabled', false)
                && !config('newtic.paytr_webhooks_enabled', false),
            'Gerçek kargo kapalı' => !config('newtic.shipping_dummy_enabled', false),
            'Toplu e-posta gönderimi kapalı' => !config('newtic.marketing_live_enabled', false),
        ];

        try {
            $checks['MySQL bağlantısı ve kurulum tablosu'] = (
                $test || config('database.default') === 'mysql'
            ) && DB::connection()->getPdo()
                && Schema::hasTable('newtic_installations');
        } catch (Throwable) {
            $checks['MySQL bağlantısı ve kurulum tablosu'] = false;
        }

        try {
            $checks['İlk mağaza kurulumu tamamlandı'] = $installer->installed();
        } catch (Throwable) {
            $checks['İlk mağaza kurulumu tamamlandı'] = false;
        }
        try {
            // The diagnostic is read-only, including before initial setup.
            $checks['SEO kontrollü başlangıç'] = (bool) SeoSetting::query()
                ->whereKey(1)->value('noindex');
        } catch (Throwable) {
            $checks['SEO kontrollü başlangıç'] = false;
        }

        foreach ($checks as $label => $passed) {
            $success = (bool) $passed;
            $healthy = $healthy && $success;
            $this->line(($success ? '[OK] ' : '[HATA] ').$label);
        }

        $this->newLine();
        if (!$healthy) {
            $this->error('NEWTIC_INSTALL_DIAGNOSTIC_FAILED: Eksikleri düzeltmeden mağazayı açmayın.');
            return self::FAILURE;
        }

        $this->info('NEWTIC_INSTALL_DIAGNOSTIC_OK: İlk kurulum kontrolleri başarılı.');
        $this->warn('Önemli: Canlı ödeme, kargo, SMTP, cron, yedekleme ve üçüncü taraf servisleri bu test onaylamaz.');
        return self::SUCCESS;
    }
}
