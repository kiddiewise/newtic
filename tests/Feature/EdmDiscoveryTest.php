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
        $this->assertSame(['recipient_unit'=>1,'recipient_alias'=>1],$result['data']['recipient_warning_reasons']);
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
        $this->assertSame(['status'=>'failed','data'=>null,'diagnostic'=>'Lookup: EDM_2005','fault_hints'=>['description_unavailable']],$result);
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
            ->assertStatus(429)->assertSee('İşlem sınırına ulaşıldı.')->assertSee('Ayarlar sayfasına dön');
        $this->assertGreaterThan(0,(int)$blocked->headers->get('Retry-After'));
        $this->assertStringContainsString('no-store',$blocked->headers->get('Cache-Control'));
        $this->assertNull(session()->getOldInput('edm_identifier'));
        $this->get(route('admin.edm.edit'))->assertOk();
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

    public function test_invoice_status_form_is_admin_only_and_validates_without_flashing_uuid(): void
    {
        Http::fake();
        $payload=['kind'=>'invoice_status','edm_uuid'=>'a268a924-0255-4da6-9b47-94383421cb48','edm_profile'=>'TEMELFATURA'];
        $this->post(route('admin.edm.lookup'),$payload)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->post(route('admin.edm.lookup'),$payload)->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.edm.lookup'),$payload)->assertSessionHasErrors('edm');
        $this->post(route('admin.edm.lookup'),array_replace($payload,['edm_uuid'=>'bad']))->assertSessionHasErrors('edm');
        $this->assertNull(session()->getOldInput('edm_uuid'));
        Http::assertNothingSent();
    }

    public function test_invoice_status_form_displays_separate_read_only_states_without_persistence(): void
    {
        $this->actingAs($this->admin())->patch(route('admin.edm.update'),['edm_username'=>'fixture','edm_password'=>'secret']);
        EdmSetting::whereKey(1)->update(['probe_status'=>'ok']);
        $uuid='a268a924-0255-4da6-9b47-94383421cb48';
        $this->sequence('GetInvoiceStatus','<INVOICE_STATUS UUID="'.$uuid.'" ID="EE12018000000013"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>SEND - SUCCEED</STATUS><STATUS_DESCRIPTION>private provider message</STATUS_DESCRIPTION><GIB_STATUS_CODE>1300</GIB_STATUS_CODE><RESPONSE_CODE>ACCEPT</RESPONSE_CODE><DIRECTION>Giden</DIRECTION></INVOICE_STATUS>');
        $response=$this->post(route('admin.edm.lookup'),['kind'=>'invoice_status','edm_uuid'=>$uuid,'edm_profile'=>'TICARIFATURA'])
            ->assertOk()->assertSee($uuid)->assertSee('1300')->assertSee('Ticari fatura yanıtı')->assertSee('mali belge onayı değildir')
            ->assertDontSee('private provider message')->assertDontSee('fixture-session');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertNull(session()->getOldInput('edm_uuid'));
        $this->assertDatabaseCount('edm_submission_attempts',0);
        $this->get(route('admin.edm.edit'))->assertOk()->assertDontSee($uuid);
        Http::assertSentCount(3);
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest') || $r->hasHeader('SOAPAction','SendInvoiceRequest'));
    }


    private function previewPayload(): array
    {
        return ['kind'=>'routing_preview','seller_id'=>'1234567890','buyer_id'=>'1234567891','sender_alias'=>'urn:mail:gb@example.test',
            'receiver_alias'=>'urn:mail:pk@example.test','series'=>'INV','year'=>'2026'];
    }

    public function test_routing_preview_reports_known_matches_and_unverified_rows_separately(): void
    {
        $this->actingAs($this->admin())->patch(route('admin.edm.update'),['edm_username'=>'fixture','edm_password'=>'secret']);
        EdmSetting::whereKey(1)->update(['probe_status'=>'ok']);
        Http::preventStrayRequests();
        $company='<GetCompanyList><VKN>1234567890</VKN><UNVAN>Fixture</UNVAN><GB>urn:mail:gb@example.test</GB>'
            .'<SERIALLIST><SERIAL>INV</SERIAL><YEAR>2026</YEAR><ACTIVEFLAG>1</ACTIVEFLAG><EARCHIVEFLAG>0</EARCHIVEFLAG><INTERNETFLAG>0</INTERNETFLAG></SERIALLIST>'
            .'<SERIALLIST><SERIAL>INVALID</SERIAL></SERIALLIST></GetCompanyList>';
        $recipient='<USER><IDENTIFIER>1234567891</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:pk@example.test</ALIAS></USER>'
            .'<USER><IDENTIFIER>1234567891</IDENTIFIER><UNIT>OTHER</UNIT><ALIAS>invalid</ALIAS></USER>';
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->reply('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push($this->reply('GetCompany',$company))
            ->push($this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))
            ->push($this->reply('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push($this->reply('CheckUser',$recipient))
            ->push($this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
        $r=$this->post(route('admin.edm.lookup'),$this->previewPayload())->assertOk()
            ->assertSee('GB eşleşmesi')->assertSee('PK eşleşmesi')->assertSee('Doğrulanamayan seri: 1')
            ->assertSee('Doğrulanamayan alıcı kaydı: 1')->assertSee('Gönderim yetkisi verilmedi')
            ->assertSee('Seri kodu biçimi')->assertSee('Seri yılı biçimi')->assertSee('Birim türü')->assertSee('Etiket biçimi')
            ->assertDontSee('fixture-session')->assertDontSee('1234567891');
        $this->assertStringContainsString('no-store',$r->headers->get('Cache-Control'));
        $this->assertNull(session()->getOldInput('buyer_id'));
        $this->assertDatabaseCount('edm_submission_attempts',0);
        Http::assertSentCount(6);
        Http::assertNotSent(fn($r)=>in_array($r->header('SOAPAction')[0],['LoadInvoiceRequest','SendInvoiceRequest','GetInvoiceSerialRequest'],true));
    }

    public function test_new_read_only_forms_require_admin_and_verified_connection(): void
    {
        Http::fake();
        $evidence=['kind'=>'invoice_evidence','edm_uuid'=>'a268a924-0255-4da6-9b47-94383421cb48','edm_profile'=>'TEMELFATURA','seller_id'=>'1234567890','buyer_id'=>'1234567891'];
        foreach ([$this->previewPayload(),$evidence] as $payload) {
            auth()->logout();
            $this->post(route('admin.edm.lookup'),$payload)->assertRedirect(route('admin.login'));
            $this->actingAs(User::factory()->create())->post(route('admin.edm.lookup'),$payload)->assertForbidden();
            $this->actingAs($this->admin())->post(route('admin.edm.lookup'),$payload)->assertSessionHasErrors('edm');
        }
        $this->assertNull(session()->getOldInput('buyer_id'));
        Http::assertNothingSent();
    }

    public function test_invoice_evidence_form_displays_bounded_xml_evidence(): void
    {
        $this->actingAs($this->admin())->patch(route('admin.edm.update'),['edm_username'=>'fixture','edm_password'=>'secret']);
        EdmSetting::whereKey(1)->update(['probe_status'=>'ok']);
        $uuid='a268a924-0255-4da6-9b47-94383421cb48';
        $id='INV2026000000001';
        $xml='<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">'
            .'<cbc:UUID>'.$uuid.'</cbc:UUID><cbc:ID>'.$id.'</cbc:ID><cbc:ProfileID>TEMELFATURA</cbc:ProfileID>'
            .'<cac:AccountingSupplierParty><cac:Party><cac:PartyIdentification><cbc:ID>1234567890</cbc:ID></cac:PartyIdentification></cac:Party></cac:AccountingSupplierParty>'
            .'<cac:AccountingCustomerParty><cac:Party><cac:PartyIdentification><cbc:ID>1234567891</cbc:ID></cac:PartyIdentification></cac:Party></cac:AccountingCustomerParty><cbc:Note>private-note</cbc:Note></Invoice>';
        $this->sequence('GetInvoice','<INVOICE UUID="'.$uuid.'" ID="'.$id.'"><HEADER><SENDER>1234567890</SENDER><RECEIVER>1234567891</RECEIVER><PROFILEID>TEMELFATURA</PROFILEID></HEADER><CONTENT>'.base64_encode($xml).'</CONTENT></INVOICE>');
        $r=$this->post(route('admin.edm.lookup'),['kind'=>'invoice_evidence','edm_uuid'=>$uuid,'edm_profile'=>'TEMELFATURA','seller_id'=>'1234567890','buyer_id'=>'1234567891'])
            ->assertOk()->assertSee('Belge kimliği eşleşti')->assertSee(hash('sha256',$xml))->assertSee($uuid)
            ->assertDontSee('private-note')->assertDontSee(base64_encode($xml))->assertDontSee('fixture-session');
        $this->assertStringContainsString('no-store',$r->headers->get('Cache-Control'));
        $this->get(route('admin.edm.edit'))->assertOk()->assertDontSee($uuid);
        $this->assertDatabaseCount('edm_submission_attempts',0);
        Http::assertSentCount(3);
    }


    public function test_warning_diagnostics_never_return_invalid_provider_values(): void
    {
        $this->sequence('CheckUser','<USER><IDENTIFIER>1234567890</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:pk@example.test</ALIAS></USER><USER><IDENTIFIER>9999999999</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:private@example.test</ALIAS></USER><USER><UNIT>GB</UNIT><ALIAS>private-value</ALIAS></USER>');
        $data=app(EdmTestClient::class)->taxpayer('fixture','secret','1234567890')['data'];
        $this->assertSame(2,$data['unverified_records']);
        $this->assertSame(['recipient_identifier_mismatch'=>1,'recipient_identifier_missing'=>1],$data['recipient_warning_reasons']);
        $this->assertStringNotContainsString('9999999999',json_encode($data));
        $this->assertStringNotContainsString('private',json_encode($data));
    }
}
