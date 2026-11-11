<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Models\Product;
use App\Models\User;
use App\Support\PaytrSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaytrTestCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        \Illuminate\Support\Facades\URL::forceRootUrl('https://powderblue-dinosaur-538060.hostingersite.com');
        \Illuminate\Support\Facades\URL::forceScheme('https');
        config()->set('newtic.checkout_test_mode', true);
        config()->set('newtic.paytr_card_payments_enabled', true);
        config()->set('newtic.paytr_webhooks_enabled', true);
        $this->withServerVariables([
            'HTTP_HOST' => 'powderblue-dinosaur-538060.hostingersite.com',
            'SERVER_NAME' => 'powderblue-dinosaur-538060.hostingersite.com',
            'HTTPS' => 'on',
        ]);
    }

    private function product(): Product
    {
        $cat = Category::create([
            'name' => 'Surf Kurşun',
            'slug' => 'surf-kursun',
            'is_active' => true,
        ]);
        return Product::create([
            'primary_category_id' => $cat->id,
            'sku' => 'SURF75',
            'slug' => 'surf-75',
            'name' => 'Polaris Surf Kurşun 75 gr',
            'price' => '49.90',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    private function merchant(bool $enabled = true): PaytrSetting
    {
        $setting = PaytrSetting::query()->create([
            'id' => 1,
            'merchant_id' => '123456',
            'merchant_key' => 'sandbox_key_for_tests',
            'merchant_salt' => 'sandbox_salt_for_tests',
        ]);
        $setting->forceFill(['is_enabled' => $enabled, 'test_mode' => true])->save();
        return $setting;
    }

    private function addCart(Product $product): string
    {
        $this->post(route('cart.add'), ['items' => [$product->id => 2]])->assertRedirect();
        $this->get(route('checkout.index'))->assertOk()
            ->assertSee('Kredi / Banka Kartı')
            ->assertSee('Güvenli Test Ödemesine Geç');
        return (string) session('checkout_token');
    }

    private function place(Product $product): Order
    {
        $token = $this->addCart($product);
        $this->post(route('checkout.place'), [
            'checkout_token' => $token,
            'payment_method' => 'paytr',
            'address_label' => 'Ev',
            'full_name' => 'Test Müşterisi',
            'email' => 'buyer@example.test',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Çamlık Mahallesi 12 Sokak No 6',
            'invoice_type' => 'individual',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('paytr', $order->payment_method);
        $this->assertSame('pending_payment', $order->status);
        $this->assertTrue($order->is_test);
        $this->assertDatabaseCount('paytr_payment_attempts', 1);
        $this->assertSame(8, $product->fresh()->stock_quantity);

        return $order;
    }

    private function signedPayload(PaytrPaymentAttempt $attempt, string $status = 'success', ?string $amount = null): array
    {
        $amount ??= (string) $attempt->amount_cents;
        return [
            'merchant_oid' => $attempt->merchant_oid,
            'status' => $status,
            'total_amount' => $amount,
            'hash' => PaytrSignature::notificationHash(
                $attempt->merchant_oid, $status, $amount,
                'sandbox_key_for_tests', 'sandbox_salt_for_tests'
            ),
            'test_mode' => '1',
            'payment_type' => 'card',
            'currency' => 'TL',
        ];
    }

    public function test_admin_explicitly_enables_and_disables_test_payments(): void
    {
        $merchant = $this->merchant(false);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $this->get(route('admin.paytr-settings.edit'))->assertOk()
            ->assertSee('Test ödemesini etkinleştir');

        $this->post(route('admin.paytr-settings.test-mode'), ['enabled' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($merchant->fresh()->is_enabled);

        $this->get(route('admin.paytr-settings.edit'))->assertOk()
            ->assertSee('Test ödemesini kapat');
        $this->post(route('admin.paytr-settings.test-mode'), ['enabled' => 0])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($merchant->fresh()->is_enabled);
    }

    public function test_guest_checkout_uses_paytr_token_iframe_and_verified_success(): void
    {
        $this->merchant();
        $product = $this->product();
        $order = $this->place($product);

        $this->assertSame(17970, $order->total_cents);
        $this->assertSame(2, session('shopping_cart')[$product->id]);
        $this->assertSame(1, $order->items()->count());

        Http::fake(['https://www.paytr.com/odeme/api/get-token' =>
            Http::response(['status' => 'success', 'token' => str_repeat('a', 48)])
        ]);

        $this->get(route('checkout.paytr.frame', $order))->assertOk()
            ->assertSee('PAYTR TEST MODU')
            ->assertSee('www.paytr.com/odeme/guvenli/'.str_repeat('a', 48), false)
            ->assertSee('179,70')
            ->assertDontSee('sandbox_key_for_tests')
            ->assertDontSee('sandbox_salt_for_tests');
        Http::assertSentCount(1);

        // Idempotent: returning to checkout does not generate another order.
        $this->get(route('checkout.index'))
            ->assertRedirect(route('checkout.paytr.frame', $order));
        $this->assertDatabaseCount('orders', 1);
        $attempt = $order->paytrAttempts()->firstOrFail();

        $this->post(route('checkout.paytr.callback'), $this->signedPayload($attempt))
            ->assertOk()->assertSeeText('OK');
        $this->post(route('checkout.paytr.callback'), $this->signedPayload($attempt))
            ->assertOk()->assertSeeText('OK');

        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertSame('success', $attempt->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Siparişiniz hazırlanıyor')
            ->assertSee('PAYTR test ödemeniz doğrulandı')
            ->assertDontSee('Banka hesabını seç');
        $this->assertSame([], session('shopping_cart', []));

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('PAYTR kart ödeme geçmişi')
            ->assertSee('Test ödemesi doğrulandı')
            ->assertSee('PAYTR Kart (Test)');
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');
        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_admin_cannot_cancel_paytr_order_while_callback_is_pending(): void
    {
        $this->merchant();
        $product = $this->product();
        $order = $this->place($product);
        $attempt = $order->paytrAttempts()->firstOrFail();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);

        // A subsequent signed success must still be able to settle the order.
        $this->post(route('checkout.paytr.callback'), $this->signedPayload($attempt))
            ->assertOk()->assertSeeText('OK');
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_failed_payment_returns_stock_and_preserves_cart_for_another_try(): void
    {
        $this->merchant();
        $product = $this->product();
        $order = $this->place($product);
        $attempt = $order->paytrAttempts()->firstOrFail();

        $failed = $this->signedPayload($attempt, 'failed', '0');
        $failed['failed_reason_code'] = '6';
        $this->post(route('checkout.paytr.callback'), $failed)->assertOk()->assertSeeText('OK');
        $this->post(route('checkout.paytr.callback'), $failed)->assertOk()->assertSeeText('OK');

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame(2, session('shopping_cart')[$product->id]);
        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Test ödemeniz başarısız oldu.')
            ->assertSee('Sepete dön ve yeniden dene');
        $this->get(route('checkout.index'))->assertOk();
        $this->assertNotEmpty(session('checkout_token'));
    }

    public function test_unauthorized_guest_cannot_open_another_customers_payment_page(): void
    {
        $this->merchant();
        $product = $this->product();
        $order = $this->place($product);
        $this->withSession(['confirmed_order_ids' => [], 'checkout_last_order' => null]);
        $this->get(route('checkout.paytr.frame', $order))->assertNotFound();
    }

    public function test_card_payment_is_unavailable_without_merchant_opt_in_or_on_other_domain(): void
    {
        $this->merchant(false);
        $product = $this->product();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $this->get(route('checkout.index'))->assertOk()->assertDontSee('name="payment_method" value="paytr"', false);
        $token = session('checkout_token');
        $this->post(route('checkout.place'), [
            'checkout_token' => $token, 'payment_method' => 'paytr',
        ])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);

        // A copied staging database cannot enable checkout on another host.
        PaytrSetting::firstOrFail()->forceFill(['is_enabled' => true])->save();
        $this->withServerVariables(['HTTP_HOST' => 'polariskursun.com', 'SERVER_NAME' => 'polariskursun.com', 'HTTPS' => 'on']);
        $this->get('https://polariskursun.com/odeme')->assertOk()
            ->assertDontSee('name="payment_method" value="paytr"', false);
    }
}
