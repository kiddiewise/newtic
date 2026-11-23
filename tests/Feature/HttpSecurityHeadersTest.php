<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HttpSecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storefront_uses_non_disruptive_browser_security_headers(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'geolocation=(), microphone=()');
    }

    public function test_login_and_checkout_responses_cannot_be_cached_or_leak_referrer(): void
    {
        foreach (['/admin/login', '/giris', '/sepet', '/odeme'] as $path) {
            $response = $this->get($path);
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeader('X-Frame-Options', 'DENY');

            $this->assertStringContainsString(
                'no-store',
                (string) $response->headers->get('Cache-Control'),
                'Sensitive response may be cached: '.$path
            );
            $this->assertStringContainsString(
                'private',
                (string) $response->headers->get('Cache-Control'),
                'Sensitive response lacks private cache policy: '.$path
            );
        }
    }

    public function test_session_and_payment_callback_do_not_require_frontend_frame_embedding(): void
    {
        // This change must not interfere with PAYTR's POST callback.
        // Invalid/unsigned requests are still rejected by the existing HMAC guard.
        $this->post('/odeme/paytr/bildirim', [
            'merchant_oid' => 'UNSIGNED',
            'status' => 'success',
        ])->assertStatus(503)->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
