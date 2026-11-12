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
}
