<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolarisTypographyTest extends TestCase
{
    use RefreshDatabase;

    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/polaris-typography.css'));
    }

    public function test_font_files_are_loaded_in_both_store_and_admin_with_swap(): void
    {
        foreach ([
            resource_path('views/layouts/store.blade.php'),
            resource_path('views/admin/layout.blade.php'),
        ] as $path) {
            $html = (string) file_get_contents($path);
            $this->assertStringContainsString('fonts.googleapis.com/css2', $html);
            $this->assertStringContainsString('family=Montserrat:wght@400;500;600;700', $html);
            $this->assertStringContainsString('family=Plus+Jakarta+Sans:wght@400;500;600;700', $html);
            $this->assertStringContainsString('display=swap', $html);
            $this->assertStringContainsString('fonts.gstatic.com', $html);
        }
    }

    public function test_typography_is_the_last_global_style_layer(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $corporate = strpos($css, "@import './polaris-corporate-theme.css';");
        $typography = strpos($css, "@import './polaris-typography.css';");

        $this->assertNotFalse($corporate);
        $this->assertNotFalse($typography);
        $this->assertGreaterThan($corporate, $typography);
    }

    public function test_all_customer_body_text_uses_montserrat_and_titles_use_jakarta(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('--polaris-font-body:"Montserrat",sans-serif', $css);
        $this->assertStringContainsString('--polaris-font-heading:"Plus Jakarta Sans",sans-serif', $css);
        $this->assertStringContainsString('font-weight:400;', $css);
        $this->assertStringContainsString('body :is(h1,h2,h3,h4,h5,h6)', $css);
        $this->assertStringContainsString('.product-card-name{font-weight:600', $css);
        $this->assertStringContainsString('body.is-auth .mobile-auth-tabs a.is-active', $css);
    }

    public function test_product_purchase_actions_and_admin_use_medium_not_black_font_weights(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('.pdp-sticky-bar .pdp-sticky-actions .pdp-sticky-variant-title', $css);
        $this->assertStringContainsString('font-weight:500!important;', $css);
        $this->assertStringContainsString('.pdp-sticky-gramages[data-scroll-ribbon]>span', $css);
        $this->assertStringContainsString('.cart-primary,.cart-checkout-disabled,.cart-checkout-action', $css);
        $this->assertStringContainsString('body.admin-body{', $css);
        $this->assertStringContainsString('.admin-page-heading h1{font-weight:700}', $css);
        $this->assertStringContainsString('.admin-primary,.admin-secondary,.admin-danger{font-weight:600}', $css);

        $critical = (string) file_get_contents(
            resource_path('views/admin/partials/critical-styles.blade.php')
        );
        $this->assertStringContainsString('font-family:"Montserrat",sans-serif', $critical);
        $this->assertStringContainsString('font-family:"Plus Jakarta Sans",sans-serif', $critical);
    }

    public function test_store_and_admin_pages_still_render_with_fonts_available(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('fonts.googleapis.com/css2', false)
            ->assertSee('family=Montserrat', false)
            ->assertSee('Plus+Jakarta+Sans', false);
        $this->get(route('account.login'))->assertOk()
            ->assertSee('fonts.googleapis.com/css2', false);

        $this->get(route('admin.login'))->assertOk()
            ->assertSee('fonts.googleapis.com/css2', false)
            ->assertSee('Montserrat', false);
    }
}
