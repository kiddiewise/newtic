<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShippingExceptionReview extends Model
{
    public $timestamps = false;
    protected $fillable = ['shipping_shipment_id','user_id','remote_status','reason','created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ShippingShipment::class, 'shipping_shipment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
