<?php

namespace Tests\Feature;

use App\Models\HomeSection;
use App\Models\Product;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_selected_categories_and_products(): void
    {
        $this->seed(PolarisDemoSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Kategorileri keşfet')
            ->assertSee('Popüler Ürünler')
            ->assertSee('Renkli Surf Kurşun 125 gr')
            ->assertSee('visual-slider')
            ->assertSee('demo-sea-mobile.svg');
    }

    public function test_inactive_slide_does_not_render(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $slider = HomeSection::query()->where('type', 'slider')->firstOrFail();
        $slider->slides()->create([
            'desktop_image_path' => 'hidden-slide.jpg',
            'sort_order' => 5,
            'is_active' => false,
        ]);

        $this->get('/')->assertOk()->assertDontSee('hidden-slide.jpg');
    }

    public function test_inactive_section_does_not_render(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        HomeSection::query()->where('type', 'products')->update(['is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Popüler Ürünler');
    }

    public function test_product_detail_keeps_every_product_independent_with_default_current_quantity(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $product = Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();

        $response = $this->get(route('product.show', $product->slug));
        $response->assertOk()->assertSee('Görüntülenen ürün')->assertSee('value="1"', false);
        $response->assertSee('Renkli Surf Kurşun 75 gr')->assertSee('Renkli Surf Kurşun 250 gr');
        $this->assertSame(6, substr_count($response->getContent(), 'data-order-quantity'));
    }

    public function test_disabled_products_are_not_in_grouped_order_list(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        Product::query()->where('sku', 'DEMO-SURF-75')->update(['is_active' => false]);
        $selected = Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();

        $response = $this->get(route('product.show', $selected->slug));
        $this->assertSame(5, substr_count($response->getContent(), 'data-order-quantity'));
    }

    public function test_missing_product_returns_404(): void
    {
        $this->get('/urun/olmayan-urun')->assertNotFound();
    }
}
