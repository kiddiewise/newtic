<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\ShippingCarrier;
use App\Models\ShippingProvider;
use App\Models\ShippingShipment;
use App\Models\ShippingWarehouse;
use App\Models\User;
use App\Services\ShippingReconciliationService;
use App\Services\ShippingShipmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ShippingOperationsTest extends TestCase
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
            'HTTP_HOST'=>'powderblue-dinosaur-538060.hostingersite.com',
            'SERVER_NAME'=>'powderblue-dinosaur-538060.hostingersite.com',
            'HTTPS'=>'on',
        ]);
    }

    private function admin(): User
    {
        $admin=User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin);
        return $admin;
    }

    private function shipment(string $status='in_courier', bool $dummy=true, string $state='submitted'): ShippingShipment
    {
        $provider=ShippingProvider::firstOrCreate(['provider'=>'kargoentegrator'], [
            'api_key'=>'dummy_test_bearer_token_used_only_in_http_fake',
            'is_enabled'=>true,
            'last_checked_at'=>now(),
        ]);
        $carrier=ShippingCarrier::firstOrCreate([
            'shipping_provider_id'=>$provider->id,'remote_id'=>$dummy ? 10 : 12,
        ],[
            'name'=>$dummy ? 'Dummy Kargo' : 'DHL eCommerce',
            'carrier_code'=>$dummy ? 'dummy' : 'dhl',
            'is_dummy'=>$dummy,
        ]);
        $warehouse=ShippingWarehouse::firstOrCreate([
            'shipping_provider_id'=>$provider->id,'remote_id'=>7,
        ], ['name'=>'İzmir Merkez Depo']);
        $order=Order::create([
            'order_number'=>'PK-TEST-'.strtoupper(Str::random(10)),
            'checkout_token'=>(string)Str::uuid(),
            'customer_name'=>'Ayşe Demir',
            'customer_email'=>'customer@example.test',
            'phone'=>'05321234567',
            'address_label'=>'Ev',
            'city'=>'İzmir',
            'district'=>'Buca',
            'street_address'=>'Atatürk Mahallesi 12 Sokak No 5',
            'payment_method'=>'bank_transfer',
            'status'=>'preparing',
            'subtotal_cents'=>9990,
            'shipping_cents'=>4990,
            'total_cents'=>14980,
            'currency'=>'TRY',
            'is_test'=>true,
            'placed_at'=>now(),
        ]);
        PaymentNotification::create([
            'order_id'=>$order->id,
            'bank_name_snapshot'=>'Test Banka',
            'iban_snapshot'=>'TR330006100519786457841326',
            'status'=>'verified',
        ]);
        return ShippingShipment::create([
            'order_id'=>$order->id,
            'shipping_provider_id'=>$provider->id,
            'shipping_carrier_id'=>$carrier->id,
            'shipping_warehouse_id'=>$warehouse->id,
            'remote_id'=>99000+$order->id,
            'state'=>$state,
            'remote_status'=>$status,
            'desi'=>'1.00',
        ]);
    }

    public function test_only_admin_can_access_operations_and_acknowledge_errors(): void
    {
        $shipment=$this->shipment('failed');
        $this->get(route('admin.shipping-operations.index'))->assertRedirect();
        $this->post(route('admin.shipping-operations.review', $shipment), [
            'reason'=>'Kargo şirketiyle görüşülecek.',
        ])->assertRedirect();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.shipping-operations.index'))->assertForbidden();
        $this->post(route('admin.shipping-operations.sync'))->assertForbidden();
        $this->post(route('admin.shipping-operations.review', $shipment), [
            'reason'=>'Kargo şirketiyle görüşülecek.',
        ])->assertForbidden();
        $this->assertDatabaseCount('shipping_exception_reviews',0);
    }

    public function test_exception_reviews_are_audited_without_refunding_or_canceling_order(): void
    {
        $admin=$this->admin();
        $shipment=$this->shipment('returned');
        $order=$shipment->order;
        $this->get(route('admin.shipping-operations.index'))->assertOk()
            ->assertSee('Kargo operasyonları')
            ->assertSee($order->order_number)
            ->assertSee('İade Edildi')
            ->assertSee('İnceleme ve aksiyon notu');

        $this->post(route('admin.shipping-operations.review',$shipment), [
            'reason'=>'kısa',
        ])->assertSessionHasErrors('reason');
        $this->assertNull($shipment->fresh()->exception_acknowledged_at);

        $reason='Kargo sağlayıcısı aranacak; teslim edilemeyen paket depoya alınacak.';
        $this->post(route('admin.shipping-operations.review',$shipment), [
            'reason'=>$reason,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $current=$shipment->fresh();
        $this->assertNotNull($current->exception_acknowledged_at);
        $this->assertSame('returned',$current->exception_acknowledged_status);
        $this->assertSame($admin->id,$current->exception_acknowledged_by);
        $this->assertDatabaseHas('shipping_exception_reviews',[
            'shipping_shipment_id'=>$shipment->id,
            'user_id'=>$admin->id,
            'remote_status'=>'returned',
            'reason'=>$reason,
        ]);
        $this->assertSame('preparing',$order->fresh()->status);
        $this->assertSame('verified',$order->paymentNotifications()->firstOrFail()->status);

        $this->get(route('admin.shipping-operations.index'))->assertDontSee($order->order_number);
        $this->get(route('admin.shipping-operations.index',['filter'=>'reviewed']))->assertSee($order->order_number);
        $this->get(route('admin.orders.show',$order))->assertSee($reason);
        $this->post(route('admin.shipping-operations.review',$shipment), ['reason'=>$reason])
            ->assertSessionHasErrors('shipping');
        $this->assertDatabaseCount('shipping_exception_reviews',1);
    }

    public function test_new_provider_status_reopens_a_previously_reviewed_exception(): void
    {
        $this->admin();
        $shipment=$this->shipment('on_return');
        $this->post(route('admin.shipping-operations.review',$shipment), [
            'reason'=>'Depoya ulaşması takip ediliyor; kargo firmasıyla görüşülecek.',
        ])->assertSessionHasNoErrors();

        $manager=app(ShippingShipmentManager::class);
        $manager->syncDetails($shipment,[
            'id'=>$shipment->remote_id,
            'status'=>'returned',
            'tracking_number'=>'1234567890',
        ]);

        $this->assertNull($shipment->fresh()->exception_acknowledged_at);
        $this->assertNull($shipment->fresh()->exception_note);
        $this->assertSame('returned',$shipment->fresh()->remote_status);
        $this->assertSame(1,$shipment->exceptionReviews()->count());
        $this->get(route('admin.shipping-operations.index'))
            ->assertSee($shipment->order->order_number);
    }

    public function test_automated_read_only_sync_catches_callback_silent_cancellations_and_respects_cooldown(): void
    {
        $this->admin();
        $shipment=$this->shipment('shipped');
        $id=$shipment->remote_id;
        Http::fake([
            'https://app.kargoentegrator.com/api/shipments/'.$id => Http::response([
                'data'=>['id'=>$id,'status'=>'canceled','tracking_number'=>'1234567890'],
            ]),
        ]);
        $this->assertSame(0, Artisan::call('shipping:sync',['--limit'=>20]));
        $this->assertSame('canceled',$shipment->fresh()->remote_status);
        $this->assertNotNull($shipment->fresh()->last_synced_at);
        $this->assertSame(0,$shipment->fresh()->sync_failure_count);
        $this->assertSame('preparing',$shipment->order->fresh()->status);
        $this->assertDatabaseHas('shipping_status_events',[
            'shipping_shipment_id'=>$shipment->id,
            'status'=>'canceled','source'=>'api_sync',
        ]);
        Http::assertSentCount(1);
        $this->assertSame(0, Artisan::call('shipping:sync',['--limit'=>20]));
        Http::assertSentCount(1); // 10-min cooldown; no extra API calls

        $this->post(route('admin.shipping-operations.sync'))->assertSessionHasNoErrors();
        Http::assertSentCount(2); // manual bounded refresh can bypass cooldown
        Http::assertSent(fn($r)=>$r->method()==='GET'
            && str_contains($r->url(),'/api/shipments/'));
        $this->get(route('admin.shipping-operations.index'))->assertSee('Kargo Gönderisi İptal Edildi');
    }

    public function test_auto_sync_queries_dummy_only_and_never_creates_real_dhl_shipments(): void
    {
        $this->admin();
        $dummy=$this->shipment('shipped',true);
        $real=$this->shipment('shipped',false);
        Http::fake([
            'https://app.kargoentegrator.com/api/shipments/'.$dummy->remote_id =>
                Http::response(['data'=>['id'=>$dummy->remote_id,'status'=>'delivered']]),
        ]);
        $this->assertSame(0,Artisan::call('shipping:sync'));
        $this->assertSame('completed',$dummy->order->fresh()->status);
        $this->assertSame('shipped',$real->fresh()->remote_status);
        $this->assertNull($real->fresh()->last_synced_at);
        Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r->method()==='GET');
    }

    public function test_failed_reads_do_not_corrupt_shipping_or_payment_and_are_reported(): void
    {
        $this->admin();
        $shipment=$this->shipment('at_delivery_center');
        Http::fake([
            'https://app.kargoentegrator.com/api/shipments/*' => Http::response(['error'=>'no'],503),
        ]);
        $service=app(ShippingReconciliationService::class);
        $this->assertSame('failed',$service->syncOne($shipment));
        $this->assertSame('failed',$service->syncOne($shipment));
        $fresh=$shipment->fresh();
        $this->assertSame('at_delivery_center',$fresh->remote_status);
        $this->assertSame(2,$fresh->sync_failure_count);
        $this->assertNull($fresh->last_synced_at);
        $this->assertNotNull($fresh->last_sync_failed_at);
        $this->assertSame('preparing',$shipment->order->fresh()->status);
        $this->get(route('admin.shipping-operations.index'))->assertSee('2 başarısız deneme');
        Http::assertSentCount(2);
    }

    public function test_command_refuses_live_domain_and_invalid_limits(): void
    {
        $shipment=$this->shipment('failed');
        Http::fake();
        $this->assertSame(2,Artisan::call('shipping:sync',['--limit'=>'0']));
        config()->set('app.url','https://polariskursun.com');
        $this->assertSame(1,Artisan::call('shipping:sync',['--limit'=>20]));
        $this->assertNull($shipment->fresh()->last_synced_at);
        Http::assertNothingSent();
    }

    public function test_order_search_and_filters_preserve_turkish_labels_and_customer_tracking(): void
    {
        $this->admin();
        $shipment=$this->shipment('at_delivery_center');
        $order=$shipment->order;
        $this->get(route('admin.shipping-operations.index',['filter'=>'all','q'=>$order->order_number]))
            ->assertOk()->assertSee($order->order_number)->assertSee('Dağıtım Merkezinde');
        $this->get(route('admin.shipping-operations.index',['filter'=>'delivered']))->assertDontSee($order->order_number);
        $this->get(route('admin.shipping-operations.index',['filter'=>'xxx']))->assertStatus(422);
        $this->get(route('admin.orders.show',$order))->assertSee('Dağıtım Merkezinde')
            ->assertSee('Kargo operasyon kaydını aç');
        $this->withSession(['confirmed_order_ids'=>[$order->id]])
            ->get(route('checkout.success',$order))->assertOk()->assertSee('Dağıtım Merkezinde');
    }
}
