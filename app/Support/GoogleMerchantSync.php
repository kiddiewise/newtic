<?php

namespace App\Support;

use App\Models\GoogleMerchantSetting;
use App\Models\GoogleMerchantSyncItem;
use App\Models\Product;
use Illuminate\Support\Str;
use RuntimeException;

final class GoogleMerchantSync
{
    public function __construct(private GoogleMerchantApi $api)
    {
    }

    public function begin(GoogleMerchantSetting $settings): array
    {
        if (!$settings->isReady()) {
            throw new RuntimeException('Önce Google hesabını seçin ve API veri kaynağını oluşturun.');
        }
        $settings->update([
            'sync_status' => 'running',
            'sync_cursor' => 0,
            'sync_sent' => 0,
            'sync_unchanged' => 0,
            'sync_removed' => 0,
            'sync_errors' => 0,
            'sync_total' => Product::query()->count() +
                GoogleMerchantSyncItem::query()->where('merchant_id', $settings->merchant_id)->whereNull('product_id')->count(),
            'last_error' => null,
        ]);

        return $this->progress($settings->fresh(), false);
    }

    /** @return array<string, mixed> */
    public function step(GoogleMerchantSetting $settings, int $limit = 4): array
    {
        if (!$settings->isReady() || $settings->sync_status !== 'running') {
            throw new RuntimeException('Başlatılmış bir Google senkronizasyonu yok.');
        }

        $limit = max(1, min(8, $limit));
        $products = Product::query()->with(['category', 'images'])
            ->where('id', '>', $settings->sync_cursor)->orderBy('id')->limit($limit)->get();

        // Token acquisition is deferred until a remote write is actually needed.
        $token = null;
        $accessToken = function () use (&$token, $settings): string {
            return $token ??= $this->api->accessToken($settings);
        };

        foreach ($products as $product) {
            $offerId = GoogleMerchantProduct::offerId($product);
            $item = GoogleMerchantSyncItem::query()
                ->where('merchant_id', $settings->merchant_id)->where('offer_id', $offerId)->first();
            $eligible = GoogleMerchantProduct::eligible($product);

            try {
                if ($eligible) {
                    $payload = GoogleMerchantProduct::payload($product);
                    $hash = GoogleMerchantProduct::hash($product);
                    if ($item && $item->status === 'synced' && $item->payload_hash === $hash) {
                        $settings->sync_unchanged++;
                    } else {
                        $this->api->insert($accessToken(), $settings, $payload);
                        GoogleMerchantSyncItem::query()->updateOrCreate([
                            'merchant_id' => $settings->merchant_id, 'offer_id' => $offerId,
                        ], [
                            'product_id' => $product->id, 'payload_hash' => $hash,
                            'status' => 'synced', 'message' => null,
                            'last_synced_at' => now(),
                        ]);
                        $settings->sync_sent++;
                    }
                } elseif ($item && $item->status !== 'removed' && ($item->payload_hash || $item->status === 'synced')) {
                    $this->api->delete($accessToken(), $settings, $offerId);
                    $item->delete();
                    $settings->sync_removed++;
                } else {
                    if ($item) $item->delete();
                    $settings->sync_unchanged++;
                }
            } catch (\Throwable $exception) {
                GoogleMerchantSyncItem::query()->updateOrCreate([
                    'merchant_id' => $settings->merchant_id, 'offer_id' => $offerId,
                ], [
                    'product_id' => $product->id, 'status' => 'error',
                    'message' => Str::limit($exception->getMessage(), 280, ''),
                ]);
                $settings->sync_errors++;
                $settings->last_error = Str::limit('Ürün '.$product->id.': '.$exception->getMessage(), 380, '');
            }

            $settings->sync_cursor = $product->id;
        }

        if ($products->isEmpty()) {
            // Rows for deleted NEWTIC products remain as tombstones, so Google
            // products don't survive deletion from the local catalogue.
            $orphans = GoogleMerchantSyncItem::query()
                ->where('merchant_id', $settings->merchant_id)
                ->whereNull('product_id')->limit($limit)->get();
            foreach ($orphans as $item) {
                try {
                    $this->api->delete($accessToken(), $settings, $item->offer_id);
                    $item->delete();
                    $settings->sync_removed++;
                } catch (\Throwable $exception) {
                    $item->update(['status' => 'error', 'message' => Str::limit($exception->getMessage(), 280, '')]);
                    $settings->sync_errors++;
                    $settings->last_error = 'Silinen ürünün Google kaydı kaldırılamadı ('.$item->id.').';
                    // Failed tombstones are retried on the next full sync.
                    break;
                }
            }

            if ($orphans->isEmpty() || $settings->sync_errors > 0) {
                $settings->sync_status = $settings->sync_errors > 0 ? 'partial' : 'completed';
                $settings->last_synced_at = now();
            }
        }

        $settings->save();
        return $this->progress($settings, $settings->sync_status !== 'running');
    }

    /** @return array<string, mixed> */
    public function progress(GoogleMerchantSetting $settings, bool $done): array
    {
        return [
            'done' => $done,
            'status' => $settings->sync_status,
            'sent' => $settings->sync_sent,
            'unchanged' => $settings->sync_unchanged,
            'removed' => $settings->sync_removed,
            'errors' => $settings->sync_errors,
            'total' => $settings->sync_total,
            'cursor' => $settings->sync_cursor,
            'message' => $settings->sync_errors ? 'Bazı ürünler aktarılamadı. Hataları ve Google hesap ayarlarını inceleyin.' : '',
        ];
    }
}