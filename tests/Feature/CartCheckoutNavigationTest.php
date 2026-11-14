<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_back_link_always_exits_to_home_instead_of_reopening_checkout(): void
    {
        $html = $this->get(route('cart.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'href="'.route('home').'" class="cart-mobile-back" data-cart-back',
            $html
        );

        // The old history.back listener created a cart -> checkout -> cart loop.
        $js = file_get_contents(resource_path('js/cart-mobile.js'));
        $this->assertStringNotContainsString('history.back()', $js);
        $this->assertStringNotContainsString('referrer.pathname', $js);
    }

    public function test_all_checkout_links_to_cart_share_the_safe_return_handler(): void
    {
        $category = Category::create([
            'name' => 'Surf', 'slug' => 'surf', 'is_active' => true,
        ]);
        $product = Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'BACK-NAV-75', 'slug' => 'back-nav-75',
            'name' => 'Polaris 75 gr', 'price' => '49.90',
            'stock_quantity' => 5, 'is_active' => true,
        ]);
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertRedirect(route('cart.index'));

        $html = $this->get(route('checkout.index'))->assertOk()->getContent();
        $this->assertSame(4, substr_count($html, 'data-checkout-return-cart'));

        $script = file_get_contents(resource_path('js/cart-checkout-navigation.js'));
        $this->assertStringContainsString('window.history.back()', $script);
        $this->assertStringContainsString('window.location.replace(link.href)', $script);
        $this->assertStringContainsString('event.preventDefault()', $script);
        $checkout = file_get_contents(resource_path('js/checkout.js'));
        $this->assertStringContainsString('installCheckoutCartLinks()', $checkout);
    }
}
