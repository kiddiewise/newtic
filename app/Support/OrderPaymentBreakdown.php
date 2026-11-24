<?php

namespace App\Support;

use App\Models\Order;

/**
 * Read-only accounting view. Order totals remain the originally agreed
 * checkout amount; provider-confirmed additional collection is separated.
 */
final class OrderPaymentBreakdown
{
    public static function fromOrder(Order $order): array
    {
        $order->loadMissing(['paytrAttempts', 'paymentNotifications']);

        $paidAttempt = $order->payment_method === 'paytr'
            ? $order->paytrAttempts->first(
                fn ($attempt) => $attempt->status === 'success'
                    && $attempt->paid_total_cents !== null
                    && $attempt->paid_total_cents >= $attempt->amount_cents
            ) : null;

        $bankVerified = $order->payment_method === 'bank_transfer'
            && $order->paymentNotifications->contains(fn ($notice) => $notice->status === 'verified');

        $paidCents = $paidAttempt ? (int) $paidAttempt->paid_total_cents
            : ($bankVerified ? (int) $order->total_cents : null);

        return [
            'products_cents' => (int) $order->subtotal_cents,
            'shipping_cents' => (int) $order->shipping_cents,
            'order_total_cents' => (int) $order->total_cents,
            'provider_difference_cents' => $paidAttempt
                ? max(0, (int) $paidAttempt->paid_total_cents - (int) $order->total_cents)
                : 0,
            'paid_cents' => $paidCents,
            'payment_verified' => $paidCents !== null,
            'payment_method' => $order->payment_method,
        ];
    }
}
