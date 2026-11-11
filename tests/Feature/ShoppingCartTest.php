<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingCartTest extends TestCase
{
    use RefreshDatabase;

    private function demo(string $sku = 'DEMO-SURF-125'): Product
    {
        $this->seed(PolarisDemoSeeder::class);

        return Product::query()->where('sku', $sku)->firstOrFail();
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function product(string $sku, string $price, int $stock = 25): Product
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test ürünleri', 'slug' => 'test-urunleri', 'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'sku' => $sku,
            'name' => 'Kurşun '.$sku,
            'slug' => 'kursun-'.strtolower($sku),
            'price' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }

    public function test_empty_cart_is_accessible_without_login(): void
    {
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Sepetin henüz boş')
            ->assertSee('Ürünleri keşfet');
        $this->assertDatabaseHas('store_settings', [
            'id' => 1, 'free_shipping_threshold' => '1000', 'shipping_fee' => '79.9',
        ]);
    }

    public function test_cart_adds_multiple_independent_skus_and_calculates_server_side_prices(): void
    {
        $first = $this->demo('DEMO-SURF-125');
        $second = Product::query()->where('sku', 'DEMO-SURF-200')->firstOrFail();

        $this->post(route('cart.add'), [
            'items' => [$first->id => 2, $second->id => 3],
            'price' => '0.01', // forged client value must never be trusted
            'shipping_fee' => '0',
        ])->assertRedirect(route('cart.index'))->assertSessionHasNoErrors();

        $cart = $this->get(route('cart.index'))->assertOk()
            ->assertSee($first->name)->assertSee($second->name)
            ->assertSee('5 adet')->assertSee('79,90 ₺');

        $expectedSubtotal = 2 * (float) $first->price + 3 * (float) $second->price;
        $this->assertStringContainsString(
            number_format($expectedSubtotal, 2, ',', '.').' ₺',
            $cart->getContent()
        );
        $this->assertSame(2, count(session('shopping_cart')));
        $this->assertSame(2, session('shopping_cart')[$first->id]);
        $this->assertSame(3, session('shopping_cart')[$second->id]);
    }

    public function test_cart_additions_accumulate_and_quantity_edit_updates_total(): void
    {
        $product = $this->demo();

        $this->post(route('cart.add'), ['items' => [$product->id => 2]])->assertRedirect();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $this->assertSame(3, session('shopping_cart')[$product->id]);

        $this->patch(route('cart.update', $product), ['quantity' => 4])->assertRedirect();
        $this->assertSame(4, session('shopping_cart')[$product->id]);
        $this->get(route('cart.index'))->assertOk()->assertSee('4 adet');
    }

    public function test_cart_uses_updated_product_prices_instead_of_cached_client_totals(): void
    {
        $product = $this->demo();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $product->update(['price' => '499.50', 'compare_at_price' => '600.00']);

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('499,50')->assertSee('600,00');
    }

    public function test_free_shipping_starts_at_exactly_1000_lira_and_under_limit_has_fee(): void
    {
        $product = $this->product('FREE', '499.99');
        $this->post(route('cart.add'), ['items' => [$product->id => 2]])->assertRedirect();

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('999,98 ₺')
            ->assertSee('79,90 ₺')
            ->assertSee('1.079,88 ₺')
            ->assertSee('0,02 ₺ daha ekle, kargo ücretsiz olsun!');

        $product->update(['price' => '500.00']);
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Ücretsiz kargo kazandın!')
            ->assertSee('1.000,00 ₺')
            ->assertDontSee('1.079,88 ₺')
            ->assertSee('Ödenecek tutar');
    }

    public function test_admin_can_change_shipping_threshold_and_fee_and_totals_refresh(): void
    {
        $product = $this->product('SHIP', '300.00');
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();

        $this->get(route('admin.store-settings.edit'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.store-settings.edit'))->assertForbidden();
        $this->patch(route('admin.store-settings.update'), [
            'free_shipping_threshold' => 500, 'shipping_fee' => 49.50,
        ])->assertForbidden();

        $this->actingAs($this->administrator());
        $this->get(route('admin.store-settings.edit'))->assertOk()->assertSee('Kargo tutarları');
        $this->patch(route('admin.store-settings.update'), [
            'free_shipping_threshold' => '500.00', 'shipping_fee' => '49.50',
        ])->assertRedirect(route('admin.store-settings.edit'))->assertSessionHasNoErrors();
        $this->get(route('cart.index'))->assertOk()->assertSee('49,50 ₺')
            ->assertSee('349,50 ₺')->assertSee('200,00 ₺ daha ekle');

        $this->patch(route('admin.store-settings.update'), [
            'free_shipping_threshold' => '250.00', 'shipping_fee' => '99.00',
        ])->assertRedirect();
        $this->get(route('cart.index'))->assertOk()->assertSee('Ücretsiz kargo kazandın!')
            ->assertSee('300,00 ₺')->assertDontSee('349,50 ₺');
    }

    public function test_invalid_shipping_settings_are_rejected(): void
    {
        $this->actingAs($this->administrator());
        $this->patch(route('admin.store-settings.update'), [
            'free_shipping_threshold' => -1, 'shipping_fee' => -99,
        ])->assertSessionHasErrors(['free_shipping_threshold', 'shipping_fee']);
        $this->assertSame('1000.00', StoreSetting::current()->free_shipping_threshold);
        $this->assertSame('79.90', StoreSetting::current()->shipping_fee);
    }

    public function test_unavailable_products_and_out_of_stock_quantities_are_not_added(): void
    {
        $product = $this->demo();
        $product->update(['stock_quantity' => 1]);

        $this->post(route('cart.add'), ['items' => [$product->id => 2]])
            ->assertSessionHasErrors('items');
        $this->assertSame([], session('shopping_cart', []));

        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertRedirect(route('cart.index'));
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertSessionHasErrors('items');
        $this->assertSame(1, session('shopping_cart')[$product->id]);

        $product->update(['is_active' => false]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Sepetin henüz boş');
        $this->assertSame([], session('shopping_cart'));
    }

    public function test_zero_selection_and_invalid_or_forged_items_do_not_mutate_cart(): void
    {
        $product = $this->demo();

        $this->post(route('cart.add'), ['items' => [$product->id => 0]])
            ->assertSessionHasErrors('items');
        $this->post(route('cart.add'), ['items' => [$product->id => -2]])
            ->assertSessionHasErrors('items.'.$product->id);
        $this->post(route('cart.add'), ['items' => [123456789 => 1]])
            ->assertSessionHasErrors('items');
        $this->assertSame([], session('shopping_cart', []));
    }

    public function test_cart_removal_and_empty_cart_actions_work(): void
    {
        $first = $this->demo();
        $second = Product::query()->where('sku', 'DEMO-SURF-150')->firstOrFail();
        $this->post(route('cart.add'), ['items' => [$first->id => 1, $second->id => 1]])
            ->assertRedirect();
        $this->delete(route('cart.remove', $first))->assertRedirect(route('cart.index'));
        $this->assertArrayNotHasKey($first->id, session('shopping_cart'));
        $this->assertArrayHasKey($second->id, session('shopping_cart'));
        $this->post(route('cart.clear'))->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))->assertOk()->assertSee('Sepetin henüz boş');
    }

    public function test_header_links_to_cart_and_shows_count_in_mobile_and_desktop_navigation(): void
    {
        $product = $this->demo();
        $this->post(route('cart.add'), ['items' => [$product->id => 3]])->assertRedirect();
        $this->get('/')->assertOk()
            ->assertSee(route('cart.index'))
            ->assertSee('Sepetim, 3 adet')
            ->assertSee('Sepet (3)');
    }

    public function test_product_page_provides_real_add_to_cart_form_for_each_category_product(): void
    {
        $product = $this->demo();
        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertStringContainsString('id="pdp-cart-form"', $html);
        $this->assertStringContainsString('action="'.route('cart.add').'"', $html);
        $this->assertStringContainsString('name="items['.$product->id.']"', $html);
        $this->assertStringContainsString('form="pdp-cart-form"', $html);
        $this->assertStringContainsString('data-cart-add', $html);
        $this->assertSame(6, substr_count($html, 'data-order-quantity'));
    }

    public function test_cart_removes_items_when_a_parent_category_is_disabled(): void
    {
        $root = Category::create([
            'name' => 'Üst kategori', 'slug' => 'ust-kategori', 'is_active' => true,
        ]);
        $child = Category::create([
            'parent_id' => $root->id,
            'name' => 'Alt kategori', 'slug' => 'alt-kategori', 'is_active' => true,
        ]);
        $product = Product::create([
            'primary_category_id' => $child->id,
            'sku' => 'HIDDEN-75', 'slug' => 'hidden-75', 'name' => '75 gr test ürünü',
            'price' => '45.00', 'stock_quantity' => 10, 'is_active' => true,
        ]);

        $this->post(route('cart.add'), ['items' => [$product->id => 2]])->assertRedirect();
        $this->assertSame(2, session('shopping_cart')[$product->id]);
        $root->update(['is_active' => false]);

        $this->get(route('cart.index'))->assertOk()->assertSee('Sepetin henüz boş');
        $this->assertSame([], session('shopping_cart'));
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cart_does_not_accept_non_post_mutations_or_start_checkout(): void
    {
        $this->get('/sepet/ekle')->assertStatus(405);
        $this->get('/sepet/temizle')->assertStatus(405);
        $this->get('/sepet')->assertOk()->assertSee('Sepetin henüz boş');
        $this->get('/odeme')->assertRedirect(route('cart.index'));
    }
}
