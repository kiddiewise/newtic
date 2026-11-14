<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartPickerSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    private function sku(string $sku = 'DEMO-SURF-125'): Product
    {
        return Product::query()->where('sku', $sku)->firstOrFail();
    }

    private function quantityFromPage(string $html, int $productId): int
    {
        $matched = preg_match(
            '/data-product-id="'.$productId.'"[^>]+value="(\d+)"/',
            $html,
            $matches
        );

        $this->assertSame(1, $matched, 'Ürün için adet alanı bulunamadı.');

        return (int) $matches[1];
    }

    public function test_first_product_visit_defaults_to_one_but_other_gramages_start_at_zero(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $selected = $this->sku();
        $other = $this->sku('DEMO-SURF-200');

        $html = $this->get(route('product.show', $selected->slug))->assertOk()->getContent();

        $this->assertSame(1, $this->quantityFromPage($html, $selected->id));
        $this->assertSame(0, $this->quantityFromPage($html, $other->id));
        $this->assertSame(0, (int) session('product_picker_reset', 0));
    }

    public function test_after_add_and_cart_clear_every_product_picker_quantity_is_zero(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $selected = $this->sku();
        $other = $this->sku('DEMO-SURF-200');

        $this->post(route('cart.add'), [
            'items' => [$selected->id => 1, $other->id => 4],
        ])->assertRedirect(route('cart.index'))->assertSessionHasNoErrors();
        $this->assertTrue(session('product_picker_reset'));

        $afterAdd = $this->get(route('product.show', $selected->slug))->assertOk()->getContent();
        $this->assertSame(0, $this->quantityFromPage($afterAdd, $selected->id));
        $this->assertSame(0, $this->quantityFromPage($afterAdd, $other->id));
        $this->assertStringContainsString('data-quick-qty>0</output>', $afterAdd);

        $this->post(route('cart.clear'))->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))->assertOk()->assertSee('Sepetin henüz boş');
        $this->assertSame([], session('shopping_cart', []));
        $this->assertTrue(session('product_picker_reset'));

        $afterClear = $this->get(route('product.show', $selected->slug))->assertOk()->getContent();
        $this->assertSame(0, $this->quantityFromPage($afterClear, $selected->id));
        $this->assertSame(0, $this->quantityFromPage($afterClear, $other->id));
        $this->assertStringContainsString('data-quick-qty>0</output>', $afterClear);
    }

    public function test_removing_or_updating_cart_items_never_restores_old_picker_quantities(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $selected = $this->sku();

        $this->post(route('cart.add'), ['items' => [$selected->id => 3]])
            ->assertRedirect(route('cart.index'));
        $this->patch(route('cart.update', $selected), ['quantity' => 1])
            ->assertRedirect(route('cart.index'));
        $this->assertSame(1, session('shopping_cart')[$selected->id]);
        $this->assertTrue(session('product_picker_reset'));

        $this->delete(route('cart.remove', $selected))->assertRedirect(route('cart.index'));
        $this->assertSame([], session('shopping_cart'));

        $html = $this->get(route('product.show', $selected->slug))->assertOk()->getContent();
        $this->assertSame(0, $this->quantityFromPage($html, $selected->id));
    }

    public function test_empty_cart_does_not_regain_products_until_user_adds_them_again(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $selected = $this->sku();

        $this->post(route('cart.add'), ['items' => [$selected->id => 4]])->assertRedirect();
        $this->post(route('cart.clear'))->assertRedirect();
        $this->get(route('product.show', $selected->slug))->assertOk();
        $this->get(route('cart.index'))->assertOk()->assertSee('Sepetin henüz boş');

        $this->post(route('cart.add'), ['items' => [$selected->id => 2]])
            ->assertRedirect(route('cart.index'))->assertSessionHasNoErrors();
        $this->assertSame(2, session('shopping_cart')[$selected->id]);

        $this->get(route('cart.index'))->assertOk()->assertSee('2 adet');
    }
}
