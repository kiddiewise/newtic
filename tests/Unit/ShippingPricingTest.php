<?php

namespace Tests\Unit;

use App\Models\StoreSetting;
use App\Support\ShippingPricing;
use PHPUnit\Framework\TestCase;

final class ShippingPricingTest extends TestCase
{
    public function test_shipping_campaign_depends_on_product_subtotal_not_total_with_shipping(): void
    {
        $settings = new StoreSetting(['free_shipping_threshold' => '1000.00', 'shipping_fee' => '79.00']);

        $below = ShippingPricing::forSubtotal(80000, $settings);
        $this->assertSame(80000, $below['total_cents'] - $below['shipping_cents']);
        $this->assertSame(7900, $below['shipping_cents']);
        $this->assertSame(87900, $below['total_cents']);
        $this->assertSame(20000, $below['remaining_cents']);
        $this->assertFalse($below['free_shipping']);

        $near = ShippingPricing::forSubtotal(95000, $settings);
        $this->assertSame(102900, $near['total_cents']);
        $this->assertSame(7900, $near['shipping_cents']);
        $this->assertSame(5000, $near['remaining_cents']);
        $this->assertFalse($near['free_shipping']);

        $atThreshold = ShippingPricing::forSubtotal(100000, $settings);
        $this->assertSame(0, $atThreshold['shipping_cents']);
        $this->assertSame(100000, $atThreshold['total_cents']);
        $this->assertTrue($atThreshold['free_shipping']);

        $above = ShippingPricing::forSubtotal(102900, $settings);
        $this->assertSame(0, $above['shipping_cents']);
        $this->assertSame(102900, $above['total_cents']);
        $this->assertTrue($above['free_shipping']);
    }

    public function test_empty_cart_has_no_shipping_and_a_zero_threshold_is_free_for_nonempty_cart(): void
    {
        $settings = new StoreSetting(['free_shipping_threshold' => '1000.00', 'shipping_fee' => '79.00']);
        $empty = ShippingPricing::forSubtotal(0, $settings);
        $this->assertSame(0, $empty['shipping_cents']);
        $this->assertSame(0, $empty['total_cents']);
        $this->assertFalse($empty['free_shipping']);

        $settings->free_shipping_threshold = '0.00';
        $instant = ShippingPricing::forSubtotal(5000, $settings);
        $this->assertSame(0, $instant['shipping_cents']);
        $this->assertTrue($instant['free_shipping']);
    }
}
