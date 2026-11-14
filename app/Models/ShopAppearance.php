<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAppearance extends Model
{
    protected $fillable = ['desktop_logo_path', 'mobile_logo_path', 'auth_image_paths'];

    protected $casts = ['auth_image_paths' => 'array'];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
