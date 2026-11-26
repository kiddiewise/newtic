<?php

namespace Tests\Feature;

use App\Models\EdmSetting;
use App\Models\User;
use App\Services\Invoices\EdmTestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EdmDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function reply(string $method,string $content): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><edm:'.$method.'Response xmlns:edm="http://tempuri.org/">'.$content.'</edm:'.$method.'Response></s:Body></s:Envelope>';
    }

    private function sequence(string $method,string $body): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->reply('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push($this->reply($method,$body))
            ->push($this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
    }

    private function admin(): User
    {
        $admin=User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        return $admin;
    }

    public function test_company_filters_credentials_and_parses_documented_series(): void
    {
        $this->sequence('GetCompany','<GetCompanyList><VKN>1234567890</VKN><UNVAN>Örnek Firma</UNVAN><GB>urn:mail:gb@example.test</GB><PK>urn:mail:pk@example.test</PK><GIB_PASS>do-not-expose</GIB_PASS><SERIALLİST><SERIAL>ABC</SERIAL><YEAR>2026</YEAR><ACTIVEFLAG>1</ACTIVEFLAG><EARCHIVEFLAG>1</EARCHIVEFLAG><INTERNETFLAG>1</INTERNETFLAG></SERIALLİST></GetCompanyList>');
        $result=app(EdmTestClient::class)->company('fixture','secret','1234567890');
        $this->assertSame('ok',$result['status']);
        $this->assertSame([['code'=>'ABC','year'=>2026,'active'=>true,'archive'=>true,'internet'=>true]],$result['data']['series']);
        $this->assertStringNotContainsString('do-not-expose',json_encode($result));
        Http::assertSentCount(3);
        Http::assertSent(fn($request)=>$request->hasHeader('SOAPAction','GetCompanyRequest') && str_contains($request->body(),'<TAXNUMBER>1234567890</TAXNUMBER>'));
    }

    public function test_lookup_returns_only_active_matching_postboxes(): void
    {
        $this->sequence('CheckUser','<USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:pk@example.test</ALIAS></USER><USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>GB</UNIT><ALIAS>urn:mail:gb@example.test</ALIAS></USER><USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:old@example.test</ALIAS><ALIAS_REMOVAL_TIME>2025-01-01</ALIAS_REMOVAL_TIME></USER>');
        $this->assertSame(['status'=>'ok','data'=>['aliases'=>['urn:mail:pk@example.test'],'sender_aliases'=>['urn:mail:gb@example.test']]],app(EdmTestClient::class)->taxpayer('fixture','secret','1234567890'));
        Http::assertSentCount(3);
        Http::assertSent(fn($r)=>$r->hasHeader('SOAPAction','CheckUserRequest') && str_contains($r->body(),'<USER><IDENTIFIER>1234567890</IDENTIFIER></USER>'));
    }

    public function test_mismatched_recipient_is_failure_not_empty_success(): void
    {
        $this->sequence('CheckUser','<USER><IDENTIFIER>0000000000</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:pk@example.test</ALIAS></USER>');
        $this->assertSame(['status'=>'failed','data'=>null,'diagnostic'=>'Lookup: CHECKUSER_IDENTIFIER_MISMATCH'],app(EdmTestClient::class)->taxpayer('fixture','secret','1234567890'));
        Http::assertSentCount(3);
    }

    public function test_valid_matching_alias_is_preserved_when_other_rows_are_unusable(): void
    {
        $this->sequence('CheckUser','<USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:pk@example.test</ALIAS></USER><USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>OTHER</UNIT><ALIAS>invalid</ALIAS></USER>');
        $result=app(EdmTestClient::class)->taxpayer('fixture','secret','1234567890');
        $this->assertSame('ok',$result['status']);
        $this->assertSame(['urn:mail:pk@example.test'],$result['data']['aliases']);
        $this->assertSame(1,$result['data']['unverified_records']);
    }

    public function test_invalid_alias_is_not_interpreted_as_unregistered(): void
    {
        $this->sequence('CheckUser','<USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>PK</UNIT><ALIAS>invalid</ALIAS></USER>');
        $this->assertSame(['status'=>'failed','data'=>null,'diagnostic'=>'Lookup: CHECKUSER_NO_VALID_ALIAS'],app(EdmTestClient::class)->taxpayer('fixture','secret','1234567890'));
    }

    public function test_sender_only_and_unset_removal_date_are_distinguished(): void
    {
        $this->sequence('CheckUser','<USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>GB</UNIT><ALIAS>urn:mail:gb@example.test</ALIAS><ALIAS_REMOVAL_TIME>0001-01-01T00:00:00</ALIAS_REMOVAL_TIME></USER>');
        $this->assertSame(['status'=>'ok','data'=>['aliases'=>[],'sender_aliases'=>['urn:mail:gb@example.test']]],app(EdmTestClient::class)->taxpayer('fixture','secret','1234567890'));
    }

    public function test_http_soap_fault_returns_only_numeric_provider_code(): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->reply('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault><faultstring>private-secret</faultstring><detail><RequestFault><ERROR_CODE>2005</ERROR_CODE></RequestFault></detail></s:Fault></s:Body></s:Envelope>',500)
            ->push($this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
        $result=app(EdmTestClient::class)->taxpayer('fixture','secret','1234567890');
        $this->assertSame(['status'=>'failed','data'=>null,'diagnostic'=>'Lookup: EDM_2005'],$result);
        Http::assertSentCount(3);
    }

    public function test_lookup_requires_admin_and_verified_connection_and_does_not_flash_identity(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $payload=['kind'=>'taxpayer','edm_identifier'=>'1234567890'];
        $this->post(route('admin.edm.lookup'),$payload)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->post(route('admin.edm.lookup'),$payload)->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.edm.lookup'),$payload)->assertSessionHasErrors('edm');
        $this->post(route('admin.edm.lookup'),['kind'=>'taxpayer','edm_identifier'=>'11111111111'])->assertSessionHasErrors('edm');
        $this->assertNull(session()->getOldInput('edm_identifier'));
        Http::assertNothingSent();
    }

    public function test_edm_limits_are_independent_and_return_to_settings_without_sensitive_input(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->actingAs($this->admin());
        // Exercise a separate numerical throttle used by admin polling.
        for ($i=0; $i<4; $i++) {
            $this->get(route('admin.orders.badge'))->assertOk();
        }
        $payload=['kind'=>'taxpayer','edm_identifier'=>'11111111111'];
        for ($i=0; $i<10; $i++) {
            $this->post(route('admin.edm.lookup'),$payload)->assertRedirect(route('admin.edm.edit'))->assertSessionHasErrors('edm');
        }
        $blocked=$this->post(route('admin.edm.lookup'),$payload)
            ->assertStatus(303)->assertRedirect(route('admin.edm.edit'))
            ->assertSessionHasErrors('edm');
        $this->assertGreaterThan(0,(int)$blocked->headers->get('Retry-After'));
        $this->assertStringContainsString('no-store',$blocked->headers->get('Cache-Control'));
        $this->assertNull(session()->getOldInput('edm_identifier'));
        $this->get(route('admin.edm.edit'))->assertOk()->assertSee('İşlem sınırına ulaşıldı.');
        $this->postJson(route('admin.edm.lookup'),$payload)->assertStatus(429)->assertJsonStructure(['message']);
        // Exhausting lookup must not block a different EDM operation.
        $this->post(route('admin.edm.probe'))->assertRedirect(route('admin.edm.edit'))->assertSessionHasErrors(['edm'=>'Önce test hesabınızı kaydedin.']);
        $this->patch(route('admin.edm.update'),['edm_username'=>'fixture','edm_password'=>'secret'])->assertRedirect(route('admin.edm.edit'))->assertSessionHasNoErrors();
        Http::assertNothingSent();
    }

    public function test_successful_lookup_returns_noncacheable_page_without_persisting_result(): void
    {
        $this->actingAs($this->admin())->patch(route('admin.edm.update'),['edm_username'=>'fixture','edm_password'=>'secret']);
        EdmSetting::whereKey(1)->update(['probe_status'=>'ok']);
        $this->sequence('CheckUser','<USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:pk@example.test</ALIAS></USER>');
        $response=$this->post(route('admin.edm.lookup'),['kind'=>'taxpayer','edm_identifier'=>'1234567890'])->assertOk()->assertSee('e-Fatura posta kutusu bulundu')->assertSee('urn:mail:pk@example.test')->assertDontSee('fixture-session');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertNull(session()->getOldInput('edm_identifier'));
        $this->get(route('admin.edm.edit'))->assertOk()->assertDontSee('urn:mail:pk@example.test');
    }
}
