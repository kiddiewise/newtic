<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAppearance extends Model
{
    protected $fillable = ['desktop_logo_path', 'mobile_logo_path'];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
