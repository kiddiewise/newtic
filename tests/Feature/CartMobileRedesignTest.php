<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartMobileRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $price = 120, ?int $compare = null): Product
    {
        $category = Category::create([
            'name' => 'Çupra Kurşun',
            'slug' => 'cupra-kursun',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Çupra Kurşun 80 gr',
            'slug' => 'cupra-kursun-80-gr',
            'sku' => 'CUPRA-80',
            'stock_quantity' => 12,
            'price' => $price,
            'compare_at_price' => $compare,
            'is_active' => true,
        ]);
    }

    public function test_mobile_cart_has_dedicated_header_quantity_buttons_and_a_fixed_order_summary(): void
    {
        $product = $this->product();
        $this->post(route('cart.add'), ['items' => [$product->id => 2]])
            ->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('is-cart-page')
            ->assertSee('cart-mobile-header')
            ->assertSee('cart-mobile-bottom')
            ->assertSee('cart-mobile-summary-sheet')
            ->assertSee('data-cart-summary-open')
            ->assertSee('data-cart-summary-close')
            ->assertSee('data-cart-minus')
            ->assertSee('data-cart-plus')
            ->assertSee('aria-label="'.$product->name.' adedini artır"', false)
            ->assertSee('2 Ürün')
            ->assertSee('Sepet Özeti')
            ->assertSee('Sil')
            ->assertSee('Kargo dahil')
            ->assertSee('data-cart-update-form');
    }

    public function test_favorite_button_is_functional_and_session_based(): void
    {
        $product = $this->product();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();

        $this->get(route('cart.index'))->assertOk()
            ->assertSee(route('wishlist.toggle', $product))
            ->assertSee('favorilere ekle');

        $this->post(route('wishlist.toggle', $product))->assertRedirect()
            ->assertSessionHas('success', 'Ürün favorilere eklendi.');
        $this->assertSame([$product->id], session('wishlist_product_ids'));

        $this->get(route('cart.index'))->assertOk()->assertSee('favorilerden çıkar');
        $this->get(route('wishlist.index'))->assertOk()
            ->assertSee($product->name)->assertSee('Favorilerim');

        $this->post(route('wishlist.toggle', $product))->assertRedirect()
            ->assertSessionHas('success', 'Ürün favorilerden çıkarıldı.');
        $this->get(route('wishlist.index'))->assertOk()
            ->assertSee('Henüz favori ürünün yok');
    }

    public function test_favoriting_inactive_products_is_rejected(): void
    {
        $product = $this->product();
        $product->update(['is_active' => false]);

        $this->post(route('wishlist.toggle', $product))->assertNotFound();
        $this->assertEmpty(session('wishlist_product_ids', []));
    }

    public function test_discounts_and_free_shipping_are_reflected_in_mobile_summary(): void
    {
        $product = $this->product(500, 640);
        $this->post(route('cart.add'), ['items' => [$product->id => 2]])->assertRedirect();
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('1.280,00 ₺')
            ->assertSee('280,00 ₺')
            ->assertSee('1.000,00 ₺')
            ->assertSee('Ücretsiz kargo kazandın!')
            ->assertSee('Kargo bedava')
            ->assertSee('Ödenecek tutar')
            ->assertSee('Bu alışverişte 280,00 ₺ indirim kazandın');
    }

    public function test_cart_quantity_patch_still_respects_stock_with_mobile_buttons(): void
    {
        $product = $this->product();
        $this->post(route('cart.add'), ['items' => [$product->id => 2]])->assertRedirect();

        $this->patch(route('cart.update', $product), ['quantity' => 3])
            ->assertRedirect(route('cart.index'));
        $this->assertSame(3, session('shopping_cart')[$product->id]);

        $this->patch(route('cart.update', $product), ['quantity' => 13])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(3, session('shopping_cart')[$product->id]);
    }
}
