<?php

namespace App\Services;

use App\Models\ShippingProvider;
use App\Models\ShippingShipment;
use App\Support\ShippingTestAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Read-only recovery path for missed callbacks, including canceled/failed/draft
 * states for which Kargo Entegratör does not send per-shipment notifications.
 *
 * Stage 1 is intentionally Dummy Kargo only and never writes to provider APIs.
 */
final class ShippingReconciliationService
{
    public function __construct(
        private readonly KargoIntegratorClient $client,
        private readonly ShippingShipmentManager $manager,
    ) {}

    public function run(int $limit = 20, bool $force = false): array
    {
        if (!ShippingTestAccess::safeEnvironment()) {
            throw new RuntimeException('Otomatik kargo sorgulama yalnızca NEWTIC test ortamında çalışır.');
        }
        $provider = ShippingProvider::where('provider', 'kargoentegrator')->first();
        if (!$provider?->hasKey()) {
            return ['checked' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0];
        }

        // A single schedule tick has a bounded number of API reads.
        $rows = ShippingShipment::query()
            ->where('shipping_provider_id', $provider->id)
            ->where('state', 'submitted')->whereNotNull('remote_id')
            ->whereHas('carrier', fn($q) => $q->where('is_dummy', true))
            ->when(!$force, fn($q) => $q->where(fn($fresh) =>
                $fresh->whereNull('last_sync_attempt_at')
                    ->orWhere('last_sync_attempt_at', '<=', now()->subMinutes(10))))
            ->orderBy('last_sync_attempt_at')->orderBy('id')
            ->limit(max(1, min($limit, 50)))->get();

        $result = ['checked' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($rows as $shipment) {
            $state = $this->syncOne($shipment);
            if ($state === 'skipped') {
                $result['skipped']++;
                continue;
            }
            $result['checked']++;
            $result[$state]++;
        }
        return $result;
    }

    /**
     * Return 'updated', 'failed', or 'skipped'. This operation only performs
     * GET /api/shipments/{id}; it must never create, delete, or edit a parcel.
     */
    public function syncOne(ShippingShipment $shipment): string
    {
        if (!ShippingTestAccess::safeEnvironment()) return 'skipped';

        $lock = Cache::lock('newtic-shipment-read-'.$shipment->id, 35);
        if (!$lock->get()) return 'skipped';

        try {
            $locked = ShippingShipment::with(['carrier','provider'])
                ->find($shipment->id);
            if (!$locked || $locked->state !== 'submitted'
                || !$locked->remote_id || !$locked->carrier?->is_dummy
                || $locked->provider?->provider !== 'kargoentegrator'
                || !$locked->provider->hasKey()) return 'skipped';

            $locked->forceFill(['last_sync_attempt_at' => now()])->save();
            try {
                $remote = $this->client->shipment($locked->provider, $locked->remote_id);
                $this->manager->syncDetails($locked, $remote);
                return 'updated';
            } catch (\Throwable) {
                // No upstream error messages or keys are persisted.
                DB::transaction(function () use ($locked): void {
                    $row = ShippingShipment::whereKey($locked->id)
                        ->lockForUpdate()->first();
                    if (!$row) return;
                    $row->update([
                        'last_sync_attempt_at' => now(),
                        'last_sync_failed_at' => now(),
                        'sync_failure_count' => min(65535, $row->sync_failure_count + 1),
                    ]);
                }, 3);
                return 'failed';
            }
        } finally {
            $lock->release();
        }
    }
}
