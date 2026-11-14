<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Support\GoogleMerchantProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleMerchantProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_attributes_keep_discounts_and_stable_independent_sku_ids(): void
    {
        $category = Category::create(['name' => 'Olta', 'slug' => 'olta', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Kurşun 100g', 'slug' => 'kursun-100', 'sku' => 'K-100',
            'primary_category_id' => $category->id,
            'price' => '120.50', 'compare_at_price' => '150.00',
            'image_path' => 'storage/products/kursun.webp', 'stock_quantity' => 0,
            'tracks_stock' => true, 'is_active' => true, 'is_demo' => false,
        ]);
        $product->load('category', 'images');
        $this->assertTrue(GoogleMerchantProduct::eligible($product));
        $payload = GoogleMerchantProduct::payload($product);
        $this->assertSame('newtic-'.$product->id, $payload['offerId']);
        $this->assertSame('tr', $payload['contentLanguage']);
        $this->assertSame('TR', $payload['feedLabel']);
        $this->assertSame('OUT_OF_STOCK', $payload['productAttributes']['availability']);
        $this->assertSame('150000000', $payload['productAttributes']['price']['amountMicros']);
        $this->assertSame('120500000', $payload['productAttributes']['salePrice']['amountMicros']);
        $hash = GoogleMerchantProduct::hash($product);
        $product->update(['price' => '110.00']);
        $this->assertNotSame($hash, GoogleMerchantProduct::hash($product));
    }
}