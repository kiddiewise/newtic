<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductPolishTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 8): Product
    {
        $category = Category::create([
            'name' => 'Surf Kurşunlar',
            'slug' => 'surf-kursunlar',
            'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'POLARIS-75',
            'slug' => 'polaris-75',
            'name' => 'Polaris Surf 75 gr',
            'price' => '49.90',
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }

    public function test_low_stock_and_compact_review_tabs_replace_old_product_information(): void
    {
        $product = $this->product();
        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertSee('Son 8 ürün')
            ->assertSee('Birlikte alınan diğer ürünler')
            ->assertSee('data-product-tab="description"', false)
            ->assertSee('data-product-tab="reviews"', false)
            ->assertSee('data-product-panel="description"', false)
            ->assertSee('data-product-panel="reviews"', false)
            ->assertSee('id="urun-yorumlari"', false)
            ->assertDontSee('Test önizlemesi')
            ->assertDontSee('görüntülenme')
            ->assertDontSee('● Stokta');

        $this->get(route('category.show', $product->category->slug))->assertOk()
            ->assertSee('Son 8 ürün')
            ->assertDontSee('product-card-views')
            ->assertDontSee('product-demo-pill');

        $product->update(['stock_quantity' => 30]);
        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertDontSee('Son 30 ürün')
            ->assertDontSee('● Stokta');
    }

    public function test_corporate_navy_buttons_and_reduced_motion_styles_are_bundled(): void
    {
        $css = file_get_contents(resource_path('css/storefront-polish.css'));
        $app = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("@import './storefront-polish.css';", $app);
        $this->assertStringContainsString('--store-action:#092037', $css);
        $this->assertStringContainsString('background:var(--store-action)!important', $css);
        $this->assertStringContainsString('.pdp-info-tabs', $css);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);
    }
}
