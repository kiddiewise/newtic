<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShippingProvider extends Model
{
    protected $fillable = ['provider', 'api_key', 'is_enabled', 'last_checked_at'];
    protected $hidden = ['api_key'];
    protected $casts = [
        'api_key' => 'encrypted',
        'is_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function hasKey(): bool
    {
        return filled($this->getRawOriginal('api_key'));
    }

    public function carriers(): HasMany
    {
        return $this->hasMany(ShippingCarrier::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(ShippingWarehouse::class);
    }
}
