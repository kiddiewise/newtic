<?php

namespace App\Console\Commands;

use App\Models\GoogleMerchantSetting;
use App\Support\GoogleMerchantSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class SyncGoogleMerchant extends Command
{
    protected $signature = 'merchant:sync {--steps=6 : Maximum number of batches per scheduled run}';
    protected $description = 'Synchronize Google Merchant Center items in short resumable batches.';

    public function handle(GoogleMerchantSync $sync): int
    {
        $settings = GoogleMerchantSetting::query()->find(1);
        if (!$settings || !$settings->auto_sync || !$settings->isReady()) {
            $this->info('Google otomatik senkronizasyon etkin değil.');
            return self::SUCCESS;
        }

        return Cache::lock('newtic_google_merchant_sync', 90)->get(function () use ($sync, $settings) {
            try {
                if ($settings->sync_status !== 'running') {
                    $sync->begin($settings);
                    $settings->refresh();
                }
                for ($i = 0; $i < min(10, max(1, (int) $this->option('steps'))); $i++) {
                    $result = $sync->step($settings);
                    $settings->refresh();
                    if ($result['done']) break;
                }
                $this->info('Google aktarım durumu: '.$settings->sync_status);
                return $settings->sync_errors ? self::FAILURE : self::SUCCESS;
            } catch (\Throwable $exception) {
                $this->error('Google aktarım hatası: '.$exception->getMessage());
                return self::FAILURE;
            }
        }) ?? self::SUCCESS;
    }
}