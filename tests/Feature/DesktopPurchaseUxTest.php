<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopPurchaseUxTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): Product
    {
        $this->seed(PolarisDemoSeeder::class);

        return Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();
    }

    public function test_desktop_product_cards_offer_a_labeled_cart_action_on_every_catalog_surface(): void
    {
        $product = $this->demo();

        foreach ([
            route('home'),
            route('category.show', $product->category->slug),
            route('search'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('product-card-action-label', $html);
            $this->assertStringContainsString('Sepete Ekle', $html);
            $this->assertStringContainsString('data-quick-add', $html);
            $this->assertStringContainsString('name="items['.$product->id.']" value="1"', $html);
        }
    }

    public function test_desktop_product_page_has_immediately_accessible_cart_controls_and_shared_picker_form(): void
    {
        $product = $this->demo();
        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('class="pdp-desktop-purchase"', $html);
        $this->assertStringContainsString('data-desktop-minus', $html);
        $this->assertStringContainsString('data-desktop-plus', $html);
        $this->assertStringContainsString('data-desktop-qty', $html);
        $this->assertStringContainsString('form="pdp-cart-form" class="pdp-desktop-add" data-cart-add', $html);
        $this->assertStringContainsString('Seçilenleri Sepete Ekle', $html);
        $this->assertStringContainsString('href="#pdp-picker"', $html);
        $this->assertStringContainsString('name="items['.$product->id.']"', $html);

        // The desktop picker button must not be hidden as in the previous layout.
        $this->assertStringNotContainsString(
            '@media(min-width:761px){.pdp-sheet-footer>button{display:none}}',
            file_get_contents(resource_path('css/product-detail-mobile.css'))
        );
    }

    public function test_out_of_stock_catalog_actions_and_desktop_purchase_are_disabled(): void
    {
        $product = $this->demo();
        $product->update(['stock_quantity' => 0]);

        $card = $this->get(route('category.show', $product->category->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Stokta Yok', $card);

        $detail = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="pdp-desktop-add" data-cart-add disabled/', $detail);
    }

    public function test_desktop_purchase_uses_the_same_server_validated_independent_sku_cart(): void
    {
        $product = $this->demo();
        $sibling = Product::query()->where('sku', 'DEMO-SURF-150')->firstOrFail();

        $this->post(route('cart.add'), [
            'items' => [$product->id => 2, $sibling->id => 3],
        ])->assertRedirect(route('cart.index'))->assertSessionHasNoErrors();

        $this->assertSame(2, session('shopping_cart')[$product->id]);
        $this->assertSame(3, session('shopping_cart')[$sibling->id]);
    }
}
