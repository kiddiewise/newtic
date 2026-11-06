<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileHomepageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_page_has_search_navigation_and_full_bleed_image_slider(): void
    {
        $this->seed(PolarisDemoSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('mobile-site-header')
            ->assertSee('mobile-search-bar')
            ->assertSee('mobile-category-pills')
            ->assertSee('homepage-hero')
            ->assertSee('slider-count')
            ->assertSee('data-slide-current')
            ->assertSee('Kategorileri keşfet')
            ->assertSee('assets/categories/renkli-kursun.svg')
            ->assertSee('promo-surf.svg')
            ->assertSee('Popüler Ürünler');
    }

    public function test_section_order_matches_mobile_reference(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $html = $this->get('/')->assertOk()->getContent();

        $slider = strpos($html, 'homepage-hero');
        $categories = strpos($html, 'category-section');
        $promos = strpos($html, 'banner-strip');
        $products = strpos($html, 'product-grid');
        $this->assertNotFalse($slider);
        $this->assertNotFalse($categories);
        $this->assertNotFalse($promos);
        $this->assertNotFalse($products);
        $this->assertTrue($slider < $categories && $categories < $promos && $promos < $products);
    }

    public function test_inactive_category_is_removed_from_mobile_shortcuts(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        Category::query()->where('slug', 'olta-takimlari')->update(['is_active' => false]);

        $this->get('/')->assertOk()->assertDontSee('Olta Takımları');
    }

    public function test_slider_does_not_render_overlay_text_or_buttons(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $html = $this->get('/')->assertOk()->getContent();
        $from = strpos($html, '<div class="slide-track">');
        $until = strpos($html, '</div>', strpos($html, '</picture>', $from));
        $slideContent = substr($html, $from, $until - $from);
        $this->assertStringNotContainsString('slide-title', $slideContent);
        $this->assertStringNotContainsString('slide-cta', $slideContent);
        $this->assertStringNotContainsString('<h1', $slideContent);
        $this->assertStringContainsString('<picture>', $slideContent);
    }
}
