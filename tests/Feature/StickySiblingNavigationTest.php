<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StickySiblingNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, ?Category $parent = null, bool $active = true, int $position = 0): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name, '-', 'tr').'-'.strtolower(\Illuminate\Support\Str::random(5)),
            'parent_id' => $parent?->id,
            'sort_order' => $position,
            'is_active' => $active,
        ]);
    }

    private function product(Category $category): Product
    {
        return Product::query()->create([
            'name' => 'Klasik Renkli Surf Kurşun 125 gr',
            'slug' => 'klasik-surf-125',
            'sku' => 'STICKY-125',
            'primary_category_id' => $category->id,
            'price' => '99.90',
            'stock_quantity' => 8,
            'is_active' => true,
        ]);
    }

    public function test_nested_category_shows_only_its_parent_and_active_sibling_category_pills(): void
    {
        $parent = $this->category('Renkli Surf Kurşun');
        $first = $this->category('Fırdöndülü Renkli', $parent, true, 1);
        $current = $this->category('Klasik Renkli Surf Kurşun', $parent, true, 2);
        $third = $this->category('Aparatlı Renkli Surf Kurşun', $parent, true, 3);
        $hidden = $this->category('Gizli Ürünler', $parent, false, 4);
        $unrelated = $this->category('Fosforlu Surf Kurşun');
        $this->category('Fosforlu Alt', $unrelated);
        $this->product($current);

        $response = $this->get(route('category.show', $current->slug))->assertOk()
            ->assertSee('data-sticky-sibling-nav')
            ->assertSee('data-sticky-sibling-track')
            ->assertSee('data-sticky-sibling-current')
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('yatay kaydırılabilir')
            ->assertSee('Klasik Renkli Surf Kurşun 125 gr');

        $html = $response->getContent();
        preg_match('/<nav class="sticky-sibling-nav".*?<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches);
        $nav = $matches[0];

        $this->assertStringContainsString(route('category.show', $parent->slug), $nav);
        $this->assertStringContainsString('>Tümü</a>', $nav);
        $this->assertStringContainsString(route('category.show', $first->slug), $nav);
        $this->assertStringContainsString(route('category.show', $current->slug), $nav);
        $this->assertStringContainsString(route('category.show', $third->slug), $nav);
        $this->assertStringNotContainsString(route('category.show', $hidden->slug), $nav);
        $this->assertStringNotContainsString(route('category.show', $unrelated->slug), $nav);
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
        $this->assertSame(1, substr_count($nav, 'data-sticky-sibling-current'));
        $this->assertTrue(
            strpos($nav, $first->name) < strpos($nav, $current->name)
                && strpos($nav, $current->name) < strpos($nav, $third->name)
        );
    }

    public function test_root_category_and_other_store_pages_do_not_get_sticky_sibling_menu(): void
    {
        $parent = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Klasik Surf Kurşun', $parent);
        $product = $this->product($child);

        $this->get(route('category.show', $parent->slug))->assertOk()
            ->assertDontSee('data-sticky-sibling-nav');
        $this->get('/')->assertOk()->assertDontSee('data-sticky-sibling-nav');
        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertDontSee('data-sticky-sibling-nav');
    }

    public function test_deep_category_uses_immediate_siblings_not_grandparent_children(): void
    {
        $root = $this->category('Renkli Surf');
        $group = $this->category('Fırdöndülü', $root, true, 1);
        $otherGroup = $this->category('Klasik', $root, true, 2);
        $leaf = $this->category('100 gr', $group, true, 1);
        $sibling = $this->category('125 gr', $group, true, 2);

        $html = $this->get(route('category.show', $leaf->slug))->assertOk()->getContent();
        preg_match('/<nav class="sticky-sibling-nav".*?<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches);
        $nav = $matches[0];
        $this->assertStringContainsString(route('category.show', $group->slug), $nav);
        $this->assertStringContainsString(route('category.show', $sibling->slug), $nav);
        $this->assertStringNotContainsString(route('category.show', $otherGroup->slug), $nav);
        $this->assertStringNotContainsString(route('category.show', $root->slug), $nav);
    }

    public function test_single_active_child_still_has_parent_return_link(): void
    {
        $parent = $this->category('Hediyelik');
        $child = $this->category('Karikatür Biblo', $parent);
        $html = $this->get(route('category.show', $child->slug))->assertOk()->getContent();
        preg_match('/<nav class="sticky-sibling-nav".*?<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString(route('category.show', $parent->slug), $matches[0]);
        $this->assertStringContainsString(route('category.show', $child->slug), $matches[0]);
    }

    public function test_ajax_category_pagination_remains_products_only(): void
    {
        $root = $this->category('Renkli Surf');
        $child = $this->category('Klasik', $root);
        $this->product($child);

        $this->getJson(route('category.show', $child->slug).'?page=1')->assertOk()
            ->assertJsonStructure(['html', 'count'])
            ->assertDontSee('data-sticky-sibling-nav');
    }

    public function test_directional_behavior_and_accessible_hiding_are_bundled(): void
    {
        $js = file_get_contents(resource_path('js/sticky-sibling-navigation.js'));
        $css = file_get_contents(resource_path('css/sticky-sibling-navigation.css'));
        $view = file_get_contents(resource_path('views/store/partials/sticky-sibling-navigation.blade.php'));

        $this->assertStringContainsString('setVisible(nextDirection > 0)', $js);
        $this->assertStringContainsString('originalSubnav.getBoundingClientRect().bottom', $js);
        $this->assertStringContainsString('header.getBoundingClientRect().bottom', $js);
        $this->assertStringContainsString("window.addEventListener('scroll'", $js);
        $this->assertStringContainsString('nav.inert = !show', $js);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $css);
        $this->assertStringContainsString('position:fixed', $css);
        $this->assertStringContainsString('z-index:134', $css);
        $this->assertStringContainsString('scroll-snap-type:x proximity', $css);
        $this->assertStringContainsString('data-sticky-sibling-current', $view);
        $this->assertStringContainsString("import './sticky-sibling-navigation.js'", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString("@import './sticky-sibling-navigation.css'", file_get_contents(resource_path('css/app.css')));
    }
}
