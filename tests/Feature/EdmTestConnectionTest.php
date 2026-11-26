<?php

namespace Tests\Feature;

use App\Models\EdmSetting;
use App\Models\User;
use App\Services\Invoices\EdmTestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EdmTestConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin'=>true])->save();
        return $user;
    }

    private function reply(string $operation, string $content): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><edm:'.$operation.'Response xmlns:edm="http://tempuri.org/">'.$content.'</edm:'.$operation.'Response></s:Body></s:Envelope>';
    }

    public function test_settings_are_private_and_credentials_are_encrypted(): void
    {
        $this->get(route('admin.edm.edit'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.edm.edit'))->assertForbidden();
        $this->actingAs($this->admin())->patch(route('admin.edm.update'), ['edm_username'=>'sample-user','edm_password'=>'sample-secret'])->assertRedirect(route('admin.edm.edit'))->assertSessionHasNoErrors();
        $setting = EdmSetting::findOrFail(1);
        $this->assertSame('sample-secret',$setting->password);
        $this->assertNotSame('sample-secret',$setting->getRawOriginal('password'));
        $this->assertArrayNotHasKey('password',$setting->toArray());
        $this->get(route('admin.edm.edit'))->assertOk()->assertSee('Fatura Entegrasyonları')->assertDontSee('sample-secret')->assertDontSee('sample-user');
        $this->patch(route('admin.edm.update'), ['edm_password'=>'never-flash-me'])->assertSessionHasErrors('edm_username');
        $this->assertNull(session()->getOldInput('edm_password'));
    }

    public function test_probe_only_calls_test_endpoint_and_logs_out(): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->reply('Login','<SESSION_ID>test-session</SESSION_ID>'))
            ->push($this->reply('CheckCounter','<COUNTER_LEFT>831237</COUNTER_LEFT>'))
            ->push($this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
        $this->actingAs($this->admin())->patch(route('admin.edm.update'), ['edm_username'=>'sample&user','edm_password'=>'sample<secret'])->assertSessionHasNoErrors();
        $this->post(route('admin.edm.probe'))->assertRedirect(route('admin.edm.edit'));
        $this->assertDatabaseHas('edm_settings',['id'=>1,'probe_status'=>'ok','counter_left'=>831237]);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request)=>$request->url()===EdmTestClient::ENDPOINT && $request->hasHeader('SOAPAction','LoginRequest') && str_contains($request->body(),'sample&lt;secret') && str_contains($request->body(),'<SESSION_ID>0</SESSION_ID>'));
        Http::assertSent(fn ($request)=>$request->hasHeader('SOAPAction','LogoutRequest') && str_contains($request->body(),'test-session'));
        $this->get(route('admin.edm.edit'))->assertOk()->assertSee('831.237')->assertDontSee('test-session');
    }

    public function test_failed_login_is_not_retried_and_fault_is_not_persisted(): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::response('<Fault>private-provider-error</Fault>',500)]);
        $this->actingAs($this->admin())->patch(route('admin.edm.update'),['edm_username'=>'sample-user','edm_password'=>'sample-secret']);
        $this->post(route('admin.edm.probe'))->assertRedirect(route('admin.edm.edit'));
        $this->assertDatabaseHas('edm_settings',['id'=>1,'probe_status'=>'failed','counter_left'=>null]);
        Http::assertSentCount(1);
        $this->get(route('admin.edm.edit'))->assertOk()->assertDontSee('private-provider-error');
    }

    public function test_counter_failure_still_logs_out_and_negative_balance_is_not_successful_credit(): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->reply('Login','<SESSION_ID>test-session</SESSION_ID>'))
            ->push($this->reply('CheckCounter','<COUNTER_LEFT>invalid</COUNTER_LEFT>'))
            ->push($this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
        $result = app(EdmTestClient::class)->probe('sample','secret');
        $this->assertSame(['status'=>'counter_failed','counter'=>null],$result);
        Http::assertSentCount(3);
    }
}
