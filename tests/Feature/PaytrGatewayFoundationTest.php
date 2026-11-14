<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Services\PaytrTokenService;
use App\Support\PaytrSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PaytrGatewayFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        \Illuminate\Support\Facades\URL::forceRootUrl('https://powderblue-dinosaur-538060.hostingersite.com');
        \Illuminate\Support\Facades\URL::forceScheme('https');
        // Service unit tests call the token provider directly, without a
        // simulated HTTP request. Bind the same canonical request host.
        app('request')->headers->set('host', 'powderblue-dinosaur-538060.hostingersite.com');
        app('request')->server->set('HTTP_HOST', 'powderblue-dinosaur-538060.hostingersite.com');
        app('request')->server->set('HTTPS', 'on');
        $this->withServerVariables([
            'HTTP_HOST' => 'powderblue-dinosaur-538060.hostingersite.com',
            'SERVER_NAME' => 'powderblue-dinosaur-538060.hostingersite.com',
            'HTTPS' => 'on',
        ]);
    }

    private function credentials(): PaytrSetting
    {
        $setting = PaytrSetting::query()->create([
            'id' => 1,
            'merchant_id' => '123456',
            'merchant_key' => 'test_secret_key_demo_only',
            'merchant_salt' => 'test_secret_salt_demo_only',
        ]);
        $setting->forceFill(['is_enabled' => false, 'test_mode' => true])->save();

        return $setting;
    }

    private function order(): Order
    {
        $order = Order::create([
            'order_number' => 'PK-TEST-'.strtoupper(Str::random(9)),
            'checkout_token' => (string) Str::uuid(),
            'user_id' => null,
            'customer_name' => 'Gül Yılmaz',
            'customer_email' => 'gul@example.test',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Mahalle 42 Sokak No 3',
            'invoice_type' => 'individual',
            'payment_method' => 'paytr',
            'status' => 'pending_payment',
            'subtotal_cents' => 9980,
            'shipping_cents' => 7990,
            'total_cents' => 17970,
            'currency' => 'TRY',
            'is_test' => true,
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => null,
            'sku' => 'SURF75',
            'product_name' => 'Surf Kurşun 75 gr',
            'option_label' => 'İğne kalınlığı',
            'option_value' => '0,25',
            'quantity' => 2,
            'unit_price_cents' => 4990,
            'line_total_cents' => 9980,
        ]);

        return $order;
    }

    private function signed(string $merchantOid, string $status = 'success', string $amount = '17970'): array
    {
        return [
            'merchant_oid' => $merchantOid,
            'status' => $status,
            'total_amount' => $amount,
            'hash' => PaytrSignature::notificationHash(
                $merchantOid, $status, $amount,
                'test_secret_key_demo_only', 'test_secret_salt_demo_only'
            ),
            'test_mode' => '1',
            'payment_type' => 'card',
            'currency' => 'TL',
        ];
    }

    public function test_signature_matches_paytr_documented_field_order(): void
    {
        $fields = [
            'merchant_id' => '123456',
            'user_ip' => '203.0.113.15',
            'merchant_oid' => 'NT123456789AB',
            'email' => 'example@example.test',
            'payment_amount' => '17970',
            'user_basket' => base64_encode('[["Surf Kurşun","49.90",2]]'),
            'no_installment' => '1',
            'max_installment' => '0',
            'currency' => 'TL',
            'test_mode' => '1',
        ];
        $concat = implode('', array_values($fields));
        $expected = base64_encode(hash_hmac(
            'sha256', $concat.'SALT', 'KEY', true
        ));

        $this->assertSame($expected, PaytrSignature::iframeToken($fields, 'KEY', 'SALT'));

        $callbackExpected = base64_encode(hash_hmac(
            'sha256', 'NT123456789AB'.'SALT'.'success'.'17970', 'KEY', true
        ));
        $this->assertSame($callbackExpected, PaytrSignature::notificationHash(
            'NT123456789AB', 'success', '17970', 'KEY', 'SALT'
        ));
    }

    public function test_disabled_by_default_payment_service_cannot_contact_paytr(): void
    {
        $setting = $this->credentials();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);
        Http::fake();

        $this->assertFalse($setting->is_enabled);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{1,64}$/', $attempt->merchant_oid);
        $this->assertNotSame($order->order_number, $attempt->merchant_oid);
        $this->assertSame(17970, $attempt->amount_cents);

        try {
            app(PaytrTokenService::class)->requestIframeToken(
                $order, $attempt, '203.0.113.15',
                'https://powderblue-dinosaur-538060.hostingersite.com/odeme/basarili',
                'https://powderblue-dinosaur-538060.hostingersite.com/odeme/basarisiz'
            );
            $this->fail('Disabled PAYTR integration should not initiate a request.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('henüz kullanıma açık değil', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_enabled_in_memory_test_mode_sends_correct_signed_post_and_variation_basket(): void
    {
        config()->set('newtic.paytr_card_payments_enabled', true);
        config()->set('newtic.paytr_webhooks_enabled', true);
        $setting = $this->credentials();
        $setting->forceFill(['is_enabled' => true])->save();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);
        $token = str_repeat('a', 50);
        Http::fake([
            'https://www.paytr.com/odeme/api/get-token' => Http::response([
                'status' => 'success', 'token' => $token,
            ], 200),
        ]);

        $actual = app(PaytrTokenService::class)->requestIframeToken(
            $order, $attempt, '203.0.113.15',
            'https://powderblue-dinosaur-538060.hostingersite.com/odeme/basarili',
            'https://powderblue-dinosaur-538060.hostingersite.com/odeme/basarisiz',
        );
        $this->assertSame($token, $actual);
        Http::assertSentCount(1);

        Http::assertSent(function ($request) use ($attempt): bool {
            $body = $request->data();
            $basket = json_decode(base64_decode($body['user_basket']), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://www.paytr.com/odeme/api/get-token'
                && ($body['payment_amount'] ?? null) === '17970'
                && ($body['merchant_oid'] ?? null) === $attempt->merchant_oid
                && ($body['currency'] ?? null) === 'TL'
                && ($body['no_installment'] ?? null) === '1'
                && ($body['test_mode'] ?? null) === '1'
                && ($body['paytr_token'] ?? null) === PaytrSignature::iframeToken(
                    $body, 'test_secret_key_demo_only', 'test_secret_salt_demo_only'
                )
                && $basket[0][0] === 'Surf Kurşun 75 gr (İğne kalınlığı: 0,25)'
                && $basket[0][1] === '49.90'
                && $basket[0][2] === 2
                && $basket[1] === ['Kargo', '79.90', 1]
                && ! array_key_exists('merchant_key', $body)
                && ! array_key_exists('merchant_salt', $body);
        });
    }

    public function test_paytr_rejected_or_malformed_token_response_is_never_treated_as_success(): void
    {
        config()->set('newtic.paytr_card_payments_enabled', true);
        config()->set('newtic.paytr_webhooks_enabled', true);
        $setting = $this->credentials();
        $setting->forceFill(['is_enabled' => true])->save();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);
        Http::fake(['https://www.paytr.com/odeme/api/get-token' => Http::response([
            'status' => 'failed', 'reason' => 'Sensitive provider failure',
        ])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test tokenı alınamadı');
        app(PaytrTokenService::class)->requestIframeToken(
            $order, $attempt, '203.0.113.15',
            'https://powderblue-dinosaur-538060.hostingersite.com/odeme/basarili',
            'https://powderblue-dinosaur-538060.hostingersite.com/odeme/basarisiz',
        );
    }

    public function test_callback_without_activation_never_processes_a_payment(): void
    {
        $setting = $this->credentials();
        $setting->forceFill(['is_enabled' => true])->save();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);

        $this->post(route('checkout.paytr.callback'), $this->signed($attempt->merchant_oid))
            ->assertStatus(503);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_verified_success_callback_can_be_processed_only_once(): void
    {
        config()->set('newtic.paytr_webhooks_enabled', true);
        $setting = $this->credentials();
        $setting->forceFill(['is_enabled' => true])->save();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);
        $signed = $this->signed($attempt->merchant_oid);

        $this->post(route('checkout.paytr.callback'), $signed)
            ->assertOk()->assertSeeText('OK');
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertSame('success', $attempt->fresh()->status);
        $this->assertSame(17970, $attempt->fresh()->paid_total_cents);

        $firstProcessedAt = $attempt->fresh()->processed_at?->toISOString();
        $this->post(route('checkout.paytr.callback'), $signed)
            ->assertOk()->assertSeeText('OK');
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertSame($firstProcessedAt, $attempt->fresh()->processed_at?->toISOString());
        $this->assertDatabaseCount('paytr_payment_attempts', 1);
    }

    public function test_callback_forgery_wrong_amount_and_unknown_oid_do_not_change_order(): void
    {
        config()->set('newtic.paytr_webhooks_enabled', true);
        $setting = $this->credentials();
        $setting->forceFill(['is_enabled' => true])->save();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);

        $forged = $this->signed($attempt->merchant_oid);
        $forged['hash'] = str_repeat('A', 44);
        $this->post(route('checkout.paytr.callback'), $forged)->assertStatus(403);
        $this->post(route('checkout.paytr.callback'), $this->signed($attempt->merchant_oid, 'success', '17971'))
            ->assertStatus(422);

        $this->post(route('checkout.paytr.callback'), $this->signed('NTUNKNOWN1234'))
            ->assertStatus(404);

        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_failed_callback_marks_only_attempt_as_failed_without_claiming_paid(): void
    {
        config()->set('newtic.paytr_webhooks_enabled', true);
        $setting = $this->credentials();
        $setting->forceFill(['is_enabled' => true])->save();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);
        $failure = $this->signed($attempt->merchant_oid, 'failed');
        $failure['failed_reason_code'] = '6';
        $failure['failed_reason_msg'] = 'Customer cancelled the test transaction';

        $this->post(route('checkout.paytr.callback'), $failure)->assertOk()->assertSeeText('OK');
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($attempt->fresh()->paid_total_cents);
        $this->post(route('checkout.paytr.callback'), $failure)->assertOk()->assertSeeText('OK');
    }

    public function test_invalid_test_flag_and_payment_type_are_rejected(): void
    {
        config()->set('newtic.paytr_webhooks_enabled', true);
        $setting = $this->credentials();
        $setting->forceFill(['is_enabled' => true])->save();
        $order = $this->order();
        $attempt = PaytrPaymentAttempt::createForOrder($order);

        $payload = $this->signed($attempt->merchant_oid);
        $payload['test_mode'] = '0';
        $this->post(route('checkout.paytr.callback'), $payload)->assertStatus(422);

        $payload = $this->signed($attempt->merchant_oid);
        $payload['payment_type'] = 'eft';
        $this->post(route('checkout.paytr.callback'), $payload)->assertStatus(422);
        $this->assertSame('pending', $attempt->fresh()->status);
    }
}
