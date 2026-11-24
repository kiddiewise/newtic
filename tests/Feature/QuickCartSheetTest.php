<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickCartSheetTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): Product
    {
        $this->seed(PolarisDemoSeeder::class);

        return Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();
    }

    public function test_product_cards_have_separate_detail_links_and_a_real_cart_form(): void
    {
        $product = $this->demo();
        $response = $this->get('/')->assertOk()
            ->assertSee('data-quick-add')
            ->assertSee('data-quick-sheet')
            ->assertSee('data-quick-backdrop')
            ->assertSee('data-quick-product')
            ->assertSee('Sepetime git')
            ->assertSee('csrf-token');

        $html = $response->getContent();
        $this->assertStringContainsString('name="items['.$product->id.']" value="1"', $html);
        $this->assertStringContainsString('aria-label="'.$product->name.' sepete ekle"', $html);
        $this->assertStringContainsString('href="'.route('product.show', $product->slug).'"', $html);
        $this->assertStringNotContainsString('product-card-action" aria-hidden="true">↗', $html);
    }

    public function test_ajax_add_returns_quick_sheet_from_server_and_correct_cart_count(): void
    {
        $product = $this->demo();
        $related = Product::query()->where('sku', 'DEMO-SURF-150')->firstOrFail();

        $response = $this->postJson(route('cart.add'), [
            'items' => [$product->id => 1],
            'quick_anchor_id' => $product->id,
            'price' => '0.01',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('subtotal_cents', (int) round((float) $product->price * 100))
            ->assertJsonPath('cart_url', route('cart.index'));

        $this->assertStringContainsString($related->name, $response->json('html'));
        $this->assertStringContainsString('data-quick-minus', $response->json('html'));
        $this->assertStringContainsString('data-quick-plus', $response->json('html'));
        $this->assertStringContainsString('Aynı koleksiyondan diğer ürünler', $response->json('html'));

        $this->assertSame(1, session('shopping_cart')[$product->id]);
    }

    public function test_ajax_stepper_adds_siblings_and_can_decrease_to_zero_without_resurrecting(): void
    {
        $product = $this->demo();
        $sibling = Product::query()->where('sku', 'DEMO-SURF-150')->firstOrFail();

        $this->postJson(route('cart.add'), ['items' => [$product->id => 1]])->assertOk();
        $added = $this->postJson(route('cart.add'), [
            'items' => [$sibling->id => 1],
            'quick_anchor_id' => $product->id,
        ])->assertOk()->assertJsonPath('count', 2);
        $this->assertStringContainsString('data-product-id="'.$product->id.'"', $added->json('html'));

        $this->patchJson(route('cart.update', $product), [
            'quantity' => 2, 'quick_anchor_id' => $product->id,
        ])->assertOk()->assertJsonPath('count', 3);

        $removed = $this->patchJson(route('cart.update', $sibling), [
            'quantity' => 0, 'quick_anchor_id' => $product->id,
        ])->assertOk()->assertJsonPath('count', 2);
        $this->assertStringContainsString('data-product-id="'.$sibling->id.'"', $removed->json('html'));

        $this->assertArrayNotHasKey($sibling->id, session('shopping_cart'));
        $this->assertSame(2, session('shopping_cart')[$product->id]);
    }

    public function test_ajax_stock_rules_reject_overselling_without_changing_cart(): void
    {
        $product = $this->demo();
        $product->update(['stock_quantity' => 1]);

        $this->postJson(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertOk()->assertJsonPath('count', 1);
        $this->postJson(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->patchJson(route('cart.update', $product), ['quantity' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame(1, session('shopping_cart')[$product->id]);
    }

    public function test_ajax_variants_respect_root_category_and_hide_other_families(): void
    {
        $product = $this->demo();
        $parent = $product->category;
        $otherRoot = Category::create([
            'name' => 'Bağımsız kategori', 'slug' => 'bagimsiz-kategori',
            'is_active' => true, 'sort_order' => 999,
        ]);
        $outside = Product::create([
            'primary_category_id' => $otherRoot->id, 'sku' => 'OTHER-CAT-1',
            'name' => 'Farklı kategori özel kurşun', 'slug' => 'farkli-kategori-kursun',
            'price' => '12.50', 'stock_quantity' => 10, 'is_active' => true,
        ]);
        $child = Category::create([
            'parent_id' => $parent->id, 'name' => 'Fırdöndülü renkli kurşun',
            'slug' => 'firdondulu-renkli-kursun-yeni', 'is_active' => true, 'sort_order' => 1,
        ]);
        $within = Product::create([
            'primary_category_id' => $child->id, 'sku' => 'RELATED-CHILD',
            'name' => 'Fırdöndülü renkli kurşun 75 gr', 'slug' => 'related-child-surf',
            'price' => '88.90', 'stock_quantity' => 10, 'is_active' => true,
        ]);

        $response = $this->postJson(route('cart.add'), ['items' => [$product->id => 1]])->assertOk();
        $this->assertStringContainsString($within->name, $response->json('html'));
        $this->assertStringNotContainsString($outside->name, $response->json('html'));
        $this->assertStringContainsString('data-product-id="'.$within->id.'"', $response->json('html'));
    }

    public function test_ajax_free_shipping_status_uses_admin_settings_not_client_totals(): void
    {
        $product = $this->demo();
        $product->update(['price' => '500.00', 'stock_quantity' => 10]);

        $first = $this->postJson(route('cart.add'), [
            'items' => [$product->id => 1], 'shipping_fee' => 0,
        ])->assertOk()
            ->assertJsonPath('free_shipping', false)
            ->assertJsonPath('remaining_cents', 50000);
        $this->assertStringContainsString('500,00 ₺ daha ekle', $first->json('html'));

        $second = $this->postJson(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertOk()->assertJsonPath('free_shipping', true)->assertJsonPath('count', 2)
            ->assertJsonPath('remaining_cents', 0);
        $this->assertStringContainsString('Kargo ücretsiz!', $second->json('html'));
    }

    public function test_product_card_out_of_stock_button_is_disabled(): void
    {
        $product = $this->demo();
        $product->update(['stock_quantity' => 0]);
        $html = $this->get(route('category.show', $product->category->slug))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/aria-label="'.preg_quote($product->name, '/').' sepete ekle"[^>]*disabled/',
            $html
        );
    }

    public function test_standard_cart_add_without_javascript_still_redirects(): void
    {
        $product = $this->demo();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertRedirect(route('cart.index'));
    }

    public function test_product_card_quantity_is_numeric_and_tracks_cart_updates(): void
    {
        $product = $this->demo();
        $other = Product::query()->where('sku','DEMO-SURF-150')->firstOrFail();
        $this->get(route('category.show', $product->category->slug))->assertOk();
        $card = fn($p) => view('store.partials.product-card',['product'=>$p->fresh()->load('options','badges')])->render();
        $this->assertMatchesRegularExpression('/data-card-cart-quantity aria-hidden="true"\s+hidden\s*>\\s*<\/span>/', $card($product));
        $this->postJson(route('cart.add'), ['items'=>[$product->id=>2,$other->id=>1]])
            ->assertOk()->assertJsonPath('product_quantities.'.$product->id,2)
            ->assertJsonPath('product_quantities.'.$other->id,1);
        $this->assertMatchesRegularExpression('/data-card-cart-quantity aria-hidden="true"\s*>2<\/span>/', $card($product));
        $this->assertMatchesRegularExpression('/data-card-cart-quantity aria-hidden="true"\s*>1<\/span>/', $card($other));
        $this->patchJson(route('cart.update',$product),['quantity'=>0])->assertOk()
            ->assertJsonMissingPath('product_quantities.'.$product->id)
            ->assertJsonPath('product_quantities.'.$other->id,1);
        $this->assertMatchesRegularExpression('/data-card-cart-quantity aria-hidden="true"\s+hidden\s*>\\s*<\/span>/', $card($product));
    }

    public function test_option_card_sums_only_valid_options_and_keeps_other_products_separate(): void
    {
        $product=$this->demo();
        $other=Product::query()->where('sku','DEMO-SURF-150')->firstOrFail();
        $product->update(['option_label'=>'İğne kalınlığı','stock_quantity'=>12]);
        $a=$product->options()->create(['value'=>'0,25']);
        $b=$product->options()->create(['value'=>'0,30']);
        $this->postJson(route('cart.add'),['items'=>[$product->id=>2],'options'=>[$product->id=>$a->id]])->assertOk();
        $this->postJson(route('cart.add'),['items'=>[$product->id=>3],'options'=>[$product->id=>$b->id]])
            ->assertOk()->assertJsonPath('product_quantities.'.$product->id,5);
        $html=view('store.partials.product-card',['product'=>$product->fresh()->load('options','badges')])->render();
        $this->assertStringContainsString('product-card-option-link',$html);
        $this->assertMatchesRegularExpression('/data-card-cart-quantity aria-hidden="true"\s*>5<\/span>/',$html);
        $this->assertSame(0,\App\Support\ShoppingCart::quantityForProduct(request(),$other));
        $b->delete();
        $this->assertSame(2,\App\Support\ShoppingCart::quantityForProduct(request(),$product->fresh()));
    }

    public function test_lost_response_can_be_checked_without_repeating_cart_add(): void
    {
        $product = $this->demo();
        $operation = (string) \Illuminate\Support\Str::uuid();
        $url = route('cart.status',['operation'=>$operation]);
        $this->getJson($url)->assertOk()->assertJsonPath('operation_confirmed',false)->assertJsonPath('count',0);
        $this->postJson(route('cart.add'),['items'=>[$product->id=>1],'cart_operation'=>$operation])->assertOk();
        $response = $this->getJson($url)->assertOk()->assertJsonPath('operation_confirmed',true)
            ->assertJsonPath('count',1)->assertJsonPath('product_quantities.'.$product->id,1);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->getJson($url)->assertJsonPath('count',1);
        $other = (string) \Illuminate\Support\Str::uuid();
        $this->getJson(route('cart.status',['operation'=>$other]))->assertJsonPath('operation_confirmed',false);
        $this->patchJson(route('cart.update',$product),['quantity'=>0,'cart_operation'=>$other])->assertOk();
        $this->getJson($url)->assertJsonPath('operation_confirmed',false)->assertJsonPath('count',0);
        $this->getJson(route('cart.status',['operation'=>$other]))->assertJsonPath('operation_confirmed',true);
    }

    public function test_cart_flash_is_dismissible_without_duplicate_success_toast(): void
    {
        $product = $this->demo();
        $this->post(route('cart.add'),['items'=>[$product->id=>1]])->assertRedirect();
        $page = $this->get(route('cart.index'))->assertOk();
        $page->assertSee('data-cart-auto-dismiss',false)->assertDontSee('cart-add-fallback-toast',false);
        $this->get(route('home'))->assertOk()->assertSee('data-cart-status-url=',false);
    }
}
