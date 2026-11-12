<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShippingShipment;
use Illuminate\Support\Facades\DB;

/**
 * Only signed provider callbacks or authenticated admin GET detail calls may
 * advance fulfillment. They can never approve a pending payment.
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
            if ($locked->state !== 'submitted' || ! $locked->remote_id) return;
            // Time is provider's exact Y-m-d H:i:s string. Lexical ordering
            // avoids server timezone assumptions and repeated/out-of-order events.
            if ($locked->last_callback_time !== null && strcmp($time, $locked->last_callback_time) <= 0) return;
            $locked->update(['remote_status' => $status, 'last_callback_time' => $time]);
            $this->advanceOrder($locked, $status);
        }, 3);
    }

    public function syncDetails(ShippingShipment $shipment, array $remote): void
    {
        DB::transaction(function () use ($shipment, $remote) {
            $locked = ShippingShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if (!$locked->remote_id || (int)($remote['id'] ?? 0) !== $locked->remote_id) return;
            $status = (string)($remote['status'] ?? '');
            if (!in_array($status, self::STATUSES, true)) {
                throw new \RuntimeException('Sağlayıcının gönderi durumu tanınmıyor.');
            }
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
            // Carrier status can advance the paid-order delivery state,
            // but never mark an unpaid or cancelled order as fulfilled.
            $this->advanceOrder($locked, $status);
        }, 3);
    }

    private function advanceOrder(ShippingShipment $shipment, string $providerStatus): void
    {
        $target = match ($providerStatus) {
            'shipped', 'on_transit', 'at_delivery_center' => 'shipped',
            'in_courier' => 'out_for_delivery',
            'delivered' => 'completed',
            default => null,
        };
        if (!$target) return;

        $order = Order::whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
        $levels = ['preparing' => 0, 'shipped' => 1, 'out_for_delivery' => 2, 'completed' => 3];
        if (!array_key_exists($order->status, $levels)) return;

        $data = [];
        if ($levels[$target] > $levels[$order->status]) {
            $data['status'] = $target;
        }
        // A later GET can supply tracking data after a "delivered" callback;
        // update metadata without rolling the order status backwards.
        if ($levels[$target] >= 1) {
            $data['carrier_name'] = $shipment->carrier->name;
            if ($shipment->tracking_number) $data['tracking_number'] = $shipment->tracking_number;
            if ($shipment->tracking_link) $data['tracking_url'] = $shipment->tracking_link;
        }
        if ($data) $order->update($data);
    }
}
