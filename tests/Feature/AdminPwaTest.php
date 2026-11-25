<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminWebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AdminPwaTest extends TestCase
{
    use RefreshDatabase;

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
