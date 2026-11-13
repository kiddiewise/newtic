<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SeoSetting extends Model
{
    public const OTHER_PAGES = [
        'search' => 'Arama sayfası',
        'cart' => 'Sepet',
        'login' => 'Giriş',
        'register' => 'Üye ol',
        'wishlist' => 'Favoriler',
        'checkout' => 'Ödeme',
        'success' => 'Sipariş onayı',
    ];

    public const PAGE_ROUTES = [
        'search' => 'search',
        'cart' => 'cart.index',
        'login' => 'account.login',
        'register' => 'account.register',
        'wishlist' => 'wishlist.index',
        'checkout' => 'checkout.index',
        'success' => 'checkout.success',
    ];

    protected $fillable = [
        'site_name', 'title_suffix', 'home_title', 'home_description',
        'default_description', 'social_image_path', 'google_verification',
        'noindex', 'nofollow', 'page_metadata',
    ];

    protected $attributes = [
        'site_name' => 'Polaris Kurşun',
        'title_suffix' => 'Polaris Kurşun',
        'noindex' => false,
        'nofollow' => false,
    ];

    protected $casts = [
        'noindex' => 'boolean',
        'nofollow' => 'boolean',
        'page_metadata' => 'array',
    ];

    // Do not insert rows on public GET requests; avoid additional writes on cache misses.
    public static function current(): self
    {
        return static::query()->find(1) ?? new self();
    }
}