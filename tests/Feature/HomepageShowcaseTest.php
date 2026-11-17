<?php

namespace Tests\Feature;

use App\Models\HomeSection;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_horizontal_product_carousel_without_category_heading_or_announcement_bar(): void
    {
        $this->seed(PolarisDemoSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-showcase-carousel', false)
            ->assertSee('data-carousel-next', false)
            ->assertDontSee('>KEŞFET<', false)
            ->assertDontSee('class="utility-bar"', false)
            ->assertDontSee('class="announcement-ticker"', false);
    }

    public function test_empty_additional_product_block_is_not_visible(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        HomeSection::create([
            'type' => HomeSection::TYPE_PRODUCTS,
            'title' => 'İçi boş vitrin',
            'sort_order' => 77,
            'is_active' => true,
            'settings' => [],
        ]);

        $this->get('/')->assertOk()->assertDontSee('İçi boş vitrin');
    }

    public function test_countdown_renders_only_when_active_future_deadline_exists(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $section = HomeSection::query()->where('type', HomeSection::TYPE_PRODUCTS)->firstOrFail();
        $this->get('/')->assertDontSee('data-showcase-countdown', false);

        $section->update(['settings' => [
            'countdown_label' => 'Kampanyanın bitmesine:',
            'countdown_ends_at' => now('Europe/Istanbul')->addDays(5)->format('Y-m-d\TH:i'),
        ]]);
        $this->get('/')->assertSee('data-showcase-countdown', false)->assertSee('Kampanyanın bitmesine:');

        $section->update(['settings' => [
            'countdown_label' => 'Kampanyanın bitmesine:',
            'countdown_ends_at' => now('Europe/Istanbul')->subDay()->format('Y-m-d\TH:i'),
        ]]);
        $this->get('/')->assertDontSee('data-showcase-countdown', false);
    }
}
