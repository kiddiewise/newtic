<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GoogleMerchantSyncItem extends Model
{
    protected $fillable = [
        'product_id', 'merchant_id', 'offer_id', 'payload_hash',
        'status', 'message', 'last_synced_at',
    ];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}