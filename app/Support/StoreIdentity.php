<?php

namespace App\Support;

use App\Models\Product;
use App\Models\SeoSetting;

final class StoreIdentity
{
    /**
     * Business identity comes from each installation's own database, falling
     * back to APP_NAME. Never use another customer's name in defaults.
     */
    public static function name(?SeoSetting $settings = null): string
    {
        $settings ??= SeoSetting::current();
        $name = trim((string) $settings->site_name);
        if ($name !== '') return $name;

        $name = trim((string) config('app.name', ''));
        return $name !== '' ? $name : 'Online Mağaza';
    }

    public static function wordmark(): array
    {
        $parts = preg_split('/\s+/u', self::name(), -1, PREG_SPLIT_NO_EMPTY) ?: ['Mağaza'];
        if (count($parts) === 1) return [$parts[0], ''];

        $last = array_pop($parts);
        return [implode(' ', $parts), $last];
    }

    public static function description(?SeoSetting $settings = null): string
    {
        $settings ??= SeoSetting::current();
        $description = trim((string) $settings->default_description);
        return $description !== '' ? $description : self::name($settings).' online mağazasında ürünleri keşfedin.';
    }

    public static function alternativesLabel(Product $product): string
    {
        // Weight-based catalogues may retain their familiar gramage wording.
        // Other industries receive a neutral product-choice label instead.
        return preg_match('/\d+\s*(?:gr(?:am)?|g|kg)\b/iu', (string) $product->name)
            ? 'Diğer gramaj seçenekleri'
            : 'Diğer ürün seçenekleri';
    }

    public static function productBrand(Product $product): string
    {
        $brand = trim((string) $product->google_brand);
        return $brand !== '' ? $brand : self::name();
    }
}