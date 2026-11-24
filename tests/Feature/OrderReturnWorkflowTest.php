<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderReturnRequest;
use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Models\User;
use App\Services\OrderReturnService;
use App\Services\PaytrRefundClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OrderReturnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'completed', string $method = 'paytr'): Order
    {
        $order = Order::create([
            'order_number' => 'NT-'.Str::random(12), 'checkout_token' => Str::uuid()->toString(),
            'customer_name'=>'Müşteri Test','customer_email'=>'customer@example.test','phone'=>'05321234567',
            'city'=>'İzmir','district'=>'Buca','street_address'=>'Örnek Sokak 12','invoice_type'=>'individual',
            'payment_method'=>$method,'status'=>$status,'subtotal_cents'=>10000,'shipping_cents'=>2000,
            'total_cents'=>12000,'currency'=>'TRY','is_test'=>true,'placed_at'=>now(),
        ]);
        $order->items()->create(['sku'=>'X','product_name'=>'Ürün','quantity'=>2,'unit_price_cents'=>5000,'line_total_cents'=>10000]);
        return $order;
    }

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['is_admin'=>true])->save();
        return $user;
    }

    private function payload(Order $order, int $qty=1): array
    {
        return ['kind'=>'return','reason'=>'withdrawal','items'=>[$order->items()->firstOrFail()->id=>$qty]];
    }

    public function test_guest_access_is_scoped_and_duplicate_and_excess_quantity_are_rejected(): void
    {
        $order=$this->order();
        $url=route('checkout.returns.store',$order);
        $this->post($url,$this->payload($order))->assertNotFound();
        $this->withSession(['confirmed_order_ids'=>[$order->id]]);
        $this->post($url,$this->payload($order,3))->assertSessionHasErrors('return');
        $this->post($url,$this->payload($order))->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url,$this->payload($order))->assertSessionHasErrors('return');
        $this->assertDatabaseCount('order_return_requests',1);
        $this->get(route('checkout.success',$order))->assertOk()->assertSee('Talep alındı');
        $this->get(route('admin.returns.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin())->get(route('admin.returns.index'))->assertOk()->assertSee($order->order_number);
        $this->get(route('admin.orders.show',$order))->assertOk()->assertSee('Talebi onayla');
    }

    public function test_paytr_refund_preflight_and_single_post_then_reconciliation(): void
    {
        $order=$this->order('pending_payment');
        $attempt=PaytrPaymentAttempt::createForOrder($order);
        $attempt->update(['status'=>'success','paid_total_cents'=>13000,'processed_at'=>now()]);
        $order->update(['status'=>'completed']);
        PaytrSetting::create(['merchant_id'=>'1234','merchant_key'=>'key','merchant_salt'=>'salt']);
        $admin=$this->admin();
        $service=app(OrderReturnService::class);
        $case=$service->create($order,$this->payload($order),$admin->id);
        $service->review($case,['action'=>'approve'],$admin->id);
        $status=['status'=>'success','currency'=>'TL','test_mode'=>'1','payment_amount'=>'120.00','payment_total'=>'130.00','returns'=>[]];
        Http::fake([
            'https://www.paytr.com/odeme/durum-sorgu'=>Http::sequence()->push($status)->push(array_replace($status,['returns'=>[['return_amount'=>'50.00','reference_no'=>$case->reference,'date_completed'=>'2026-11-24 10:00:00']]])),
            'https://www.paytr.com/odeme/iade'=>Http::response(['status'=>'success','merchant_oid'=>$attempt->merchant_oid,'reference_no'=>$case->reference,'return_amount'=>'50.00','is_test'=>'1']),
        ]);
        $this->actingAs($admin);
        $url=route('admin.returns.update',$case);
        $this->post($url,['action'=>'refund','confirmed'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('submitted',$case->fresh()->refund_status);
        $this->post($url,['action'=>'refund','confirmed'=>1])->assertSessionHasErrors('return');
        Http::assertSentCount(2);
        Http::assertSent(fn($r)=>$r->url()==='https://www.paytr.com/odeme/iade' && $r['return_amount']==='50.00' && $r['reference_no']===$case->reference
            && $r['paytr_token']===base64_encode(hash_hmac('sha256','1234'.$attempt->merchant_oid.'50.00'.'salt','key',true)));
        $this->post($url,['action'=>'reconcile'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('verified',$case->fresh()->refund_status);
        $this->post($url,['action'=>'finish','restock'=>1])->assertSessionHasErrors('return');
        $this->post($url,['action'=>'received'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url,['action'=>'finish','restock'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed',$case->fresh()->status);
        $this->assertSame(12000,$order->fresh()->total_cents);
        $this->post($url,['action'=>'finish','restock'=>1])->assertSessionHasErrors('return');
    }

    public function test_pending_paytr_never_refunds_and_unpaid_bank_cancellation_is_explicit(): void
    {
        $order=$this->order('pending_payment');
        PaytrPaymentAttempt::createForOrder($order);
        $admin=$this->admin();$service=app(OrderReturnService::class);
        $case=$service->create($order,$this->payload($order,2),$admin->id);
        $service->review($case,['action'=>'approve'],$admin->id);
        Http::fake();
        $this->actingAs($admin)->post(route('admin.returns.update',$case),['action'=>'refund','confirmed'=>1])->assertSessionHasErrors('return');
        Http::assertNothingSent();
        $bank=$this->order('pending_payment','bank_transfer');
        $case=$service->create($bank,$this->payload($bank,2),$admin->id);
        $service->review($case,['action'=>'approve'],$admin->id);
        $url=route('admin.returns.update',$case);
        $this->post($url,['action'=>'complete_unpaid'])->assertSessionHasErrors('return');
        $this->post($url,['action'=>'complete_unpaid','confirmed'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled',$bank->fresh()->status);
        $this->assertSame('completed',$case->fresh()->status);
    }

    public function test_live_refund_is_disabled_by_default_and_does_not_contact_gateway(): void
    {
        $order=$this->order('pending_payment');
        $order->update(['is_test'=>false]);
        $attempt=PaytrPaymentAttempt::createForOrder($order);
        $attempt->update(['status'=>'success','paid_total_cents'=>12000,'processed_at'=>now()]);
        $order->update(['status'=>'completed']);
        config()->set('newtic.checkout_test_mode',false);
        PaytrSetting::create(['merchant_id'=>'1234','merchant_key'=>'key','merchant_salt'=>'salt']);
        $admin=$this->admin();
        $service=app(OrderReturnService::class);
        $case=$service->create($order,$this->payload($order),$admin->id);
        $service->review($case,['action'=>'approve'],$admin->id);
        Http::fake();
        $this->actingAs($admin)->post(route('admin.returns.update',$case),['action'=>'refund','confirmed'=>1])->assertSessionHasErrors('return');
        Http::assertNothingSent();
        $this->assertSame('none',$case->fresh()->refund_status);
    }
}
