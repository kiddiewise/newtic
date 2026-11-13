<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

final class GoogleMerchantProduct
{
    public static function offerId(Product $product): string
    {
        // Unlike the SKU/name, the internal product ID never changes on update.
        return 'newtic-'.$product->id;
    }

    public static function price(float $amount): array
    {
        return [
            'amountMicros' => (string) (int) round($amount * 1000000),
            'currencyCode' => 'TRY',
        ];
    }

    public static function eligible(Product $product, ?Collection $allCategories = null): bool
    {
        return (bool) $product->is_active
            && ! $product->is_demo
            && filled($product->image_path)
            && $product->category !== null
            && CategoryHierarchy::visible($product->category, $allCategories);
    }

    public static function attributes(Product $product): array
    {
        $current = (float) $product->price;
        $regular = (float) $product->compare_at_price;
        $isSale = $regular > $current && $current > 0;
        $brand = trim((string) ($product->google_brand ?: 'Polaris Kurşun'));

        $attrs = [
            'title' => Str::limit($product->name, 150, ''),
            'description' => Str::limit(trim(strip_tags((string) ($product->description ?: $product->short_description ?: $product->name))), 5000, ''),
            'link' => route('product.show', $product->slug),
            'imageLink' => asset($product->image_path),
            'availability' => $product->stock_quantity > 0 || ! $product->tracks_stock ? 'IN_STOCK' : 'OUT_OF_STOCK',
            'price' => self::price($isSale ? $regular : $current),
            'condition' => 'NEW',
            'brand' => $brand,
            'productTypes' => [$product->category?->name ?? ''],
        ];
        if ($isSale) {
            $attrs['salePrice'] = self::price($current);
        }
        if (filled($product->gtin)) {
            $attrs['gtins'] = [$product->gtin];
        }
        if (filled($product->mpn)) {
            $attrs['mpn'] = $product->mpn;
        }

        $secondary = $product->images
            ->pluck('path')->filter(fn ($path) => $path && $path !== $product->image_path)
            ->unique()->take(10)->map(fn ($path) => asset($path))->values()->all();
        if ($secondary) {
            $attrs['additionalImageLinks'] = $secondary;
        }

        return $attrs;
    }

    public static function payload(Product $product): array
    {
        return [
            'offerId' => self::offerId($product),
            'contentLanguage' => 'tr',
            'feedLabel' => 'TR',
            'productAttributes' => self::attributes($product),
        ];
    }

    public static function hash(Product $product): string
    {
        return hash('sha256', json_encode(self::payload($product), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}