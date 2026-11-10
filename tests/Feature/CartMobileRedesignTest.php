<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
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
            ->assertSee('<span>Özet</span>', false)
            ->assertSee('>Siparişi Tamamla</a>', false)
            ->assertDontSee('Siparişi Tamamla <span aria-hidden="true">›</span>', false)
            ->assertSee('Sil')
            ->assertSee('Kargo dahil')
            ->assertSee('data-cart-update-form');
    }

    public function test_favorite_button_requires_membership_and_persists_per_account(): void
    {
        $product = $this->product();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();

        $this->get(route('cart.index'))->assertOk()
            ->assertSee(route('account.login', ['next' => 'cart', 'favorite' => $product->id]))
            ->assertSee('favorilere eklemek için üye girişi yap');
        $this->get(route('wishlist.index'))->assertRedirect(route('account.login'));
        $this->post(route('wishlist.toggle', $product))->assertRedirect(route('account.login'));
        $this->assertDatabaseCount('wishlist_items', 0);

        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->get(route('cart.index'))->assertOk()->assertSee(route('wishlist.toggle', $product));
        $this->post(route('wishlist.toggle', $product))->assertRedirect()
            ->assertSessionHas('success', 'Ürün favorilere eklendi.');
        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $owner->id, 'product_id' => $product->id,
        ]);
        $this->get(route('cart.index'))->assertOk()->assertSee('favorilerden çıkar');
        $this->get(route('wishlist.index'))->assertOk()
            ->assertSee($product->name)->assertSee('Favorilerim');

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);
        $this->get(route('wishlist.index'))->assertOk()->assertSee('Henüz favori ürünün yok');

        $this->actingAs($owner);
        $this->post(route('wishlist.toggle', $product))->assertRedirect()
            ->assertSessionHas('success', 'Ürün favorilerden çıkarıldı.');
        $this->assertDatabaseMissing('wishlist_items', [
            'user_id' => $owner->id, 'product_id' => $product->id,
        ]);
        $this->get(route('wishlist.index'))->assertOk()
            ->assertSee('Henüz favori ürünün yok');
    }

    public function test_favoriting_inactive_products_is_rejected(): void
    {
        $product = $this->product();
        $product->update(['is_active' => false]);
        $this->actingAs(User::factory()->create());

        $this->post(route('wishlist.toggle', $product))->assertNotFound();
        $this->assertDatabaseCount('wishlist_items', 0);
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
