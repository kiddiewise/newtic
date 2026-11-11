<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

final class PaytrPaymentAttempt extends Model
{
    protected $fillable = [
        'order_id', 'merchant_oid', 'amount_cents', 'currency',
        'status', 'is_test', 'paid_total_cents',
        'failed_reason_code', 'failed_reason_message', 'processed_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_total_cents' => 'integer',
        'is_test' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public static function createForOrder(Order $order): self
    {
        if ($order->payment_method !== 'paytr' || $order->status !== 'pending_payment'
            || ! $order->is_test || $order->total_cents <= 0
            || $order->currency !== 'TRY') {
            throw new RuntimeException('Only pending PAYTR test orders can create attempts.');
        }

        // Independent alphanumeric attempt ID: customer-facing PK-... order
        // numbers include dashes, which PAYTR merchant_oid does not allow.
        return self::query()->create([
            'order_id' => $order->id,
            'merchant_oid' => 'NT'.Str::upper(Str::random(30)),
            'amount_cents' => $order->total_cents,
            'currency' => 'TRY',
            'status' => 'pending',
            'is_test' => true,
        ]);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
