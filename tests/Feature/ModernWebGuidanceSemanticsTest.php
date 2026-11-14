<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModernWebGuidanceSemanticsTest extends TestCase
{
    public function test_free_shipping_meter_is_native_progress_with_correct_amount_and_truck(): void
    {
        $cart = [
            'free_shipping' => false,
            'threshold_cents' => 100000,
            'subtotal_cents' => 50000,
            'remaining_cents' => 50000,
        ];
        $html = view('store.partials.cart-shipping-progress', compact('cart'))->render();
        $this->assertStringContainsString('<progress class="cart-shipping-native-progress"', $html);
        $this->assertStringContainsString('value="50000"', $html);
        $this->assertStringContainsString('max="100000"', $html);
        $this->assertStringContainsString('--shipping-progress: 50%', $html);
        $this->assertStringContainsString('class="cart-shipping-truck"', $html);
        $this->assertStringNotContainsString('role="progressbar"', $html);

        $cart['free_shipping'] = true;
        $cart['subtotal_cents'] = 120000;
        $cart['remaining_cents'] = 0;
        $free = view('store.partials.cart-shipping-progress', compact('cart'))->render();
        $this->assertStringContainsString('Kargon bizden!', $free);
        $this->assertStringContainsString('value="100000"', $free);
        $this->assertStringContainsString('--shipping-progress: 100%', $free);
    }

    public function test_scrollbar_supports_standard_properties_and_vendor_fallback(): void
    {
        $css = file_get_contents(resource_path('css/admin-2026-polish.css'));
        $this->assertStringContainsString('scrollbar-width:thin', $css);
        $this->assertStringContainsString('scrollbar-color:#d4e4e4 transparent', $css);
        $this->assertStringContainsString('@supports not (scrollbar-color: auto)', $css);
        $this->assertStringContainsString('::-webkit-scrollbar-thumb', $css);

        $storeCss = file_get_contents(resource_path('css/storefront-refinements.css'));
        $this->assertStringContainsString('.cart-shipping-native-progress::-webkit-progress-value', $storeCss);
        $this->assertStringContainsString('.cart-shipping-native-progress::-moz-progress-bar', $storeCss);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $storeCss);
    }

    public function test_modern_guidance_is_configured_to_run_without_telemetry_and_without_deploy(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/modern-web-guidance.yml'));
        $this->assertStringContainsString("DISABLE_TELEMETRY: '1'", $workflow);
        $this->assertStringContainsString('modern-web-guidance@1.0.0 legacy-scan resources --json', $workflow);
        $this->assertStringNotContainsString('${{ secrets.', $workflow);
        $this->assertStringNotContainsString('ssh ', $workflow);
        $this->assertStringNotContainsString('deploy-staging.sh', $workflow);
    }
}
