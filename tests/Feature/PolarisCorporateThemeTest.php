<?php

namespace Tests\Feature;

use Tests\TestCase;

class PolarisCorporateThemeTest extends TestCase
{
    private function theme(): string
    {
        return (string) file_get_contents(resource_path('css/polaris-corporate-theme.css'));
    }

    public function test_corporate_palette_is_shared_by_storefront_and_admin_without_a_dark_page(): void
    {
        $css = $this->theme();
        foreach (['#092037', '#f68220', '#424769', 'color-scheme:light', '--polaris-paper:#ffffff'] as $token) {
            $this->assertStringContainsString($token, $css);
        }

        $app = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("@import './polaris-corporate-theme.css';", $app);
        $this->assertGreaterThan(
            strpos($app, "@import './product-reviews.css';"),
            strpos($app, "@import './polaris-corporate-theme.css';")
        );

        $store = (string) file_get_contents(resource_path('views/layouts/store.blade.php'));
        $admin = (string) file_get_contents(resource_path('views/admin/layout.blade.php'));
        $this->assertStringContainsString('name="theme-color" content="#092037"', $store);
        $this->assertStringContainsString('name="theme-color" content="#092037"', $admin);
    }

    public function test_the_orange_conversion_button_uses_high_contrast_navy_text(): void
    {
        $css = $this->theme();
        $this->assertStringContainsString('background:var(--polaris-orange);', $css);
        $this->assertStringContainsString('color:var(--polaris-navy);', $css);

        // WCAG contrast for ordinary button text: orange on white is not
        // sufficient; navy text on orange exceeds 4.5:1.
        $navy = [9, 32, 55];
        $orange = [246, 130, 32];
        $luminance = static function (array $rgb): float {
            $linear = array_map(static function (int $channel): float {
                $v = $channel / 255;
                return $v <= .04045 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
            }, $rgb);

            return .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
        };
        $contrast = ($luminance($orange) + .05) / ($luminance($navy) + .05);
        $this->assertGreaterThan(4.5, $contrast);
    }

    public function test_admin_first_paint_and_checkout_state_colors_are_preserved(): void
    {
        $critical = (string) file_get_contents(
            resource_path('views/admin/partials/critical-styles.blade.php')
        );
        $this->assertStringContainsString('.admin-side-link.is-active{background:#f68220;color:#092037}', $critical);
        $this->assertStringContainsString('.admin-primary{background:#f68220;color:#092037}', $critical);

        $css = $this->theme();
        $this->assertStringContainsString('body.is-auth .mobile-auth-tabs a.is-active', $css);
        $this->assertStringContainsString('.pdp-sticky-bar>.pdp-sticky-gramages[data-scroll-ribbon]', $css);
        $this->assertStringContainsString('.admin-sidebar', $css);
        $this->assertStringContainsString('.cart-primary:not(:disabled)', $css);
        $this->assertStringContainsString('.review-star-picker input:checked~label', $css);
        $this->assertStringContainsString('.pdp-sticky-actions .pdp-sticky-add:disabled', $css);
        $this->assertStringContainsString('/* verified success retains green */', $css);
    }
}
