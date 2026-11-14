<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingCarrier;
use App\Models\ShippingProvider;
use App\Models\ShippingShipment;
use App\Models\ShippingWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingIntegratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'https://powderblue-dinosaur-538060.hostingersite.com');
        config()->set('newtic.checkout_test_mode', true);
        config()->set('newtic.shipping_dummy_enabled', true);
        URL::forceRootUrl('https://powderblue-dinosaur-538060.hostingersite.com');
        URL::forceScheme('https');
        $this->withServerVariables([
            'HTTP_HOST' => 'powderblue-dinosaur-538060.hostingersite.com',
            'SERVER_NAME' => 'powderblue-dinosaur-538060.hostingersite.com',
            'HTTPS' => 'on',
        ]);
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
    }

    private function provider(bool $enabled = true): ShippingProvider
    {
        $provider = ShippingProvider::create([
            'provider' => 'kargoentegrator',
            'api_key' => 'test_key_used_only_in_fake_requests',
            'is_enabled' => $enabled,
            'last_checked_at' => now(),
        ]);
        ShippingCarrier::create([
            'shipping_provider_id' => $provider->id,
            'remote_id' => 168, 'name' => 'Dummy Kargo',
            'carrier_code' => 'dummy','is_dummy' => true,
        ]);
        ShippingCarrier::create([
            'shipping_provider_id' => $provider->id,
            'remote_id' => 169, 'name' => 'DHL eCommerce',
            'carrier_code' => 'dhl', 'is_dummy' => false,
        ]);
        ShippingWarehouse::create([
            'shipping_provider_id' => $provider->id,
            'remote_id' => 127,'name' => 'İzmir Depo',
        ]);
        return $provider;
    }

    private function fakeCarriers(): void
    {
        Http::fake([
            'https://app.kargoentegrator.com/api/integration/cargos' =>
                Http::response(['data' => [
                    ['id' => 168, 'name' => 'Dummy Kargo', 'cargo_company' => ['id' => 'dummy','title' => 'Dummy Kargo']],
                    ['id' => 169, 'name' => 'DHL eCommerce', 'cargo_company' => ['id' => 'dhl','title' => 'DHL']],
                ]]),
            'https://app.kargoentegrator.com/api/settings/warehouses' =>
                Http::response(['data' => [['id' => 127, 'name' => 'İzmir Depo']]]),
        ]);
    }

    private function order(string $status = 'preparing', bool $isTest = true): Order
    {
        $order = Order::create([
            'order_number' => 'PK-CARGO-'.strtoupper(Str::random(8)),
            'checkout_token' => (string)Str::uuid(),
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'buyer@example.test',
            'phone' => '05321234567', 'address_label' => 'Ev',
            'city' => 'İzmir', 'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi, 12 Sokak, No: 5',
            'payment_method' => 'bank_transfer','status' => $status,
            'subtotal_cents' => 14990, 'shipping_cents' => 7990, 'total_cents' => 22980,
            'currency' => 'TRY', 'is_test' => $isTest, 'placed_at' => now(),
        ]);
        $order->items()->create([ 'sku' => 'SURF-75',
            'product_name' => 'Polaris Surf Kurşun 75 gr',
            'quantity' => 2, 'unit_price_cents' => 7495, 'line_total_cents' => 14990,
        ]);
        return $order;
    }

    private function createData(?ShippingProvider $provider = null): array
    {
        $provider ??= ShippingProvider::where('provider', 'kargoentegrator')->firstOrFail();
        return [
            'carrier_id' => $provider->carriers()->where('is_dummy', true)->firstOrFail()->id,
            'warehouse_id' => $provider->warehouses()->firstOrFail()->id,
            'desi' => '1.25',
            'kg' => '0.35',
            'payment_type' => 'cash_money',
        ];
    }

    private function fakeCreateSuccess(): void
    {
        Http::fake([
            'https://app.kargoentegrator.com/api/integration/cargos' =>
                Http::response(['data' => [
                    ['id' => 168, 'cargo_company' => ['id' => 'dummy'], 'name' => 'Dummy Kargo'],
                ]]),
            'https://app.kargoentegrator.com/api/shipments' =>
                Http::response(['data' => ['id' => 7444,'status'=>'non_processed','barcode'=>'326334021558']], 201),
        ]);
    }

    private function createShipment(Order $order, ShippingProvider $provider): ShippingShipment
    {
        $this->fakeCreateSuccess();
        $this->post(route('admin.shipping-shipments.create', $order), $this->createData($provider))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasNoErrors();
        return ShippingShipment::firstOrFail();
    }

    public function test_guest_and_non_admin_cannot_manage_shipping_credentials_or_labels(): void
    {
        $this->get(route('admin.shipping-integrations.index'))->assertRedirect();
        $this->post(route('admin.shipping-integrations.sync'))->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(route('admin.shipping-integrations.index'))->assertForbidden();
        $this->patch(route('admin.shipping-integrations.save'))->assertForbidden();
    }

    public function test_save_key_is_encrypted_not_reflected_and_does_not_enable_shipments(): void
    {
        $this->admin();
        $key = 'VERY_SECRET_API_KEY_123456';
        $this->patch(route('admin.shipping-integrations.save'), [
            'provider' => 'kargoentegrator', 'api_key' => $key,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $p = ShippingProvider::firstOrFail();
        $this->assertSame($key, $p->api_key);
        $this->assertNotSame($key, $p->getRawOriginal('api_key'));
        $this->assertArrayNotHasKey('api_key', $p->toArray());
        $this->assertFalse($p->is_enabled);
        $this->get(route('admin.shipping-integrations.index'))->assertOk()
            ->assertSee('Kargo Entegrasyonu')
            ->assertSee('Kargonomi (yakında)')
            ->assertDontSee($key);
        $this->patch(route('admin.shipping-integrations.save'), [
            'provider' => 'kargoentegrator', 'api_key' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($key, $p->fresh()->api_key);

        $this->patch(route('admin.shipping-integrations.save'), [
            'provider' => 'kargoentegrator', 'api_key' => 'bad',
        ])->assertSessionHasErrors('api_key');
        $this->assertSame($key, $p->fresh()->api_key);
        $this->assertSame([], session()->getOldInput());
    }

    public function test_read_only_sync_caches_dhl_dummy_and_warehouse_but_never_creates_remote_shipment(): void
    {
        $this->admin();
        $provider = $this->provider(false);
        $this->fakeCarriers();
        $this->post(route('admin.shipping-integrations.sync'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $provider->carriers()->count());
        $this->assertTrue($provider->carriers()->where('remote_id', 168)->firstOrFail()->is_dummy);
        $this->assertFalse($provider->carriers()->where('remote_id', 169)->firstOrFail()->is_dummy);
        $this->assertSame('İzmir Depo', $provider->warehouses()->firstOrFail()->name);
        Http::assertSentCount(2);
        Http::assertSent(fn($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/integration/cargos')
            && $request->hasHeader('Authorization', 'Bearer '.$provider->api_key));
    }

    public function test_dummy_toggle_requires_verified_accounts_and_is_only_available_on_staging(): void
    {
        $this->admin();
        $provider = $this->provider(false);
        $this->post(route('admin.shipping-integrations.toggle'), ['enabled'=>1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($provider->fresh()->is_enabled);
        $this->post(route('admin.shipping-integrations.toggle'), ['enabled'=>0])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($provider->fresh()->is_enabled);
        config()->set('newtic.checkout_test_mode', false);
        $this->post(route('admin.shipping-integrations.toggle'), ['enabled'=>1])->assertForbidden();
    }

    public function test_pending_payment_and_real_carrier_cannot_create_a_shipment(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order('pending_payment');
        Http::fake();
        $this->post(route('admin.shipping-shipments.create', $order), $this->createData($provider))
            ->assertSessionHasErrors('shipping');
        $prepared = $this->order();
        $data = $this->createData($provider);
        $data['carrier_id'] = $provider->carriers()->where('is_dummy', false)->firstOrFail()->id;
        $this->post(route('admin.shipping-shipments.create', $prepared), $data)
            ->assertSessionHasErrors('shipping');
        $this->assertDatabaseCount('shipping_shipments', 0);
        Http::assertNothingSent();
    }

    public function test_only_remote_verified_dummy_receives_exact_order_snapshot_no_cod_and_single_label(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        $shipment = $this->createShipment($order, $provider);
        $this->assertSame('submitted', $shipment->state);
        $this->assertSame(7444, $shipment->remote_id);
        $this->assertSame('326334021558', $shipment->barcode);
        $this->assertSame('preparing', $order->fresh()->status);
        Http::assertSent(fn($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/shipments')
            && $request['cargo_integration_id'] === 168
            && $request['warehouse_id'] === 127
            && $request['customer']['city'] === 'İzmir'
            && $request['customer']['district'] === 'Buca'
            && $request['customer']['phone'] === '5321234567'
            && $request['customer']['surname'] === 'Yılmaz'
            && $request['platform_d_id'] === $order->order_number
            && $request['is_pay_at_door'] === false
            && $request['payor_type'] === 'sender'
            && $request['lines'][0]['sku'] === 'SURF-75'
            && $request['lines'][0]['quantity'] === 2);

        $this->post(route('admin.shipping-shipments.create', $order), $this->createData($provider))
            ->assertSessionHasErrors('shipping');
        $this->assertDatabaseCount('shipping_shipments', 1);
        Http::assertSentCount(2); // A provider GET and one shipment POST; duplicates make no external call.
    }

    public function test_unconfirmed_api_response_is_not_retried_automatically(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        Http::fake([
            'https://app.kargoentegrator.com/api/integration/cargos' =>
                Http::response(['data' => [['id'=>168,'cargo_company'=>['id'=>'dummy']]]]),
            'https://app.kargoentegrator.com/api/shipments' =>
                Http::response(['message' => 'temporary upstream error'], 502),
        ]);

        $this->post(route('admin.shipping-shipments.create', $order), $this->createData($provider))
            ->assertSessionHasErrors('shipping');
        $this->assertSame('uncertain', ShippingShipment::firstOrFail()->state);
        $this->post(route('admin.shipping-shipments.create', $order), $this->createData($provider))
            ->assertSessionHasErrors('shipping');
        $this->assertDatabaseCount('shipping_shipments', 1);
    }

    public function test_signed_callback_advances_only_paid_dummy_order_once_and_ignores_replay(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        $shipment = $this->createShipment($order, $provider);
        $url = route('shipping.callback');

        $message = function (string $status, string $time) use ($provider): array {
            return [
                'shipment_id' => 7444, 'team_id' => 361,
                'status' => $status, 'time' => $time,
                'hash' => hash_hmac('sha512', '7444'.$status.$time, $provider->api_key),
            ];
        };

        $invalid = $message('delivered', '2026-11-12 11:12:00');
        $invalid['hash'] = str_repeat('0', 128);
        $this->postJson($url, $invalid)->assertStatus(401);
        $this->assertSame('preparing', $order->fresh()->status);

        $shipped = $message('shipped', '2026-11-12 11:13:00');
        $this->postJson($url, $shipped)->assertOk()->assertSeeText('OK');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->postJson($url, $shipped)->assertOk();
        $this->assertSame('shipped', $order->fresh()->status);

        $delivery = $message('in_courier', '2026-11-12 12:13:00');
        $this->postJson($url, $delivery)->assertOk();
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $this->postJson($url, $shipped)->assertOk();
        $this->assertSame('out_for_delivery', $order->fresh()->status);

        $this->postJson($url, $message('delivered','2026-11-12 14:13:00'))->assertOk();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('delivered', $shipment->fresh()->remote_status);
    }

    public function test_read_only_tracking_sync_populates_tracking_number_and_pdf_is_checked(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        $this->createShipment($order, $provider);
        Http::fake([
            'https://app.kargoentegrator.com/api/shipments/7444' =>
                Http::response(['data' => [
                    'id'=>7444,'status'=>'delivered',
                    'tracking_number'=>'1234567890',
                    'tracking_link'=>'https://example.com/tracking/1234567890',
                    'barcode'=>'326334021558',
                ]]),
            'https://app.kargoentegrator.com/api/print-pdf*' =>
                Http::response('%PDF-1.4'."\n".'Dummy PDF content', 200, ['Content-Type'=>'application/pdf']),
        ]);
        $this->post(route('admin.shipping-shipments.refresh', $order))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('1234567890', $order->fresh()->tracking_number);
        $this->assertSame('Dummy Kargo', $order->fresh()->carrier_name);
        $label = $this->get(route('admin.shipping-shipments.label', $order))
            ->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringContainsString('no-store', (string)$label->headers->get('Cache-Control'));
        Http::assertSentCount(2);
    }

    public function test_live_domain_must_not_allow_dummy_or_real_carrier_submission(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        Http::fake();
        config()->set('app.url', 'https://polariskursun.com');
        $this->post(route('admin.shipping-shipments.create', $order), $this->createData($provider))
            ->assertForbidden();
        $this->postJson(route('shipping.callback'), [
            'shipment_id' => 123,
        ])->assertStatus(503);
        $this->assertDatabaseCount('shipping_shipments', 0);
        Http::assertNothingSent();
    }

    public function test_all_eleven_provider_states_have_readable_turkish_labels(): void
    {
        $expected = [
            'draft' => 'Taslak',
            'non_processed' => 'Gönderime Hazır',
            'shipped' => 'Kargoya Verildi',
            'on_transit' => 'Taşıma Sürecinde',
            'at_delivery_center' => 'Dağıtım Merkezinde',
            'in_courier' => 'Dağıtıma Çıktı',
            'delivered' => 'Teslim Edildi',
            'on_return' => 'İade Sürecinde',
            'returned' => 'İade Edildi',
            'canceled' => 'Kargo Gönderisi İptal Edildi',
            'failed' => 'Kargo İşleminde Sorun',
        ];
        $this->assertSame($expected, \App\Support\ShippingStatus::LABELS);
        $this->assertSame(array_keys($expected), \App\Services\ShippingShipmentManager::STATUSES);
    }

    public function test_shipping_callback_can_correct_an_earlier_distribution_stage_and_show_turkish_timeline_to_buyer(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        $shipment = $this->createShipment($order, $provider);
        $callback = function (string $status, string $time) use ($provider): void {
            $this->postJson(route('shipping.callback'), [
                'shipment_id' => 7444, 'status' => $status, 'time' => $time,
                'hash' => hash_hmac('sha512', '7444'.$status.$time, $provider->api_key),
            ])->assertOk();
        };

        $callback('at_delivery_center', '2026-11-13 09:10:00');
        $this->assertSame('shipped', $order->fresh()->status);
        $callback('in_courier', '2026-11-13 10:10:00');
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $callback('at_delivery_center', '2026-11-13 11:10:00');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame('at_delivery_center', $shipment->fresh()->remote_status);
        $this->assertSame(4, $shipment->events()->count());
        $this->assertSame(3, $order->statusChanges()->count());

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Dağıtım Merkezinde')
            ->assertSee('Kargo hareket geçmişi')
            ->assertDontSee('>at_delivery_center<', false);

        $this->withSession(['confirmed_order_ids' => [$order->id]])
            ->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Kargonuz dağıtım merkezinde!')
            ->assertSee('Dağıtım Merkezinde')
            ->assertSee('Kargo hareketleri')
            ->assertSee('Dağıtıma Çıktı')
            ->assertDontSee('>at_delivery_center<', false);
    }

    public function test_shipping_return_and_failure_are_customer_visible_but_do_not_refund_or_cancel_payment(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        $shipment = $this->createShipment($order, $provider);
        $post = function (string $status, string $at) use ($provider): void {
            $this->postJson(route('shipping.callback'), [
                'shipment_id'=>7444, 'status'=>$status, 'time'=>$at,
                'hash'=>hash_hmac('sha512', '7444'.$status.$at, $provider->api_key),
            ])->assertOk();
        };
        $post('shipped', '2026-11-13 12:00:00');
        $post('on_return', '2026-11-13 13:00:00');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->withSession(['confirmed_order_ids' => [$order->id]])
            ->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Kargonuz iade sürecinde')
            ->assertSee('İade Sürecinde')
            ->assertSee('Ödeme iadesi otomatik yapılmış anlamına gelmez');

        $post('returned', '2026-11-13 14:00:00');
        $this->withSession(['confirmed_order_ids' => [$order->id]])
            ->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Kargonuz göndericiye iade edildi')
            ->assertSee('İade Edildi');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame('returned', $shipment->fresh()->remote_status);

        // Provider never callbacks for failed / canceled: manual GET refresh must.
        Http::fake([
            'https://app.kargoentegrator.com/api/shipments/7444' =>
                Http::response(['data' => ['id' => 7444, 'status' => 'failed']]),
        ]);
        $this->post(route('admin.shipping-shipments.refresh', $order))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->withSession(['confirmed_order_ids' => [$order->id]])
            ->get(route('checkout.success', $order))->assertOk()
            ->assertSee('Kargo gönderinizle ilgili işlem gerekiyor')
            ->assertSee('Kargo İşleminde Sorun');
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_admin_can_correct_non_integrated_shipping_status_with_a_reason_and_audit_log(): void
    {
        $this->admin();
        $order = $this->order();
        \App\Models\PaymentNotification::create([
            'order_id'=>$order->id, 'bank_name_snapshot'=>'Test Banka',
            'iban_snapshot'=>'TR330006100519786457841326', 'status'=>'verified',
        ]);

        $this->patch(route('admin.orders.correct', $order), [
            'status'=>'completed', 'reason'=>'Operasyon kaydındaki eksik teslimat adımını düzelttim.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);

        $this->patch(route('admin.orders.correct', $order), [
            'status'=>'preparing', 'reason'=>'Teslimat bilgisi yanlış işaretlenmiş; kargo henüz çıkmadı.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_changes', [
            'order_id'=>$order->id,'from_status'=>'completed',
            'to_status'=>'preparing','source'=>'admin_correction',
        ]);
        $this->assertSame(2, $order->statusChanges()->count());
        $this->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('Sipariş durum değişiklikleri')
            ->assertSee('Durumu gerekçeyle düzelt');

        $this->patch(route('admin.orders.correct', $order), [
            'status'=>'shipped', 'reason'=>'short',
        ])->assertSessionHasErrors('reason');
        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_admin_may_revoke_false_bank_verification_only_before_shipping_with_audit(): void
    {
        $this->admin();
        $order = $this->order();
        $notice = \App\Models\PaymentNotification::create([
            'order_id'=>$order->id, 'bank_name_snapshot'=>'Test Banka',
            'iban_snapshot'=>'TR330006100519786457841326', 'status'=>'verified',
        ]);
        $this->post(route('admin.orders.revoke-bank-verification', $order), [
            'reason'=>'Banka hareketinde bu siparişe ait tahsilat bulunamadı.',
        ])->assertSessionHasErrors('bank_not_received');
        $this->assertSame('verified', $notice->fresh()->status);

        $this->post(route('admin.orders.revoke-bank-verification', $order), [
            'reason'=>'Banka hareketinde bu siparişe ait tahsilat bulunamadı.',
            'bank_not_received'=>'1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame('rejected', $notice->fresh()->status);
        $this->assertDatabaseHas('order_status_changes', [
            'order_id'=>$order->id,'from_status'=>'preparing',
            'to_status'=>'pending_payment',
            'source'=>'bank_verification_correction',
        ]);
        $this->post(route('admin.orders.revoke-bank-verification', $order), [
            'reason'=>'Banka hareketinde hâlâ ödeme bulunamadı.', 'bank_not_received'=>'1',
        ])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('order_status_changes', 1);
    }

    public function test_integrated_shipping_must_be_corrected_at_provider_not_with_admin_dropdown(): void
    {
        $this->admin();
        $provider = $this->provider();
        $order = $this->order();
        $this->createShipment($order, $provider);
        $this->patch(route('admin.orders.correct', $order), [
            'status'=>'completed', 'reason'=>'Dış sağlayıcı bilgisi olmadan aşama değiştirilmeye çalışıldı.',
        ])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status'=>'shipped'])
            ->assertSessionHasErrors('status');
        $this->assertSame('preparing', $order->fresh()->status);
    }


    public function test_account_and_admin_order_lists_show_specific_turkish_carrier_state(): void
    {
        $provider = $this->provider();
        $order = $this->order();
        $member = User::factory()->create();
        $order->update(['user_id'=>$member->id]);
        $shipment = ShippingShipment::create([
            'order_id'=>$order->id,
            'shipping_provider_id'=>$provider->id,
            'shipping_carrier_id'=>$provider->carriers()->where('is_dummy', true)->firstOrFail()->id,
            'shipping_warehouse_id'=>$provider->warehouses()->firstOrFail()->id,
            'remote_id'=>77323,
            'state'=>'submitted',
            'remote_status'=>'at_delivery_center',
            'desi'=>'1.00',
        ]);
        $this->actingAs($member)->get(route('account.index'))->assertOk()
            ->assertSee('Kargo: Dağıtım Merkezinde');
        $this->admin();
        $this->get(route('admin.orders.index'))->assertOk()
            ->assertSee('Kargo: Dağıtım Merkezinde');
    }

}
