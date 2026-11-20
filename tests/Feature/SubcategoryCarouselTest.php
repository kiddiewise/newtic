<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubcategoryCarouselTest extends TestCase
{
    use RefreshDatabase;

    private function createParentWithChildren(int $count): Category
    {
        $parent = Category::create([
            'name' => 'Renkli Surf Kurşun',
            'slug' => 'renkli-surf-kursun',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        for ($index = 1; $index <= $count; $index++) {
            Category::create([
                'parent_id' => $parent->id,
                'name' => 'Alt kategori '.$index,
                'slug' => 'alt-kategori-'.$index,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        return $parent;
    }

    public function test_one_child_uses_compact_single_card_without_autoplay_controls(): void
    {
        $parent = $this->createParentWithChildren(1);
        $html = $this->get(route('category.show', $parent->slug))->assertOk()
            ->assertSee('category-children-section--count-1')
            ->assertSee('class="category-children-grid"', false)
            ->assertSee('Alt kategori 1')
            ->assertDontSee('data-subcategory-carousel')
            ->assertDontSee('data-subcategory-prev')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="category-child-card"'));
    }

    public function test_two_and_three_child_layouts_keep_stable_grids(): void
    {
        foreach ([2, 3] as $count) {
            Category::query()->whereNotNull('parent_id')->delete();
            Category::query()->whereNull('parent_id')->delete();
            $parent = $this->createParentWithChildren($count);
            $html = $this->get(route('category.show', $parent->slug))->assertOk()
                ->assertSee('category-children-section--count-'.$count)
                ->assertDontSee('data-subcategory-carousel')
                ->getContent();
            $this->assertSame($count, substr_count($html, 'class="category-child-card"'));
            $this->assertStringNotContainsString('category-children-grid--scrolling', $html);
        }
    }

    public function test_four_children_stay_in_one_swipeable_autoplay_carousel(): void
    {
        $parent = $this->createParentWithChildren(4);
        $html = $this->get(route('category.show', $parent->slug))->assertOk()
            ->assertSee('data-subcategory-carousel')
            ->assertSee('data-autoplay-interval="3000"', false)
            ->assertSee('category-children-grid--scrolling')
            ->assertSee('data-subcategory-track')
            ->assertSee('data-subcategory-prev')
            ->assertSee('data-subcategory-next')
            ->assertSee('aria-label="Alt kategoriler, yatay kaydırılabilir"', false)
            ->getContent();
        $this->assertSame(4, substr_count($html, 'data-subcategory-slide'));
        $this->assertSame(4, substr_count($html, 'class="category-child-card"'));
        for ($i = 1; $i <= 4; $i++) {
            $this->assertStringContainsString('/kategori/alt-kategori-'.$i, $html);
        }
    }

    public function test_more_children_keep_unique_links_and_category_order(): void
    {
        $parent = $this->createParentWithChildren(8);
        $html = $this->get(route('category.show', $parent->slug))->assertOk()->getContent();
        $this->assertSame(8, substr_count($html, 'data-subcategory-slide'));
        $this->assertStringContainsString('data-autoplay-interval="3000"', $html);
        $previous = -1;
        for ($i = 1; $i <= 8; $i++) {
            $url = route('category.show', 'alt-kategori-'.$i);
            $this->assertSame(1, substr_count($html, 'href="'.$url.'"'));
            $next = strpos($html, 'Alt kategori '.$i, $previous + 1);
            $this->assertNotFalse($next);
            $this->assertGreaterThan($previous, $next);
            $previous = $next;
        }
    }

    public function test_frontend_implements_reduced_motion_pauses_and_touch_scrolling(): void
    {
        $js = file_get_contents(resource_path('js/subcategory-carousel.js'));
        $css = file_get_contents(resource_path('css/subcategory-carousel.css'));
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $js);
        $this->assertStringContainsString("document.hidden", $js);
        $this->assertStringContainsString('IntersectionObserver', $js);
        $this->assertStringContainsString('touchstart', $js);
        $this->assertStringContainsString('scroll-snap-type:x mandatory', $css);
        $this->assertStringContainsString('category-children-section--count-1', $css);
        $this->assertStringContainsString('category-children-section--count-2', $css);
        $this->assertStringContainsString('category-children-section--count-3', $css);
        $this->assertStringContainsString("import './subcategory-carousel.js'", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString("@import './subcategory-carousel.css'", file_get_contents(resource_path('css/app.css')));
    }
}
