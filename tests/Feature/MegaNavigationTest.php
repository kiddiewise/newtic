<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MegaNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name, '-', 'tr'),
            'parent_id' => $parent?->id,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_mobile_menu_has_full_screen_navigation_visuals_social_and_close_controls(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Fırdöndülü Renkli Surf Kurşun', $root);
        $grandchild = $this->category('Fırdöndülü Aparatlı Surf Kurşun', $child);

        $this->get('/')->assertOk()
            ->assertSee('data-mega-menu')
            ->assertSee('polaris-mega-menu')
            ->assertSee('mega-menu-shell')
            ->assertSee('data-mega-toggle')
            ->assertSee('data-mega-close')
            ->assertDontSee('mega-hero-art')
            ->assertSee('Kategorileri keşfet')
            ->assertSee('mega-social-grid')
            ->assertSee('https://instagram.com/polariskursun')
            ->assertSee('https://youtube.com/@polariskursun')
            ->assertSee('https://facebook.com/polariskursun')
            ->assertSee('https://wa.me/905462629002')
            ->assertSee($grandchild->name)
            ->assertSee(route('category.show', $root->slug))
            ->assertSee(route('category.show', $child->slug))
            ->assertSee('Tüm Renkli Surf Kurşun ürünleri');
    }

    public function test_current_root_category_pill_is_highlighted_with_aria_current(): void
    {
        $root = $this->category('Renkli Surf Kurşun');

        $html = $this->get(route('category.show', $root->slug))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/class="mobile-pill is-active"[^>]*aria-current="page"[^>]*>Renkli Surf Kurşun<\/a>/',
            $html
        );
    }

    public function test_nested_category_keeps_ancestor_pill_highlighted_without_false_current_page(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Fırdöndülü Renkli Surf Kurşun', $root);

        $html = $this->get(route('category.show', $child->slug))->assertOk()->getContent();
        $this->assertStringContainsString('class="mobile-pill is-active"', $html);
        $this->assertStringContainsString('Fırdöndülü Renkli Surf Kurşun', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/class="mobile-pill is-active"[^>]*aria-current="page"[^>]*>Renkli Surf Kurşun<\/a>/',
            $html
        );
    }

    public function test_category_cards_do_not_have_decorative_arrow_and_keep_their_links(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Fırdöndülü Renkli Surf Kurşun', $root);

        $html = $this->get(route('category.show', $root->slug))->assertOk()
            ->assertSee(route('category.show', $child->slug))
            ->assertSee($child->name)
            ->getContent();

        $this->assertStringNotContainsString('category-child-arrow', $html);
        $this->assertStringContainsString('category-child-card', $html);
    }

    public function test_home_pill_is_active_only_on_home_page(): void
    {
        $category = $this->category('Renkli Surf Kurşun');
        $home = $this->get('/')->assertOk()->getContent();
        $inner = $this->get(route('category.show', $category->slug))->assertOk()->getContent();

        $this->assertStringContainsString('mobile-pill-home is-active', $home);
        $this->assertStringNotContainsString('mobile-pill-home is-active', $inner);
    }
}
