<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShippingCarrier extends Model
{
    protected $fillable = ['shipping_provider_id','remote_id','name','carrier_code','is_dummy'];
    protected $casts = ['is_dummy' => 'boolean', 'remote_id' => 'integer'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'shipping_provider_id');
    }
}
