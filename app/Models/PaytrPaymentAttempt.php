<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
