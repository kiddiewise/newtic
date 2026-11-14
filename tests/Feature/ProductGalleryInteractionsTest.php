<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductGalleryInteractionsTest extends TestCase
{
    use RefreshDatabase;

    private function productWithPhotos(int $count): Product
    {
        $category = Category::create([
            'name' => 'Olta Takımları',
            'slug' => 'olta-takimlari',
            'is_active' => true,
        ]);
        $product = Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'GAL-001',
            'name' => 'Olta Kurşunu',
            'slug' => 'olta-kursunu',
            'price' => 95,
            'stock_quantity' => 10,
            'image_path' => 'storage/products/one.webp',
            'is_active' => true,
        ]);
        foreach (range(1, $count) as $n) {
            $product->images()->create([
                'path' => 'storage/products/'.$n.'.webp',
                'sort_order' => $n,
            ]);
        }

        return $product;
    }

    public function test_desktop_arrows_exist_only_for_galleries_with_multiple_images(): void
    {
        $product = $this->productWithPhotos(3);
        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('data-gallery-prev', $html);
        $this->assertStringContainsString('data-gallery-next', $html);
        $this->assertStringContainsString('aria-label="Önceki ürün fotoğrafı"', $html);
        $this->assertStringContainsString('aria-label="Sonraki ürün fotoğrafı"', $html);
        $this->assertStringContainsString('data-gallery-current', $html);
        $this->assertStringContainsString('data-gallery-swipe', $html);
        $this->assertStringContainsString('data-gallery-live', $html);

        $product->images()->where('sort_order', '>', 1)->delete();
        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertDontSee('data-gallery-prev')
            ->assertDontSee('data-gallery-next');
    }

    public function test_gallery_assets_hide_arrows_on_mobile_and_support_pinch_to_reset(): void
    {
        $styles = file_get_contents(resource_path('css/product-gallery.css'));
        $appStyles = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/product-gallery.js'));
        $interaction = file_get_contents(resource_path('js/product-detail.js'));

        $this->assertStringContainsString('@media(max-width:760px)', $styles);
        $this->assertStringContainsString('display:none!important', $styles);
        $this->assertStringContainsString('touch-action:pan-y', $styles);
        $this->assertStringContainsString("@import './product-gallery.css';", $appStyles);
        $this->assertStringContainsString("touchstart", $js);
        $this->assertStringContainsString("touchmove", $js);
        $this->assertStringContainsString("touchend", $js);
        $this->assertStringContainsString("resetZoom()", $js);
        $this->assertStringContainsString("initProductGallery", $interaction);
    }
}
