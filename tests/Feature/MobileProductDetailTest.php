<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_compact_mobile_header_and_sticky_selection_controls(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $product = Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();

        $response = $this->get(route('product.show', $product->slug));
        $response->assertOk()
            ->assertSee('is-product-detail')
            ->assertSee('pdp-mobile-header')
            ->assertSee('data-product-back')
            ->assertSee('Favoriler yakında')
            ->assertSee('data-sticky-bar')
            ->assertSee('data-quick-plus')
            ->assertSee('data-quick-minus')
            ->assertSee('data-sheet-open')
            ->assertSee('data-sheet-close')
            ->assertSee('Diğer gramaj seçenekleri')
            ->assertSee('data-scroll-ribbon', false)
            ->assertSee('href="#ui-chevron-up"', false)
            ->assertSee('href="#ui-shopping-cart"', false)
            ->assertSee('Görüntülenen ürün')
            ->assertSee('value="1"', false);

        $html = $response->getContent();
        $this->assertSame(6, substr_count($html, 'data-order-quantity'));
        $this->assertSame(1, substr_count($html, 'data-sticky-bar'));
        $this->assertTrue(strpos($html, 'pdp-mobile-header') < strpos($html, 'pdp-art'));
    }

    public function test_gallery_has_compact_numbered_counter_and_multiple_images(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $product = Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();
        $product->update(['image_path' => 'storage/products/front.webp']);
        $product->images()->createMany([
            ['path' => 'storage/products/front.webp', 'sort_order' => 0],
            ['path' => 'storage/products/back.webp', 'sort_order' => 1],
        ]);
        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('data-gallery-current')
            ->assertSee('1</span> / 2', false)
            ->assertSee('data-gallery-swipe')
            ->assertSee('storage/products/back.webp')
            ->assertSee('2. ürün fotoğrafını göster');
    }

    public function test_price_comes_immediately_after_title_and_description_is_below_selection(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $product = Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();
        $product->update(['price' => '120.00', 'compare_at_price' => '160.00']);

        $html = $this->get(route('product.show', $product->slug))
            ->assertOk()->assertSee('-%25')->assertSee('160,00')->assertSee('120,00')
            ->getContent();
        $title = strpos($html, '<h1>');
        $price = strpos($html, 'data-detail-price-anchor');
        $picker = strpos($html, 'data-product-sheet');
        $description = strpos($html, 'pdp-description-section');
        $this->assertTrue($title < $price && $price < $picker && $picker < $description);
    }

    public function test_out_of_stock_current_product_initially_has_zero_quantity(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $product = Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();
        $product->update(['stock_quantity' => 0]);

        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Stokta yok', $html);
        $this->assertStringContainsString('data-product-id="'.$product->id.'"', $html);
        $this->assertMatchesRegularExpression(
            '/data-product-id="'.$product->id.'"[^>]+value="0"/',
            $html
        );
    }

    public function test_other_product_details_keep_independent_links(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $selected = Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();
        $another = Product::query()->where('sku', 'DEMO-SURF-200')->firstOrFail();
        $response = $this->get(route('product.show', $selected->slug));

        $response->assertOk()
            ->assertSee(route('product.show', $another->slug))
            ->assertSee($another->name)
            ->assertSee('data-price="'.$another->price.'"', false);
        $this->assertNotSame($selected->slug, $another->slug);
    }
}
