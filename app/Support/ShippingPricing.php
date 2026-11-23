<?php

namespace App\Support;

use App\Models\StoreSetting;

/**
 * A buyer pays the merchant at checkout. The merchant is always the carrier's
 * billing counterparty; this calculation is never sent as a courier COD charge.
 */
final class ShippingPricing
{
    public static function forSubtotal(int $subtotalCents, StoreSetting $settings): array
    {
        $thresholdCents = max(0, (int) round((float) $settings->free_shipping_threshold * 100));
        $feeCents = max(0, (int) round((float) $settings->shipping_fee * 100));
        $remainingCents = max(0, $thresholdCents - $subtotalCents);
        $shippingCents = $subtotalCents > 0 && $remainingCents > 0 ? $feeCents : 0;

        return [
            'threshold_cents' => $thresholdCents,
            'shipping_fee_cents' => $feeCents,
            'remaining_cents' => $remainingCents,
            'shipping_cents' => $shippingCents,
            'free_shipping' => $subtotalCents > 0 && $remainingCents === 0,
            'total_cents' => $subtotalCents + $shippingCents,
        ];
    }
}
