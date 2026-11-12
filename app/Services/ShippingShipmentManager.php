<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\ShippingShipment;
use App\Support\ShippingStatus;
use Illuminate\Support\Facades\DB;

/**
 * Shipping is provider-authoritative. A newer signed carrier update may
 * correct a shipping stage backwards, but cannot change payment or stock.
 */
final class ShippingShipmentManager
{
    public const STATUSES = [
        'draft', 'non_processed', 'shipped', 'on_transit', 'at_delivery_center',
        'in_courier', 'delivered', 'on_return', 'returned', 'canceled', 'failed',
    ];

    public function acceptCallback(ShippingShipment $shipment, string $status, string $time): void
    {
        DB::transaction(function () use ($shipment, $status, $time) {
            $locked = ShippingShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== 'submitted' || !$locked->remote_id) return;
            // Preserve provider event order and reject duplicated or older events.
            if ($locked->last_callback_time !== null
                && strcmp($time, $locked->last_callback_time) <= 0) return;

            $changed = $locked->remote_status !== $status;
            $locked->update(['remote_status' => $status, 'last_callback_time' => $time]);
            if ($changed) {
                $locked->events()->create([
                    'status' => $status, 'source' => 'callback',
                    'provider_time' => $time, 'created_at' => now(),
                ]);
            }
            $this->syncCoarseOrder($locked, $status);
        }, 3);
    }

    public function syncDetails(ShippingShipment $shipment, array $remote): void
    {
        DB::transaction(function () use ($shipment, $remote) {
            $locked = ShippingShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== 'submitted' || !$locked->remote_id
                || (int)($remote['id'] ?? 0) !== $locked->remote_id) return;

            $status = (string)($remote['status'] ?? '');
            if (!ShippingStatus::valid($status)) {
                throw new \RuntimeException('Sağlayıcının gönderi durumu tanınmıyor.');
            }
            $changed = $locked->remote_status !== $status;
            $fields = ['remote_status' => $status];
            foreach (['barcode' => 'barcode','tracking_number' => 'tracking_number',
                'tracking_link' => 'tracking_link'] as $key => $dest) {
                if (!isset($remote[$key]) || !is_scalar($remote[$key])) continue;
                $value = trim((string)$remote[$key]);
                if ($dest === 'tracking_link'
                    && ($value === '' || !str_starts_with($value, 'https://')
                        || !filter_var($value, FILTER_VALIDATE_URL))) continue;
                $fields[$dest] = mb_substr($value, 0, $dest === 'tracking_link' ? 2000 : 255);
            }
            $locked->update($fields);
            if ($changed) {
                $locked->events()->create([
                    'status' => $status, 'source' => 'api_sync',
                    'created_at' => now(),
                ]);
            }
            $this->syncCoarseOrder($locked, $status);
        }, 3);
    }

    private function syncCoarseOrder(ShippingShipment $shipment, string $providerStatus): void
    {
        $target = ShippingStatus::coarseOrderStatus($providerStatus);
        $order = Order::whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();

        // Do not let shipment activity override payment/cancel/refund facts.
        if (!in_array($order->status, ['preparing','shipped','out_for_delivery','completed'], true)) {
            return;
        }

        $update = [];
        if ($target !== null && $target !== $order->status) {
            $update['status'] = $target;
        }
        // Copy tracking metadata even when the order was already at this stage.
        // Details can arrive in a later GET after a webhook with only a status.
        if (in_array($providerStatus, ['shipped','on_transit','at_delivery_center',
            'in_courier','delivered','on_return','returned'], true)) {
            $update['carrier_name'] = $shipment->carrier->name;
            if ($shipment->tracking_number) $update['tracking_number'] = $shipment->tracking_number;
            if ($shipment->tracking_link) $update['tracking_url'] = $shipment->tracking_link;
        }
        if (!$update) return;

        $previous = $order->status;
        $order->update($update);
        if ($previous !== $order->status) {
            OrderStatusChange::create([
                'order_id' => $order->id, 'user_id' => null,
                'from_status' => $previous, 'to_status' => $order->status,
                'source' => 'shipping_provider',
                'reason' => 'Kargo Entegratör: '.ShippingStatus::label($providerStatus),
                'created_at' => now(),
            ]);
        }
    }
}
