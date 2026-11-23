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
            'REMOTE_ADDR' => '8.8.8.8',
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
            ->assertSee('PAYTR Ödeme Ekranına Geç');
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
            ->assertSee('Bağlantı doğrulamasını aç');

        $this->post(route('admin.paytr-settings.test-mode'), ['enabled' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($merchant->fresh()->is_enabled);

        $this->get(route('admin.paytr-settings.edit'))->assertOk()
            ->assertSee('Bağlantı doğrulamasını kapat');
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
        // Once ordered, items are reserved and shown as a pending order,
        // not double-counted in the customer's editable shopping cart.
        $this->assertSame([], session('shopping_cart', []));
        $this->assertSame(1, $order->items()->count());

        Http::fake(['https://www.paytr.com/odeme/api/get-token' =>
            Http::response(['status' => 'success', 'token' => str_repeat('a', 48)])
        ]);

        $this->get(route('checkout.paytr.frame', $order))->assertOk()
            ->assertSee('PAYTR BAĞLANTI KONTROLÜ')
            ->assertSee('www.paytr.com/odeme/guvenli/'.str_repeat('a', 48), false)
            ->assertSee('179,70')
            ->assertDontSee('sandbox_key_for_tests')
            ->assertDontSee('sandbox_salt_for_tests');
        Http::assertSentCount(1);

        // Checkout no longer forces the fixed-price old PAYTR order. Empty
        // fresh cart returns to the cart, and a payment resume is explicit.
        $this->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'));
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
            ->assertSee('PAYTR ödeme bildirimi doğrulandı')
            ->assertDontSee('Banka hesabını seç');
        $this->assertSame([], session('shopping_cart', []));

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('PAYTR kart ödeme geçmişi')
            ->assertSee('Ödeme bildirimi doğrulandı')
            ->assertSee('PAYTR Kart');
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');
        $this->assertSame('preparing', $order->fresh()->status);
    }


    public function test_late_paytr_success_never_deletes_a_new_cart_with_identical_items(): void
    {
        $this->merchant();
        $product = $this->product();
        $order = $this->place($product);

        // Customer independently buys the SAME SKU and same two units again.
        $this->post(route('cart.add'), ['items' => [$product->id => 2]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, session('shopping_cart')[$product->id]);

        // A signed provider callback for the first purchase arrives later.
        $attempt = $order->paytrAttempts()->firstOrFail();
        $this->post(route('checkout.paytr.callback'), $this->signedPayload($attempt))
            ->assertOk()->assertSeeText('OK');
        $this->get(route('checkout.success', $order))->assertOk();
        $this->get(route('cart.index'))->assertOk()
            ->assertSee($product->name)
            ->assertDontSee('Ödemesi bekleyen bir siparişin var');

        $this->assertSame(2, session('shopping_cart')[$product->id]);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
    }

    public function test_pending_test_payment_page_can_be_revisited_more_than_old_eight_per_minute_limit(): void
    {
        $this->merchant();
        $order = $this->place($this->product());
        Http::fake(['https://www.paytr.com/odeme/api/get-token' =>
            Http::response(['status' => 'success', 'token' => str_repeat('r', 48)])
        ]);

        // Browser back/forward and repeated checkout redirects must not
        // produce a full-page 429 after the old IP-wide eight-request cap.
        for ($index = 0; $index < 12; $index++) {
            $this->get(route('checkout.paytr.frame', $order))
                ->assertOk()
                ->assertSee('PAYTR BAĞLANTI KONTROLÜ');
        }

        Http::assertSentCount(12);
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('paytr_payment_attempts', 1);
    }

    public function test_payment_frame_retains_named_scoped_rate_limits(): void
    {
        // Laravel disables actual throttle enforcement in the PHPUnit test
        // HTTP kernel. Check the configured limiter directly instead of
        // asserting that its 25th test HTTP request produces 429.
        $route = \Illuminate\Support\Facades\Route::getRoutes()
            ->getByName('checkout.paytr.frame');
        $this->assertContains('throttle:paytr-frame', $route->gatherMiddleware());

        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('paytr-frame');
        $this->assertNotNull($limiter);
        $request = \Illuminate\Http\Request::create(
            route('checkout.paytr.frame', 42), 'GET'
        );
        $request->setLaravelSession(app('session')->driver());
        $limits = $limiter($request);
        $this->assertCount(2, $limits);
        $this->assertSame(24, $limits[0]->maxAttempts);
        $this->assertSame(120, $limits[1]->maxAttempts);
        $this->assertNotSame($limits[0]->key, $limits[1]->key);
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

        // The cart must stay accessible while the fixed-price payment is pending.
        // The original order has reserved its inventory and lives in the banner.
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Ödemesi bekleyen bir siparişin var')
            ->assertSee($order->order_number)
            ->assertSee(route('checkout.paytr.frame', $order));
        $this->assertSame([], session('shopping_cart', []));

        $failed = $this->signedPayload($attempt, 'failed', '0');
        $failed['failed_reason_code'] = '6';
        $this->post(route('checkout.paytr.callback'), $failed)->assertOk()->assertSeeText('OK');
        $this->post(route('checkout.paytr.callback'), $failed)->assertOk()->assertSeeText('OK');

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->get(route('cart.index'))->assertOk()
            ->assertSee($product->name);
        $this->assertSame(2, session('shopping_cart')[$product->id]);
        $this->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Kart ödemeniz başarısız oldu.')
            ->assertSee('Sepete dön ve yeniden dene');
        $this->get(route('checkout.index'))->assertOk();
        $this->assertNotEmpty(session('checkout_token'));
    }

    public function test_pending_payment_does_not_lock_cart_and_new_products_go_to_a_separate_order(): void
    {
        $this->merchant();
        $product = $this->product();
        $oldOrder = $this->place($product);

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Ödemesi bekleyen bir siparişin var')
            ->assertSee($oldOrder->order_number)
            ->assertSee('Sepetin henüz boş')
            ->assertSee(route('checkout.paytr.frame', $oldOrder))
            ->assertSee('Yeni ürünler eklersen bunlar ayrı bir sipariş oluşturur');
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));

        $new = Product::create([
            'primary_category_id' => $product->primary_category_id,
            'sku' => 'SURF-NEW', 'slug' => 'surf-new',
            'name' => 'Yeni Surf Ürünü', 'price' => '59.90',
            'stock_quantity' => 10, 'is_active' => true,
        ]);

        $this->post(route('cart.add'), ['items' => [$new->id => 2]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$new->id => 2], session('shopping_cart'));
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(10, $new->fresh()->stock_quantity);

        $this->get(route('cart.index'))->assertOk()
            ->assertSee($oldOrder->order_number)
            ->assertSee($new->name)
            ->assertSee('2 Ürün');
        $this->get(route('checkout.index'))->assertOk()
            ->assertSee($new->name);
        // Placing a second order reserves only the new items.
        \App\Models\BankAccount::create([
            'bank_name' => 'Deneme Bankası',
            'account_holder' => 'Deneme Mağaza',
            'iban' => 'TR330006100519786457841326',
            'is_active' => true,
        ]);
        $token = session('checkout_token');
        $this->post(route('checkout.place'), [
            'checkout_token' => $token,
            'payment_method' => 'bank_transfer',
            'address_label' => 'Ev',
            'full_name' => 'Test Müşterisi',
            'email' => 'buyer@example.test',
            'phone' => '05321234567',
            'city' => 'İzmir', 'district' => 'Buca',
            'street_address' => 'Çamlık Mahallesi 12 Sokak No 6',
            'invoice_type' => 'individual',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 2);
        $this->assertSame('pending_payment', $oldOrder->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(8, $new->fresh()->stock_quantity);
        $newOrder = Order::query()->whereKeyNot($oldOrder->id)->firstOrFail();
        $this->assertSame('bank_transfer', $newOrder->payment_method);
        $this->assertSame(2, $newOrder->items()->firstOrFail()->quantity);
        $this->get(route('cart.index'))->assertOk()
            ->assertSee($oldOrder->order_number)
            ->assertSee(route('checkout.paytr.frame', $oldOrder));
    }

    public function test_signed_payment_success_does_not_erase_new_basket_items(): void
    {
        $this->merchant();
        $product = $this->product();
        $oldOrder = $this->place($product);
        $other = Product::create([
            'primary_category_id' => $product->primary_category_id,
            'sku' => 'OTHER-CART', 'slug' => 'other-cart',
            'name' => 'Ek Sepet Ürünü', 'price' => '75.00',
            'stock_quantity' => 8, 'is_active' => true,
        ]);
        $this->post(route('cart.add'), ['items' => [$other->id => 1]])
            ->assertSessionHasNoErrors();
        $attempt = $oldOrder->paytrAttempts()->firstOrFail();
        $this->post(route('checkout.paytr.callback'), $this->signedPayload($attempt))
            ->assertOk()->assertSeeText('OK');

        $this->get(route('checkout.success', $oldOrder))->assertOk();
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Ek Sepet Ürünü')
            ->assertDontSee('Ödemesi bekleyen bir siparişin var');
        $this->assertSame([$other->id => 1], session('shopping_cart'));
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_legacy_pending_cart_is_detached_before_any_new_cart_addition(): void
    {
        $this->merchant();
        $product = $this->product();
        $order = $this->place($product);

        // Existing browsers may still have the pre-fix original cart snapshot.
        $this->withSession([
            'shopping_cart' => session('paytr_pending_cart'),
            // A real pre-migration browser session predates this marker.
            'paytr_detached_order_id' => null,
        ]);
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Ödemesi bekleyen bir siparişin var')
            ->assertSee($order->order_number);
        $this->assertSame([], session('shopping_cart', []));

        $other = Product::create([
            'primary_category_id' => $product->primary_category_id,
            'sku' => 'LEGACY-NEW', 'slug' => 'legacy-new',
            'name' => 'Yeni Ek Ürün', 'price' => '55.00',
            'stock_quantity' => 4, 'is_active' => true,
        ]);
        $this->post(route('cart.add'), ['items' => [$other->id => 1]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$other->id => 1], session('shopping_cart'));
        $this->assertSame(8, $product->fresh()->stock_quantity);
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
    public function test_reverse_proxy_customer_ip_is_forwarded_to_paytr_in_test_mode(): void
    {
        $this->merchant();
        $order = $this->place($this->product());

        // Shared hosting presents a private reverse proxy as REMOTE_ADDR.
        // Trusted proxy forwarding supplies the customer's public address.
        $this->withServerVariables([
            'HTTP_HOST' => 'powderblue-dinosaur-538060.hostingersite.com',
            'SERVER_NAME' => 'powderblue-dinosaur-538060.hostingersite.com',
            'HTTPS' => 'on',
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '192.168.1.2, 8.8.4.4, 10.0.0.10',
        ]);

        Http::fake(['https://www.paytr.com/odeme/api/get-token' =>
            Http::response(['status' => 'success', 'token' => str_repeat('d', 48)])
        ]);

        $this->get(route('checkout.paytr.frame', $order))->assertOk()
            ->assertSee('www.paytr.com/odeme/guvenli/'.str_repeat('d', 48), false);

        Http::assertSent(fn ($request) =>
            ($request->data()['user_ip'] ?? null) === '8.8.4.4'
            && ($request->data()['debug_on'] ?? null) === '1'
            && ($request->data()['test_mode'] ?? null) === '1'
        );
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_paytr_provider_failure_shows_only_safe_error_code(): void
    {
        $this->merchant();
        $order = $this->place($this->product());
        Http::fake(['https://www.paytr.com/odeme/api/get-token' =>
            Http::response([
                'status' => 'failed',
                'reason' => 'Zorunlu alan degeri gecersiz: user_ip TEST_SENSITIVE_NOT_FOR_CUSTOMER',
            ])
        ]);

        $this->get(route('checkout.paytr.frame', $order))->assertOk()
            ->assertSee('customer_ip')
            ->assertSee('Ödeme formu açılamadı')
            ->assertDontSee('TEST_SENSITIVE_NOT_FOR_CUSTOMER');
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_invalid_private_ip_cannot_call_paytr_gateway(): void
    {
        $this->merchant();
        $order = $this->place($this->product());
        $this->withServerVariables([
            'HTTP_HOST' => 'powderblue-dinosaur-538060.hostingersite.com',
            'SERVER_NAME' => 'powderblue-dinosaur-538060.hostingersite.com',
            'HTTPS' => 'on',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        Http::fake();

        $this->get(route('checkout.paytr.frame', $order))->assertOk()
            ->assertSee('müşteri bağlantısı doğrulanamadı');
        Http::assertNothingSent();
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_realistic_paytr_opaque_token_is_accepted_and_uri_encoded(): void
    {
        $this->merchant();
        $order = $this->place($this->product());

        // The provider's opaque value is not guaranteed to be alphanumeric:
        // '+' '/' and '=' are legitimate token characters.
        $opaque = str_repeat('A', 40).'+/=_-';
        Http::fake(['https://www.paytr.com/odeme/api/get-token' =>
            Http::response(['status' => 'success', 'token' => $opaque], 200)
        ]);

        $this->get(route('checkout.paytr.frame', $order))->assertOk()
            ->assertSee('www.paytr.com/odeme/guvenli/'.rawurlencode($opaque), false)
            ->assertDontSee('Ödeme formu açılamadı')
            ->assertDontSee('invalid_token_response');
        Http::assertSentCount(1);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_control_characters_in_provider_token_are_rejected_without_leaking(): void
    {
        $this->merchant();
        $order = $this->place($this->product());
        $invalidOpaque = str_repeat('A', 40)."\n";
        Http::fake(['https://www.paytr.com/odeme/api/get-token' =>
            Http::response(['status' => 'success', 'token' => $invalidOpaque], 200)
        ]);

        $this->get(route('checkout.paytr.frame', $order))->assertOk()
            ->assertSee('(invalid_token_response)')
            ->assertDontSee('www.paytr.com/odeme/guvenli/')
            ->assertDontSee($invalidOpaque);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

}
