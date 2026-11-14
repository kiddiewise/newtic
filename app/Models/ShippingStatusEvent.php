<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShippingStatusEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['shipping_shipment_id', 'status', 'source', 'provider_time', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ShippingShipment::class, 'shipping_shipment_id');
    }
}
