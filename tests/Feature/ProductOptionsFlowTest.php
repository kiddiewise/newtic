<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionsFlowTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku = 'BODY-75', int $stock = 12): Product
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Olta bedenleri', 'slug' => 'olta-bedenleri', 'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'sku' => $sku, 'name' => 'Surf kurşunlu olta bedeni '.$sku,
            'slug' => strtolower($sku),
            'price' => '129.90', 'stock_quantity' => $stock, 'is_active' => true,
        ]);
    }

    private function choices(Product $product): array
    {
        $product->update(['option_label' => 'İğne kalınlığı']);
        return [
            $product->options()->create(['value' => '0,25']),
            $product->options()->create(['value' => '0,30']),
        ];
    }

    public function test_plain_surf_leads_remain_independent_skus_and_work_without_options(): void
    {
        $product75 = $this->product('LEAD-75');
        $product100 = $this->product('LEAD-100');

        $this->post(route('cart.add'), [
            'items' => [$product75->id => 2, $product100->id => 1],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, session('shopping_cart')[$product75->id]);
        $this->assertSame(1, session('shopping_cart')[$product100->id]);
        $this->get(route('cart.index'))->assertOk()
            ->assertSee($product75->name)->assertSee($product100->name);
    }

    public function test_admin_can_manage_hook_sizes_without_merging_gram_products(): void
    {
        $product = $this->product('BODY-75');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        $payload = [
            'name' => $product->name,
            'sku' => $product->sku,
            'primary_category_id' => $product->primary_category_id,
            'regular_price' => '129.90',
            'stock_quantity' => 12,
            'is_active' => 1,
            'option_label' => 'İğne kalınlığı',
            'options_text' => "0,25\n0,30\n0,35",
        ];
        $this->patch(route('admin.products.update', $product), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, $product->options()->count());
        $this->assertSame('İğne kalınlığı', $product->fresh()->option_label);
        $this->get(route('admin.products.edit', $product))
            ->assertOk()->assertSee('Ürün seçenekleri / varyasyon')->assertSee('0,25');

        $savedId = $product->options()->where('value', '0,25')->firstOrFail()->id;
        $this->patch(route('admin.products.update', $product), array_merge($payload, [
            'options_text' => "0,25\n0,40",
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, $product->options()->count());
        $this->assertSame($savedId, $product->options()->where('value', '0,25')->firstOrFail()->id);

        $this->patch(route('admin.products.update', $product), array_merge($payload, [
            'options_text' => "0,25\n0,25",
        ]))->assertSessionHasErrors('options_text');
        $this->assertSame(2, $product->options()->count());
    }

    public function test_option_picker_renders_per_sku_and_configurable_cards_link_to_detail(): void
    {
        $product75 = $this->product('BODY-75');
        $product100 = $this->product('BODY-100');
        [$thin, $thick] = $this->choices($product75);
        $this->choices($product100);

        $page = $this->get(route('product.show', $product75->slug))->assertOk();
        $page->assertSee('name="options['.$product75->id.']"', false)
            ->assertSee('name="options['.$product100->id.']"', false)
            ->assertSee($thin->value)->assertSee($thick->value)
            ->assertSee('data-product-option');

        $this->get(route('category.show', $product75->category->slug))->assertOk()
            ->assertSee('Seçenek Seç')
            ->assertSee(route('product.show', $product75->slug));
    }

    public function test_option_is_required_and_must_belong_to_the_selected_gram(): void
    {
        $first = $this->product('BODY-75');
        $second = $this->product('BODY-100');
        [$firstOption] = $this->choices($first);
        [$otherOption] = $this->choices($second);

        $this->post(route('cart.add'), ['items' => [$first->id => 1]])
            ->assertSessionHasErrors('options.'.$first->id);

        $this->post(route('cart.add'), [
            'items' => [$first->id => 1],
            'options' => [$first->id => $otherOption->id],
        ])->assertSessionHasErrors('options.'.$first->id);

        $this->assertSame([], session('shopping_cart', []));
        $this->post(route('cart.add'), [
            'items' => [$first->id => 1],
            'options' => [$first->id => $firstOption->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, session('shopping_cart')[$first->id.':'.$firstOption->id]);
    }

    public function test_same_gram_can_have_two_hook_sizes_in_cart_but_shares_total_stock(): void
    {
        $product = $this->product('BODY-125', 4);
        [$thin, $thick] = $this->choices($product);

        foreach ([[$thin, 2], [$thick, 2]] as [$option, $quantity]) {
            $this->post(route('cart.add'), [
                'items' => [$product->id => $quantity],
                'options' => [$product->id => $option->id],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(2, session('shopping_cart')[$product->id.':'.$thin->id]);
        $this->assertSame(2, session('shopping_cart')[$product->id.':'.$thick->id]);

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('İğne kalınlığı')
            ->assertSee('0,25')->assertSee('0,30')->assertSee('4 Ürün');

        $this->post(route('cart.add'), [
            'items' => [$product->id => 1],
            'options' => [$product->id => $thin->id],
        ])->assertSessionHasErrors('items');

        $this->assertSame(2, session('shopping_cart')[$product->id.':'.$thin->id]);
    }

    public function test_cart_update_and_remove_target_only_the_selected_hook_size(): void
    {
        $product = $this->product('BODY-150', 10);
        [$thin, $thick] = $this->choices($product);
        foreach ([$thin, $thick] as $option) {
            $this->post(route('cart.add'), [
                'items' => [$product->id => 1],
                'options' => [$product->id => $option->id],
            ])->assertRedirect();
        }

        $this->patch(route('cart.update', $product), [
            'option_id' => $thin->id, 'quantity' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, session('shopping_cart')[$product->id.':'.$thin->id]);
        $this->assertSame(1, session('shopping_cart')[$product->id.':'.$thick->id]);

        $this->delete(route('cart.remove', $product), [
            'option_id' => $thin->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey($product->id.':'.$thin->id, session('shopping_cart'));
        $this->assertSame(1, session('shopping_cart')[$product->id.':'.$thick->id]);
    }

    public function test_checkout_saves_immutable_hook_size_snapshot_and_decrements_shared_stock(): void
    {
        $product = $this->product('BODY-200', 9);
        [$thin, $thick] = $this->choices($product);
        foreach ([[$thin, 1], [$thick, 2]] as [$option, $quantity]) {
            $this->post(route('cart.add'), [
                'items' => [$product->id => $quantity],
                'options' => [$product->id => $option->id],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->get(route('checkout.index'))->assertOk()->assertSee('İğne kalınlığı');
        $token = session('checkout_token');
        $this->post(route('checkout.place'), [
            'checkout_token' => $token,
            'payment_method' => 'bank_transfer',
            'email' => 'buyer@example.com',
            'address_label' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '05321234567',
            'city' => 'İzmir', 'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi Mustafa Kemal Sokak No 12',
            'invoice_type' => 'individual',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(2, $order->items()->count());
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'product_id' => $product->id,
            'option_label' => 'İğne kalınlığı', 'option_value' => '0,25', 'quantity' => 1,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'product_id' => $product->id,
            'option_value' => '0,30', 'quantity' => 2,
        ]);
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee('0,25')->assertSee('0,30');
    }

    public function test_removing_configured_option_expires_invalid_cart_line_safely(): void
    {
        $product = $this->product('BODY-300');
        [$thin] = $this->choices($product);
        $this->post(route('cart.add'), [
            'items' => [$product->id => 1],
            'options' => [$product->id => $thin->id],
        ])->assertRedirect();

        $thin->delete();
        $this->get(route('cart.index'))->assertOk()
            ->assertDontSee('İğne kalınlığı: 0,25');
        $this->assertArrayNotHasKey($product->id.':'.$thin->id, session('shopping_cart', []));
    }
}
