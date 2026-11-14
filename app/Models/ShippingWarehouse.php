<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShippingWarehouse extends Model
{
    protected $fillable = ['shipping_provider_id','remote_id','name'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'shipping_provider_id');
    }
}
