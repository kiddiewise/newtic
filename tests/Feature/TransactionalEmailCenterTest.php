<?php

namespace Tests\Feature;

use App\Models\EmailDelivery;
use App\Models\EmailSetting;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Models\ShippingProvider;
use App\Models\ShippingShipment;
use App\Models\ShippingWarehouse;
use App\Models\User;
use App\Services\EmailComposer;
use App\Services\EmailTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TransactionalEmailCenterTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): EmailSetting
    {
        $settings = EmailSetting::current();
        $settings->update([
            'enabled' => true, 'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587, 'smtp_scheme' => 'smtp',
            'smtp_username' => 'notifications@example.com',
            'smtp_password' => 'private-credential',
            'from_address' => 'notifications@example.com',
            'from_name' => 'NEWTIC',
            'test_recipient' => 'sandbox@example.com',
            'admin_emails' => 'operator@example.com',
            'verified_at' => now(), 'events' => array_keys(EmailSetting::EVENTS),
            'review_delay_days' => 3,
        ]);
        return $settings;
    }

    private function makeOrder(?User $buyer = null, bool $test = true): Order
    {
        return Order::create([
            'order_number' => 'PK-'.strtoupper(Str::random(18)),
            'checkout_token' => (string) Str::uuid(),
            'user_id' => $buyer?->id,
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => $buyer?->email ?? 'customer@example.com',
            'phone' => '05551111111', 'city'=>'İzmir',
            'district'=>'Gaziemir', 'street_address'=>'Test adresi',
            'payment_method'=>'bank_transfer', 'status'=>'pending_payment',
            'subtotal_cents'=>50000, 'shipping_cents'=>0,
            'total_cents'=>50000, 'currency'=>'TRY',
            'is_test'=>$test, 'placed_at'=>now(),
        ]);
    }

    private function addShipment(Order $order): ShippingShipment
    {
        $provider = ShippingProvider::create(['provider'=>'kargoentegrator','is_enabled'=>true]);
        $carrier = ShippingCarrier::create([
            'shipping_provider_id'=>$provider->id,'remote_id'=>4,
            'name'=>'Dummy Kargo','is_dummy'=>true,
        ]);
        $warehouse = ShippingWarehouse::create([
            'shipping_provider_id'=>$provider->id,'remote_id'=>7,'name'=>'Ana Depo',
        ]);
        return ShippingShipment::create([
            'order_id'=>$order->id,'shipping_provider_id'=>$provider->id,
            'shipping_carrier_id'=>$carrier->id,'shipping_warehouse_id'=>$warehouse->id,
            'remote_id'=>123, 'state'=>'submitted', 'remote_status'=>'shipped',
            'desi'=>1.00,
        ]);
    }

    public function test_disabled_by_default_and_private_settings_are_never_in_admin_markup(): void
    {
        $this->assertFalse(EmailSetting::current()->active());
        $this->makeOrder();
        $this->assertDatabaseCount('email_deliveries', 0);
        $this->get(route('admin.email-center.index'))->assertRedirect(route('admin.login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.email-center.index'))->assertForbidden();
        $user->forceFill(['is_admin'=>true])->save();
        $settings = $this->enable();
        $this->assertNotSame('private-credential',$settings->getRawOriginal('smtp_password'));
        $this->actingAs($user)->get(route('admin.email-center.index'))->assertOk()
            ->assertDontSee('private-credential')->assertSee('E-posta ve bildirim merkezi');
    }

    public function test_new_orders_and_status_transitions_are_deduplicated_in_the_outbox(): void
    {
        $this->enable();
        $order = $this->makeOrder();
        $this->assertSame(1, EmailDelivery::where('event','admin_new_order')->count());
        $this->assertSame(1, EmailDelivery::where('event','customer_order_received')->count());
        $order->update(['status'=>'preparing']);
        $this->assertSame(1, EmailDelivery::where('event','customer_payment_verified')->count());
        $order->update(['status'=>'shipped']);
        $this->assertSame(1, EmailDelivery::where('event','customer_order_shipped')->count());
        $order->update(['status'=>'out_for_delivery']);
        $this->assertSame(1, EmailDelivery::where('event','customer_out_for_delivery')->count());
        $order->update(['status'=>'shipped']);
        $this->assertSame(1, EmailDelivery::where('event','customer_order_shipped')->count());
        $this->assertDatabaseCount('email_deliveries', 5);
    }

    public function test_review_invitation_requires_signed_in_delivered_order_and_respects_three_day_delay(): void
    {
        $this->enable();
        $buyer = User::factory()->create();
        $order = $this->makeOrder($buyer);
        $shipment = $this->addShipment($order);
        $order->update(['status'=>'completed']); // Shipping provider has not confirmed delivery.
        $this->assertSame(0, EmailDelivery::where('event','customer_review_invite')->count());
        $shipment->update(['remote_status'=>'delivered']);
        $order = $order->fresh();
        $order->update(['status'=>'shipped']);
        $order->update(['status'=>'completed']);
        $invites = EmailDelivery::where('event','customer_review_invite')->get();
        $this->assertCount(1, $invites);
        $this->assertTrue($invites->first()->available_at->between(
            now()->addDays(3)->subMinutes(1), now()->addDays(3)->addMinutes(1)
        ));
        // A shipment return before the delayed send cancels the invitation.
        $shipment->update(['remote_status'=>'on_return']);
        $this->assertNull(app(EmailComposer::class)->build($invites->first()->load('order')));
        $this->assertSame(1, EmailDelivery::where('event','admin_shipping_exception')->count());
    }

    public function test_worker_uses_safe_preview_address_and_never_sends_stale_messages(): void
    {
        $this->enable();
        $order = $this->makeOrder();
        $transport = $this->mock(EmailTransport::class);
        $transport->shouldReceive('send')->twice()
            ->withArgs(fn($settings,$recipient,$payload) =>
                $settings instanceof EmailSetting
                && in_array($recipient, ['operator@example.com','customer@example.com'], true)
                && filled($payload['subject']))->andReturnNull();
        $this->artisan('email:process',['--limit'=>10])->assertExitCode(0);
        $this->assertSame(2, EmailDelivery::where('status','sent')->count());
        $this->artisan('email:process',['--limit'=>10])->assertExitCode(0);
        $this->assertSame(2, EmailDelivery::where('status','sent')->count());
        $this->assertSame('sandbox@example.com',EmailSetting::current()->safeRecipient('customer@example.com'));
    }
}
