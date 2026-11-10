<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPurchaseBarUxTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $suffix): Product
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Olta Bedenleri',
            'slug' => 'olta-bedenleri',
            'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Surf Kurşunlu Olta Bedeni '.$suffix.' gr',
            'sku' => 'BODY-'.$suffix,
            'slug' => 'surf-beden-'.$suffix,
            'price' => '149.90',
            'stock_quantity' => 9,
            'is_active' => true,
        ]);
    }

    public function test_option_product_has_two_mirrored_accessible_purchase_selects_and_one_form_option(): void
    {
        $product = $this->product('75');
        $product->update(['option_label' => 'İğne kalınlığı']);
        $first = $product->options()->create(['value' => '0,25']);
        $product->options()->create(['value' => '0,30']);

        $response = $this->get(route('product.show', $product->slug))->assertOk();
        $html = $response->getContent();

        $response->assertSee('id="pdp-desktop-option"', false)
            ->assertSee('id="pdp-sticky-option"', false)
            ->assertSee('data-option-help="desktop"', false)
            ->assertSee('data-option-help="sticky"', false)
            ->assertSee('has-variant', false)
            ->assertSee('İğne kalınlığı')
            ->assertSee('Sepete eklemek için önce')
            ->assertSee('0,25')
            ->assertSee('0,30')
            ->assertSee('data-variant-hint', false);

        $response->assertSee('Diğer gramaj seçenekleri')
            ->assertSee('aria-label="Seçilen ürünleri sepete ekle"', false)
            ->assertSee('href="#ui-shopping-cart"', false)
            ->assertSee('aria-label="Diğer gramaj seçeneklerini ve kategorideki ürünleri görüntüle"', false);
        // The slim options ribbon sits ABOVE the purchase row.
        // The row contains ONLY variant (if configured), quantity and cart add.
        $this->assertSame(1, substr_count($html, 'class="pdp-sticky-option-help"'));
        $this->assertSame(1, substr_count($html, 'class="pdp-sticky-variant-total"'));
        $this->assertSame(1, substr_count($html, 'class="pdp-sticky-add"'));
        $this->assertSame(1, substr_count($html, 'data-sticky-total'));
        $ribbon = strpos($html, 'pdp-sticky-choose pdp-sticky-gramages');
        $row = strpos($html, "pdp-sticky-actions");
        $this->assertNotFalse($ribbon);
        $this->assertNotFalse($row);
        $this->assertLessThan($row, $ribbon);
        $this->assertSame(2, substr_count($html, 'data-option-mirror'));
        $this->assertSame(1, substr_count($html, 'name="options['.$product->id.']"'));
        $this->assertSame(1, substr_count($html, 'form="pdp-cart-form" name="options['.$product->id.']"'));
        $this->assertSame(2, substr_count($html, 'data-option-location="'));
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'data-cart-add')); // Desktop, mobile and multi-picker share one form.
        $this->assertSame($first->id, $product->options()->first()->id);
    }

    public function test_plain_products_keep_original_purchase_controls_without_unneeded_variant_fields(): void
    {
        $product = $this->product('100');

        $response = $this->get(route('product.show', $product->slug))->assertOk();
        $html = $response->getContent();

        $response->assertSee('data-desktop-plus', false)
            ->assertSee('data-quick-plus', false)
            ->assertSee('data-sheet-open', false)
            ->assertSee('Sepete ekle')
            ->assertSee('Diğer gramaj seçenekleri')
            ->assertSee('aria-label="Seçilen ürünleri sepete ekle"', false)
            ->assertSee('href="#ui-shopping-cart"', false);
        $this->assertStringNotContainsString('data-option-mirror', $html);
        $this->assertStringNotContainsString('pdp-sticky-option-help', $html);
        $this->assertStringNotContainsString('class="pdp-sticky-actions has-variant"', $html);
    }

    public function test_current_product_has_mirrors_but_other_gram_options_remain_in_multi_picker(): void
    {
        $body75 = $this->product('75');
        $body100 = $this->product('100');
        foreach ([$body75, $body100] as $product) {
            $product->update(['option_label' => 'İğne kalınlığı']);
            $product->options()->create(['value' => '0,25']);
            $product->options()->create(['value' => '0,30']);
        }

        $html = $this->get(route('product.show', $body75->slug))
            ->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-option-mirror data-product-id="'.$body75->id.'"'));
        $this->assertStringNotContainsString('data-option-mirror data-product-id="'.$body100->id.'"', $html);
        $this->assertStringContainsString('name="options['.$body75->id.']"', $html);
        $this->assertStringContainsString('name="options['.$body100->id.']"', $html);
    }

    public function test_missing_choice_validation_returns_an_actionable_error(): void
    {
        $product = $this->product('125');
        $product->update(['option_label' => 'İğne kalınlığı']);
        $product->options()->create(['value' => '0,25']);

        $this->followingRedirects()
            ->from(route('product.show', $product->slug))
            ->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertOk()
            ->assertSee('cart-flash is-error')
            ->assertSee('İğne');
    }
}
