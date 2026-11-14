<?php

namespace App\Console\Commands;

use App\Services\ShippingReconciliationService;
use App\Support\ShippingTestAccess;
use Illuminate\Console\Command;

final class ReconcileShippingShipments extends Command
{
    protected $signature = 'shipping:sync {--limit=20 : Max. number of provider GET requests per run}';
    protected $description = 'Safely reconcile Dummy Kargo shipment statuses (read-only GET; staging only)';

    public function handle(ShippingReconciliationService $service): int
    {
        if (!ShippingTestAccess::safeEnvironment()) {
            $this->warn('Kargo sorgulama bu alan adında devre dışı.');
            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 50) {
            $this->error('--limit must be an integer between 1 and 50.');
            return self::INVALID;
        }
        $result = $service->run($limit);
        $this->line(sprintf(
            'checked=%d updated=%d failed=%d skipped=%d',
            $result['checked'], $result['updated'], $result['failed'], $result['skipped']
        ));
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
