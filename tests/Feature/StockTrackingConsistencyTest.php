<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockTrackingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock, bool $tracking): Product
    {
        $category = Category::first() ?? Category::create([
            'name' => 'Özel Sipariş', 'slug' => 'ozel-siparis', 'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'STOCK-'.($tracking ? 'TRACKED' : 'UNTRACKED'),
            'slug' => $tracking ? 'tracked' : 'untracked',
            'name' => 'Özel Üretim '.$stock,
            'price' => '150.00', 'stock_quantity' => $stock,
            'tracks_stock' => $tracking, 'is_active' => true,
        ]);
    }

    private function bank(): void
    {
        BankAccount::create([
            'bank_name' => 'Test Bankası', 'account_holder' => 'Test Mağaza',
            'iban' => 'TR330006100519786457841326', 'is_active' => true,
        ]);
    }

    public function test_untracked_zero_stock_is_purchasable_in_all_storefront_views(): void
    {
        $product = $this->product(0, false);
        $this->assertSame(999, $product->cart_quantity_limit);

        $this->get(route('category.show', $product->category->slug))
            ->assertOk()->assertSee('Sepete Ekle')->assertDontSee('Tükendi');
        $this->get(route('product.show', $product->slug))
            ->assertOk()->assertSee('max="999"', false)
            ->assertDontSee('pdp-out-of-stock', false);

        $this->post(route('cart.add'), ['items' => [$product->id => 2]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('cart.index'))->assertOk()->assertSee('2 adet');
        $this->patch(route('cart.update', $product), ['quantity' => 3])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, (int) session('shopping_cart')[$product->id]);

        $this->bank();
        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.place'), [
            'checkout_token' => session('checkout_token'),
            'payment_method' => 'bank_transfer',
            'email' => 'siparis@example.test',
            'address_label' => 'Ev',
            'full_name' => 'Örnek Kullanıcı',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Gaziemir',
            'street_address' => 'Test mahallesi no 4',
            'invoice_type' => 'individual',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(3, Order::firstOrFail()->items()->firstOrFail()->quantity);
    }

    public function test_untracked_product_still_honors_999_total_across_options(): void
    {
        $product = $this->product(0, false);
        $first = $product->options()->create(['value' => 'Küçük']);
        $second = $product->options()->create(['value' => 'Büyük']);
        $this->post(route('cart.add'), [
            'items' => [$product->id => 600],
            'options' => [$product->id => $first->id],
        ])->assertSessionHasNoErrors();

        $this->post(route('cart.add'), [
            'items' => [$product->id => 400],
            'options' => [$product->id => $second->id],
        ])->assertSessionHasErrors('items');

        $this->post(route('cart.add'), [
            'items' => [$product->id => 399],
            'options' => [$product->id => $second->id],
        ])->assertSessionHasNoErrors();
        $this->get(route('cart.index'))->assertOk();
        $this->assertSame(999, array_sum(session('shopping_cart')));
    }

    public function test_tracked_zero_stock_remains_unavailable_and_cannot_be_added(): void
    {
        $product = $this->product(0, true);
        $this->assertSame(0, $product->cart_quantity_limit);
        $this->get(route('category.show', $product->category->slug))
            ->assertOk()->assertSee('Tükendi');
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertSessionHasErrors('items');
        $this->assertSame([], session('shopping_cart', []));
    }
}
