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
            ->assertSee('role="progressbar"', false)
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
}
