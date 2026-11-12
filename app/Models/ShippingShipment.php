<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShippingShipment extends Model
{
    protected $fillable = [
        'order_id','shipping_provider_id','shipping_carrier_id','shipping_warehouse_id',
        'remote_id','state','remote_status','barcode','tracking_number',
        'tracking_link','desi','kg','last_callback_time',
    ];
    protected $casts = ['remote_id' => 'integer', 'desi' => 'decimal:2', 'kg' => 'decimal:2'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'shipping_provider_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'shipping_carrier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ShippingWarehouse::class, 'shipping_warehouse_id');
    }
}
