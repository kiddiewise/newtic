<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminOrderDeliveryEditTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::create([
            'order_number'=>'PK-EDIT-'.Str::upper(Str::random(8)),'checkout_token'=>(string)Str::uuid(),
            'customer_name'=>'Eski Alıcı','customer_email'=>'buyer@example.com','phone'=>'05321234567',
            'address_label'=>'Ev','city'=>'İzmir','district'=>'Konak','street_address'=>'Eski Mahalle 10 Sokak No 3',
            'invoice_type'=>'individual','payment_method'=>'paytr','status'=>'preparing',
            'subtotal_cents'=>10000,'shipping_cents'=>1000,'total_cents'=>11000,'currency'=>'TRY','is_test'=>true,'placed_at'=>now(),
        ]);
    }

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['is_admin'=>true])->save();
        return $user;
    }

    private function payload(): array
    {
        return ['customer_name'=>'Yeni Alıcı','phone'=>'+90 555 123 45 67','city'=>'Ankara','district'=>'Çankaya','street_address'=>'Yeni Mahalle 20 Sokak No 8'];
    }

    public function test_admin_can_correct_delivery_without_changing_payment_email_or_account(): void
    {
        $order=$this->order();
        $admin=$this->admin();
        $this->actingAs($admin)->get(route('admin.orders.show',$order))->assertOk()
            ->assertSee('data-delivery-edit-open',false)->assertSee('data-delivery-editor',false)
            ->assertDontSee('Ev · Eski Mahalle');
        $this->patch(route('admin.orders.delivery',$order),$this->payload()+[
            'customer_email'=>'other@example.com','total_cents'=>1,'status'=>'completed','address_label'=>'İş',
        ])->assertRedirect(route('admin.orders.show',$order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('Yeni Alıcı',$order->customer_name);
        $this->assertSame('05551234567',$order->phone);
        $this->assertSame('Ankara',$order->city);
        $this->assertSame('Çankaya',$order->district);
        $this->assertSame('Yeni Mahalle 20 Sokak No 8',$order->street_address);
        $this->assertSame('buyer@example.com',$order->customer_email);
        $this->assertSame('preparing',$order->status);
        $this->assertSame(11000,$order->total_cents);
        $this->assertSame('Ev',$order->address_label);
        $this->assertDatabaseHas('order_delivery_edits',['order_id'=>$order->id,'user_id'=>$admin->id]);
        $this->patch(route('admin.orders.delivery',$order),$this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('order_delivery_edits',1);
    }

    public function test_only_admin_and_pre_shipment_valid_delivery_updates_are_allowed(): void
    {
        $order=$this->order();
        $url=route('admin.orders.delivery',$order);
        $this->patchJson($url,$this->payload())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->patchJson($url,$this->payload())->assertForbidden();
        $this->actingAs($this->admin());
        $this->patchJson($url,array_replace($this->payload(),['district'=>'Konak','phone'=>'123']))->assertUnprocessable()->assertJsonValidationErrors(['phone','district']);
        $order->update(['status'=>'shipped']);
        $this->patchJson($url,$this->payload())->assertUnprocessable()->assertJsonValidationErrors('delivery');
        $this->get(route('admin.orders.show',$order))->assertOk()->assertDontSee('data-delivery-edit-open',false);
        $this->assertSame('Eski Alıcı',$order->fresh()->customer_name);
        $this->assertDatabaseCount('order_delivery_edits',0);
    }
}
