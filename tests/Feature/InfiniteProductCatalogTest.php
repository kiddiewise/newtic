<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfiniteProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function populate(int $count = 31): Category
    {
        $category = Category::create([
            'name' => 'Surf Kurşun',
            'slug' => 'surf-kursun',
            'is_active' => true,
        ]);
        for ($n = 1; $n <= $count; $n++) {
            Product::create([
                'primary_category_id' => $category->id,
                'sku' => sprintf('SCROLL-%03d', $n),
                'slug' => sprintf('scroll-product-%03d', $n),
                'name' => sprintf('Polaris Scroll %03d', $n),
                'price' => '99.90',
                'stock_quantity' => 15,
                'is_active' => true,
            ]);
        }

        return $category;
    }

    public function test_search_initial_page_renders_only_twelve_products_with_real_fallback_pagination(): void
    {
        $this->populate();
        $response = $this->get(route('search'))->assertOk();
        $html = $response->getContent();

        $this->assertSame(12, substr_count($html, '<article class="product-card">'));
        $this->assertStringContainsString('Polaris Scroll 001', $html);
        $this->assertStringContainsString('Polaris Scroll 012', $html);
        $this->assertStringNotContainsString('Polaris Scroll 013', $html);
        $this->assertStringContainsString('data-infinite-product-grid', $html);
        $this->assertStringContainsString('data-infinite-next-url=', $html);
        $this->assertStringContainsString('data-infinite-pagination', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('data-infinite-trigger', $html);
    }

    public function test_search_ajax_next_pages_return_only_current_page_cards_and_eventually_finish(): void
    {
        $this->populate();
        $pageTwo = $this->getJson(route('search', ['page' => 2]))->assertOk()
            ->assertJsonPath('heading', 'Tüm ürünler')
            ->assertJsonPath('count', 31);
        $htmlTwo = $pageTwo->json('html');
        $this->assertSame(12, substr_count($htmlTwo, '<article class="product-card">'));
        $this->assertStringContainsString('Polaris Scroll 013', $htmlTwo);
        $this->assertStringContainsString('Polaris Scroll 024', $htmlTwo);
        $this->assertStringNotContainsString('Polaris Scroll 012', $htmlTwo);
        $this->assertStringContainsString('page=3', $htmlTwo);

        $pageThree = $this->getJson(route('search', ['page' => 3]))->assertOk()
            ->assertJsonPath('count', 31);
        $htmlThree = $pageThree->json('html');
        $this->assertSame(7, substr_count($htmlThree, '<article class="product-card">'));
        $this->assertStringContainsString('Polaris Scroll 025', $htmlThree);
        $this->assertStringContainsString('Polaris Scroll 031', $htmlThree);
        $this->assertStringContainsString('data-infinite-next-url=""', $htmlThree);
    }

    public function test_category_supports_the_same_twelve_item_html_and_json_fragments(): void
    {
        $category = $this->populate(26);
        $first = $this->get(route('category.show', $category->slug))->assertOk()->getContent();
        $this->assertSame(12, substr_count($first, '<article class="product-card">'));
        $this->assertStringContainsString('26 ürün', $first);
        $this->assertStringContainsString('data-infinite-catalog', $first);
        $this->assertStringNotContainsString('Polaris Scroll 013', $first);

        $second = $this->getJson(route('category.show', ['slug' => $category->slug, 'page' => 2]))
            ->assertOk()->assertJsonPath('count', 26);
        $this->assertSame(12, substr_count($second->json('html'), '<article class="product-card">'));
        $this->assertStringContainsString('Polaris Scroll 013', $second->json('html'));

        $last = $this->getJson(route('category.show', ['slug' => $category->slug, 'page' => 3]))
            ->assertOk()->assertJsonPath('count', 26);
        $this->assertSame(2, substr_count($last->json('html'), '<article class="product-card">'));
        $this->assertStringContainsString('data-infinite-next-url=""', $last->json('html'));
    }

    public function test_live_search_preserves_the_filtered_query_across_auto_loaded_pages(): void
    {
        $this->populate(28);
        $filtered = $this->getJson(route('search', ['q' => 'Polaris Scroll', 'page' => 2]))
            ->assertOk()->assertJsonPath('count', 28);
        $html = $filtered->json('html');
        $this->assertSame(12, substr_count($html, '<article class="product-card">'));
        $this->assertStringContainsString('q=Polaris', $html);
        $this->assertStringContainsString('page=3', $html);

        $empty = $this->getJson(route('search', ['q' => 'Bulunmayan Ürün']))
            ->assertOk()->assertJsonPath('count', 0);
        $this->assertStringContainsString('Aradığınız kriterde ürün bulunamadı.', $empty->json('html'));
        $this->assertStringContainsString('data-infinite-next-url=""', $empty->json('html'));
    }

    public function test_browser_progressive_enhancement_and_ajax_refresh_are_integrated(): void
    {
        $javascript = file_get_contents(resource_path('js/infinite-catalog.js'));
        $live = file_get_contents(resource_path('js/live-search.js'));
        $css = file_get_contents(resource_path('css/infinite-catalog.css'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('IntersectionObserver', $javascript);
        $this->assertStringContainsString("rootMargin: '550px", $javascript);
        $this->assertStringContainsString('AbortController', $javascript);
        $this->assertStringContainsString('grid.append(...cards)', $javascript);
        $this->assertStringContainsString('restoreLinks', $javascript);
        $this->assertStringContainsString('newtic:catalog-results-replaced', $live);
        $this->assertStringContainsString('disposeInfiniteCatalog(results)', $live);
        $this->assertStringContainsString("import './infinite-catalog.js';", $app);
        $this->assertStringContainsString('[data-infinite-pagination]', $css);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);
    }
}
