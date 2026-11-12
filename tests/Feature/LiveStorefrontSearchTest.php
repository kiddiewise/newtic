<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveStorefrontSearchTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, string $name, string $slug, string $sku): Product
    {
        return Product::create([
            'primary_category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'price' => '49.90',
            'stock_quantity' => 8,
            'is_active' => true,
        ]);
    }

    private function category(string $name, string $slug, ?Category $parent = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parent?->id,
            'is_active' => true,
        ]);
    }

    public function test_header_search_provides_six_safe_results_for_active_category_products(): void
    {
        $root = $this->category('Surf', 'surf');
        for ($n = 1; $n <= 9; $n++) {
            $this->product($root, 'Surf kurşun '.$n, 'surf-'.$n, 'S-'.$n);
        }
        $hidden = $this->category('Gizli', 'gizli');
        $child = $this->category('Alt', 'alt', $hidden);
        $this->product($child, 'Surf gizli kurşun', 'surf-gizli', 'S-HIDDEN');
        $hidden->update(['is_active' => false]);

        $response = $this->getJson(route('search.suggest', ['q' => 'Surf']))->assertOk();
        $items = $response->json('items');
        $this->assertCount(6, $items);
        $this->assertSame('Surf kurşun 1', $items[0]['name']);
        $this->assertSame(route('product.show', 'surf-1'), $items[0]['url']);
        $this->assertSame('49,90 ₺', $items[0]['price']);
        $this->assertFalse(collect($items)->contains(fn ($item) => $item['name'] === 'Surf gizli kurşun'));
        $cache = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);

        $this->getJson(route('search.suggest', ['q' => '']))->assertOk()
            ->assertExactJson(['items' => []]);
        $this->getJson(route('search.suggest', ['q' => str_repeat('x', 101)]))->assertStatus(422);
    }

    public function test_ajax_catalog_filters_and_clearing_the_query_restores_all_visible_products(): void
    {
        $category = $this->category('Surf', 'surf');
        $this->product($category, 'Surf renkli kurşun', 'surf-renkli', 'SKU-SURF');
        $this->product($category, 'Jig kurşun', 'jig', 'SKU-JIG');
        $hidden = $this->category('Gizli', 'hidden');
        $hiddenChild = $this->category('Hidden child', 'hidden-child', $hidden);
        $this->product($hiddenChild, 'Surf gizli', 'surf-gizli', 'HIDDEN');
        $hidden->update(['is_active' => false]);

        $search = $this->getJson(route('search', ['q' => 'Surf']))->assertOk()
            ->assertJsonPath('heading', 'Arama: Surf')
            ->assertJsonPath('count', 1);
        $this->assertStringContainsString('Surf renkli kurşun', $search->json('html'));
        $this->assertStringNotContainsString('Jig kurşun', $search->json('html'));
        $this->assertStringNotContainsString('Surf gizli', $search->json('html'));

        $empty = $this->getJson(route('search'))->assertOk()
            ->assertJsonPath('heading', 'Tüm ürünler')
            ->assertJsonPath('count', 2);
        $this->assertStringContainsString('Surf renkli kurşun', $empty->json('html'));
        $this->assertStringContainsString('Jig kurşun', $empty->json('html'));
        $this->assertStringNotContainsString('Surf gizli', $empty->json('html'));

        $html = $this->get(route('search'))->assertOk()->getContent();
        $this->assertStringContainsString('data-live-catalog-form', $html);
        $this->assertStringContainsString('data-live-search-results', $html);
        $this->assertStringContainsString('data-search-suggest-form', $html);
        $this->assertStringContainsString('data-suggest-url', $html);
    }

    public function test_ajax_search_and_checkout_guard_keep_full_server_fallbacks(): void
    {
        $category = $this->category('Surf', 'surf');
        $product = $this->product($category, 'Surf 75 gr', 'surf-75', 'SKU-75');

        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertSee('Birlikte alınan diğer ürünler')
            ->assertSee('href="#ui-message-circle"', false)
            ->assertDontSee('Seçili ürün:');

        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();

        $checkout = $this->get(route('checkout.index'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($checkout, 'data-checkout-state="locked"'));
        $this->assertSame(2, substr_count($checkout, 'data-checkout-button-label'));
        $this->assertStringContainsString('class="checkout-lock-shackle"', $checkout);
        $this->assertStringContainsString('<noscript>', $checkout);

        $js = file_get_contents(resource_path('js/checkout.js'));
        $this->assertStringContainsString('checkoutForm.checkValidity()', $js);
        $this->assertStringContainsString('setCustomValidity', $js);
        $this->assertStringContainsString('button.disabled = !ready;', $js);
        $this->assertStringContainsString('pageshow', $js);

        $styles = file_get_contents(resource_path('css/commerce-interactions.css'));
        $this->assertStringContainsString('@keyframes checkout-shackle-open', $styles);
        $this->assertStringContainsString('.search-suggest-results', $styles);

        $script = file_get_contents(resource_path('js/live-search.js'));
        $this->assertStringContainsString('AbortController', $script);
        $this->assertStringContainsString("input.addEventListener('input'", $script);
        $this->assertStringContainsString("history.replaceState", $script);
    }
}
