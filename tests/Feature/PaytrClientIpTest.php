<?php

namespace Tests\Feature;

use App\Exceptions\PaytrTokenException;
use App\Support\PaytrClientIp;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

final class PaytrClientIpTest extends TestCase
{
    public function test_public_peer_address_cannot_be_overridden_by_forged_forward_headers(): void
    {
        $request = Request::create('https://example.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
            'HTTP_X_FORWARDED_FOR' => '1.1.1.1',
            'HTTP_X_REAL_IP' => '1.1.1.1',
        ]);
        $this->assertSame('8.8.8.8', PaytrClientIp::resolve($request));
    }

    public function test_internal_proxy_prefers_nearest_public_hop(): void
    {
        $request = Request::create('https://example.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.8',
            'HTTP_X_FORWARDED_FOR' => '192.168.5.1, 8.8.4.4, 10.0.0.5',
        ]);
        $this->assertSame('8.8.4.4', PaytrClientIp::resolve($request));
    }

    public function test_x_real_ip_is_accepted_only_for_private_proxy_peers(): void
    {
        $request = Request::create('https://example.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_REAL_IP' => '1.1.1.1',
        ]);
        $this->assertSame('1.1.1.1', PaytrClientIp::resolve($request));
    }

    public function test_private_ip_without_trusted_proxy_headers_does_not_pass(): void
    {
        $request = Request::create('https://example.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip, 192.168.0.6',
        ]);
        $this->expectException(InvalidArgumentException::class);
        PaytrClientIp::resolve($request);
    }

    public function test_provider_failure_is_classified_without_leaking_raw_reason(): void
    {
        $reason = 'Zorunlu alan degeri gecersiz: user_ip PRIVATE_DIAGNOSTIC_PAYLOAD';
        $exception = PaytrTokenException::fromProviderReason($reason);
        $this->assertSame('customer_ip', $exception->safeCode);
        $this->assertStringNotContainsString('PRIVATE_DIAGNOSTIC_PAYLOAD', $exception->getMessage());
        $this->assertSame('signature', PaytrTokenException::fromProviderReason('Gecersiz paytr_token')->safeCode);
        $this->assertSame('merchant_id', PaytrTokenException::fromProviderReason('Zorunlu alan degeri gecersiz: merchant_id')->safeCode);
        $this->assertSame('gateway_rejected', PaytrTokenException::fromProviderReason('Other unknown response')->safeCode);
    }
}
