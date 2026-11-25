<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Str;
use App\Services\AdminWebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AdminPwaTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::create([
            'order_number'=>'PK-PWA-'.Str::upper(Str::random(8)), 'checkout_token'=>(string)Str::uuid(),
            'customer_name'=>'Test Müşteri','customer_email'=>'test@example.com','phone'=>'05321234567',
            'address_label'=>'Ev','city'=>'İzmir','district'=>'Konak','street_address'=>'Örnek Sokak 3',
            'invoice_type'=>'individual','payment_method'=>'bank_transfer','status'=>'pending_payment',
            'subtotal_cents'=>10000,'shipping_cents'=>0,'total_cents'=>10000,'currency'=>'TRY','is_test'=>true,'placed_at'=>now(),
        ]);
    }

    public function test_cron_pushes_new_unread_orders_once_and_cleans_expired_endpoint(): void
    {
        $admin=User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin);
        $this->order();
        $endpoint='https://fcm.googleapis.com/fcm/send/example-token';
        $this->postJson(route('admin.pwa.subscribe'),compact('endpoint'))->assertOk();
        Http::fake(['fcm.googleapis.com/*'=>Http::response('',201)]);
        $push=app(AdminWebPush::class);
        $this->assertSame(0,$push->deliverPending());
        $order=$this->order();
        $this->assertSame(1,$push->deliverPending());
        $this->assertSame(0,$push->deliverPending());
        $this->assertSame($order->id,(int)DB::table('admin_push_subscriptions')->value('last_order_id'));
        $read=$this->order();
        \App\Support\AdminOrderNotifications::markOrderSeen($admin,$read);
        $this->assertSame(0,$push->deliverPending());
        Http::fake(['fcm.googleapis.com/*'=>Http::response('',410)]);
        $this->order();
        $this->assertSame(0,$push->deliverPending());
        $this->assertDatabaseCount('admin_push_subscriptions',0);
    }

    public function test_pwa_is_admin_only_and_endpoint_allowlist_is_strict(): void
    {
        $this->get(route('admin.pwa.worker'))->assertOk()->assertHeader('Service-Worker-Allowed','/admin/');
        $this->getJson(route('admin.pwa.status'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('admin.pwa.status'))->assertForbidden();
        $admin=User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get(route('admin.pwa.index'))->assertOk()->assertSee('newtic-admin.webmanifest');
        $key=$this->getJson(route('admin.pwa.status'))->assertOk()->assertJsonPath('active',false)->json('public_key');
        $this->assertSame(87,strlen($key));
        foreach (['http://fcm.googleapis.com/a','https://127.0.0.1/a','https://fcm.googleapis.com.evil.test/a','https://fcm.googleapis.com:444/a','https://a@fcm.googleapis.com/a'] as $endpoint) {
            $this->postJson(route('admin.pwa.subscribe'),compact('endpoint'))->assertUnprocessable();
        }
        $endpoint='https://fcm.googleapis.com/fcm/send/example-token';
        $this->postJson(route('admin.pwa.subscribe'),compact('endpoint'))->assertOk()->assertJsonPath('active',true);
        $this->assertNotSame($endpoint,DB::table('admin_push_subscriptions')->value('endpoint'));
        Http::fake(['fcm.googleapis.com/*'=>Http::response('',201)]);
        $this->postJson(route('admin.pwa.test'))->assertOk()->assertJsonPath('accepted',true);
        Http::assertSent(fn($request)=>$request->url()===$endpoint && str_starts_with($request->header('Authorization')[0],'vapid t=') && $request->body()==='');
        $this->deleteJson(route('admin.pwa.unsubscribe'))->assertOk();
        $this->assertDatabaseCount('admin_push_subscriptions',0);
    }
}
