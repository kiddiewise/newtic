<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShippingShipment extends Model
{
    protected $fillable = [
        'order_id','shipping_provider_id','shipping_carrier_id','shipping_warehouse_id',
        'remote_id','state','remote_status','barcode','tracking_number',
        'tracking_link','desi','kg','last_callback_time',
        'last_sync_attempt_at','last_synced_at','last_sync_failed_at','sync_failure_count',
        'exception_acknowledged_at','exception_acknowledged_status','exception_acknowledged_by','exception_note',
    ];
    protected $casts = [
        'remote_id' => 'integer','desi' => 'decimal:2','kg' => 'decimal:2',
        'last_sync_attempt_at' => 'datetime','last_synced_at' => 'datetime',
        'last_sync_failed_at' => 'datetime','exception_acknowledged_at' => 'datetime',
        'sync_failure_count' => 'integer',
    ];

    public function exceptionReviews(): HasMany
    {
        return $this->hasMany(ShippingExceptionReview::class)->orderByDesc('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShippingStatusEvent::class)->orderBy('id');
    }

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
