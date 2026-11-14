<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShopAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCartRefinementsTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create([
            'name' => 'Surf', 'slug' => 'surf', 'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Polaris 75 gr', 'sku' => 'REF-75',
            'slug' => 'polaris-75-gr', 'price' => '50.00',
            'compare_at_price' => '80.00',
            'stock_quantity' => 10, 'is_active' => true,
        ]);
    }

    public function test_cart_shows_neutral_shipping_truck_savings_tag_and_icon_only_delete(): void
    {
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Ürünlerimizi keşfetmeye hazır mısın?');

        $product = $this->product();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $response = $this->get(route('cart.index'))->assertOk()
            ->assertSee('>Sepet</strong>', false)
            ->assertSee('İndirim toplamı')
            ->assertSee('Bu alışverişte 30,00 ₺ indirim kazandın')
            ->assertSee('class="cart-shipping-truck"', false)
            ->assertSee('class="cart-shipping-native-progress"', false)
            ->assertSee('aria-label="Ücretsiz kargo hedefi"', false)
            ->assertDontSee('role="progressbar"', false)
            ->assertSee('href="#ui-trash-2"', false)
            ->assertSee('href="#ui-tag"', false)
            ->assertDontSee('class="cart-mobile-favorites"', false)
            ->assertDontSee('Ücretsiz kargo kazandın!');

        $this->assertGreaterThanOrEqual(3, substr_count($response->getContent(), 'class="cart-shipping-panel'));
    }

    public function test_logos_follow_admin_appearance_in_login_and_footer(): void
    {
        ShopAppearance::current()->update([
            'desktop_logo_path' => 'storage/branding/store-desktop.png',
            'mobile_logo_path' => 'storage/branding/store-mobile.png',
        ]);
        $this->get(route('account.login'))->assertOk()
            ->assertSee('storage/branding/store-mobile.png')
            ->assertSee('class="mobile-auth-brand"', false);
        $this->get(route('home'))->assertOk()
            ->assertSee('class="footer-brandmark"', false)
            ->assertSee('storage/branding/store-desktop.png');
    }

    public function test_header_is_split_from_search_and_navy_buttons_remain_consistent(): void
    {
        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('class="mobile-subnav"', $home);
        $this->assertMatchesRegularExpression(
            '/<\/header>\s*<div class="mobile-subnav">\s*<form class="mobile-search-bar"/',
            $home
        );
        $search = $this->get(route('search'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="Ürün ara"', $search);
        $this->assertStringNotContainsString('ÜRÜN KATALOĞU', $search);

        $css = file_get_contents(resource_path('css/storefront-refinements.css'));
        $app = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("@import './storefront-refinements.css';", $app);
        $this->assertStringContainsString('background:var(--polaris-action)!important;', $css);
        $this->assertStringContainsString('.mobile-site-header.has-announcements', $css);
        $this->assertStringContainsString('.cart-shipping-truck', $css);
        $this->assertStringContainsString('.checkout-invoice-options label:has(input:checked)', $css);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);
    }
    public function test_search_and_categories_only_render_on_browsing_pages(): void
    {
        $product = $this->product();

        foreach ([
            route('home'),
            route('category.show', $product->category->slug),
            route('search'),
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('class="mobile-subnav"', false)
                ->assertSee('class="mobile-search-bar"', false)
                ->assertSee('class="mobile-category-pills"', false);
        }

        foreach ([
            route('product.show', $product->slug),
            route('cart.index'),
            route('account.login'),
            route('account.register'),
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('class="mobile-subnav"', false)
                ->assertDontSee('class="mobile-category-pills"', false);
        }

        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();

        $this->get(route('checkout.index'))->assertOk()
            ->assertDontSee('class="mobile-subnav"', false)
            ->assertDontSee('class="mobile-category-pills"', false);
    }

    public function test_mega_menu_cart_badge_uses_independent_compact_white_number_style(): void
    {
        $product = $this->product();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/class="mega-cart-link"[^>]*>.*?class="cart-icon-badge">1<\/span>/s',
            $html
        );

        $css = file_get_contents(resource_path('css/storefront-refinements.css'));
        $this->assertStringContainsString('.mobile-site-header .mega-menu-top .mega-cart-link>.cart-icon-badge', $css);
        $this->assertStringContainsString('font-size:10px!important;', $css);
        $this->assertStringContainsString('color:#fff!important;', $css);
        $this->assertStringContainsString('right:-4px!important;', $css);
    }

}
