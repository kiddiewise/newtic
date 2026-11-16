<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MerchantFooterMark extends Model
{
    protected $table = 'merchant_footer_marks';

    public const TYPES = [
        'payment' => 'Ödeme markası',
        'trust' => 'Doğrulanmış güven / üyelik işareti',
        'app' => 'Resmi mobil uygulama bağlantısı',
    ];

    protected $fillable = [
        'type', 'title', 'image_path', 'verification_url',
        'is_active', 'use_authorized', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean', 'use_authorized' => 'boolean'];

    public static function visible(string $type): \Illuminate\Support\Collection
    {
        return static::query()->where('type', $type)
            ->where('is_active', true)->where('use_authorized', true)
            ->orderBy('sort_order')->orderBy('id')->limit(12)->get();
    }
}
