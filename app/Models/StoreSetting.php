<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = ['free_shipping_threshold', 'shipping_fee'];

    protected $casts = [
        'free_shipping_threshold' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->findOrFail(1);
    }
}
