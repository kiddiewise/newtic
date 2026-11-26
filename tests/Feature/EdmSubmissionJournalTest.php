<?php

namespace Tests\Feature;

use App\Models\EdmSetting;
use App\Models\InvoicePreparation;
use App\Models\Order;
use App\Services\Invoices\EdmSubmissionJournal;
use App\Services\Invoices\EdmTestClient;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EdmSubmissionJournalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Real commits are necessary; do not wrap tests in RefreshDatabase transactions.
        $this->artisan('migrate:fresh')->assertExitCode(0);
    }

    private function account(): EdmSetting
    {
        $account=EdmSetting::query()->firstOrCreate(['id'=>1]);
        $account->forceFill(['username'=>'fixture','password'=>'secret','revision'=>(string)Str::uuid(),'probe_status'=>'ok'])->save();
        return $account;
    }

    private function draft(bool $test=true): InvoicePreparation
    {
        $order=Order::create([
            'order_number'=>'TEST-'.Str::random(10),'checkout_token'=>(string)Str::uuid(),
            'customer_name'=>'Test Buyer','customer_email'=>'buyer@example.test','phone'=>'05321234567',
            'address_label'=>'Ev','city'=>'İzmir','district'=>'Karşıyaka','street_address'=>'Test adres',
            'payment_method'=>'bank_transfer','status'=>'preparing','subtotal_cents'=>12000,
            'shipping_cents'=>0,'total_cents'=>12000,'currency'=>'TRY','is_test'=>$test,'placed_at'=>now(),
        ]);
        $line=['label'=>'Test ürün','quantity'=>1,'unit_gross_cents'=>12000,'kind'=>'product','gross_cents'=>12000,'net_cents'=>10000,'tax_cents'=>2000,'rate'=>20];
        $snapshot=['complete'=>true,'gross_cents'=>12000,'net_cents'=>10000,'tax_cents'=>2000,'groups'=>[['gross_cents'=>12000,'net_cents'=>10000,'tax_cents'=>2000,'rate'=>20]],'lines'=>[$line]];
        return InvoicePreparation::create(['order_id'=>$order->id,'uuid'=>(string)Str::uuid(),'gross_cents'=>12000,'snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),'amounts'=>$snapshot]);
    }

    private function claim(InvoicePreparation $draft,EdmSetting $account)
    {
        return (new EdmSubmissionJournal)->claim($draft->id,'TEMELFATURA',hash('sha256','synthetic fixture'),$account->revision);
    }

    private function soap(string $method,string $content): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><edm:'.$method.'Response xmlns:edm="http://tempuri.org/">'.$content.'</edm:'.$method.'Response></s:Body></s:Envelope>';
    }

    private function statusSequence(string $body): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->soap('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push($this->soap('GetInvoiceStatus',$body))
            ->push($this->soap('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
    }

    public function test_claim_is_committed_once_and_never_sends(): void
    {
        Http::fake();
        $draft=$this->draft();
        $account=$this->account();
        $attempt=$this->claim($draft,$account);
        $this->assertSame(0,DB::transactionLevel());
        $this->assertSame('outcome_unknown',$attempt->state);
        $this->assertArrayNotHasKey('payload_sha256',$attempt->toArray());
        try {
            $this->claim($draft,$account);
            $this->fail('Second claim accepted');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Yeniden gönderilemez',$e->getMessage());
        }
        $this->assertDatabaseCount('edm_submission_attempts',1);
        Http::assertNothingSent();
    }

    public function test_live_order_never_claims(): void
    {
        Http::fake();
        try {
            $this->claim($this->draft(false),$this->account());
            $this->fail('Live claim accepted');
        } catch (DomainException $e) {
            $this->assertStringContainsString('test siparişine',$e->getMessage());
        }
        $this->assertDatabaseCount('edm_submission_attempts',0);
        Http::assertNothingSent();
    }

    public function test_nested_transaction_never_claims(): void
    {
        $draft=$this->draft();
        $account=$this->account();
        try {
            DB::transaction(fn()=>$this->claim($draft,$account));
            $this->fail('Nested claim accepted');
        } catch (DomainException $e) {
            $this->assertStringContainsString('bağımsız',$e->getMessage());
        }
        $this->assertDatabaseCount('edm_submission_attempts',0);
    }

    public function test_reconcile_observes_record_without_declaring_fiscal_success(): void
    {
        $draft=$this->draft();
        $attempt=$this->claim($draft,$this->account());
        $this->statusSequence('<INVOICE_STATUS UUID="'.$draft->uuid.'" ID="EE12018000000013"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>SEND - SUCCEED</STATUS><GIB_STATUS_CODE>1300</GIB_STATUS_CODE><DIRECTION>Giden</DIRECTION></INVOICE_STATUS>');
        $updated=(new EdmSubmissionJournal)->reconcile($attempt->id,new EdmTestClient());
        $this->assertSame('provider_record_found',$updated->state);
        $this->assertSame('1300',$updated->last_gib_code);
        $this->assertSame('EE12018000000013',$updated->observed_invoice_number);
        $this->assertSame('preparing',Order::findOrFail($draft->order_id)->status);
        Http::assertSentCount(3);
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest')||$r->hasHeader('SOAPAction','SendInvoiceRequest'));
    }

    public function test_empty_lookup_remains_uncertain_and_blocks_claim(): void
    {
        $draft=$this->draft();
        $account=$this->account();
        $attempt=$this->claim($draft,$account);
        $this->statusSequence('');
        $updated=(new EdmSubmissionJournal)->reconcile($attempt->id,new EdmTestClient());
        $this->assertSame('outcome_unknown',$updated->state);
        $this->assertSame('unconfirmed',$updated->last_lookup_state);
        $this->expectException(DomainException::class);
        $this->claim($draft,$account);
    }

    public function test_rotated_account_cannot_reconcile(): void
    {
        Http::fake();
        $attempt=$this->claim($this->draft(),$this->account());
        $this->account();
        try {
            (new EdmSubmissionJournal)->reconcile($attempt->id,new EdmTestClient());
            $this->fail('Rotated account accepted');
        } catch (DomainException $e) {
            $this->assertStringContainsString('hesap revizyonuna',$e->getMessage());
        }
        Http::assertNothingSent();
    }

    private function payload(InvoicePreparation $draft): string
    {
        // Minimal synthetic mapping fixture, NOT a valid fiscal document.
        return '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">'
            .'<cbc:UUID>'.$draft->uuid.'</cbc:UUID><cbc:ProfileID>TEMELFATURA</cbc:ProfileID><cbc:InvoiceTypeCode>SATIS</cbc:InvoiceTypeCode><cbc:DocumentCurrencyCode>TRY</cbc:DocumentCurrencyCode><cbc:ID>ABC2009123456789</cbc:ID>'
            .'<cac:AccountingSupplierParty><cac:Party><cac:PartyIdentification><cbc:ID>1234567890</cbc:ID></cac:PartyIdentification></cac:Party></cac:AccountingSupplierParty>'
            .'<cac:AccountingCustomerParty><cac:Party><cac:PartyIdentification><cbc:ID>1234567891</cbc:ID></cac:PartyIdentification></cac:Party></cac:AccountingCustomerParty></Invoice>';
    }

    private function fixturePolicy(): \App\Services\Invoices\EdmSandboxLoadPolicy
    {
        return new class implements \App\Services\Invoices\EdmSandboxLoadPolicy {
            public function authorize(InvoicePreparation $draft,EdmSetting $account,string $xml,string $profile,string $senderAlias,string $receiverAlias): void {}
        };
    }

    public function test_load_transport_claims_before_call_and_ack_is_not_fiscal_success(): void
    {
        $draft=$this->draft();
        $this->account();
        $xml=$this->payload($draft);
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>function ($request) use ($draft,$xml) {
            $method=$request->header('SOAPAction')[0];
            if ($method==='LoginRequest') return Http::response($this->soap('Login','<SESSION_ID>fixture-session</SESSION_ID>'));
            if ($method==='LogoutRequest') return Http::response($this->soap('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'));
            $this->assertSame('LoadInvoiceRequest',$method);
            $this->assertDatabaseHas('edm_submission_attempts',['uuid'=>$draft->uuid,'state'=>'outcome_unknown']);
            $this->assertSame(0,DB::transactionLevel());
            $this->assertStringContainsString(base64_encode($xml),$request->body());
            $this->assertStringContainsString('<GENERATEINVOICEIDONLOAD>false</GENERATEINVOICEIDONLOAD>',$request->body());
            return Http::response($this->soap('LoadInvoice','<INVOICE UUID="'.$draft->uuid.'"/><REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'));
        }]);
        $args=[$draft->id,$xml,'TEMELFATURA','urn:mail:sender@example.test','urn:mail:buyer@example.test',$this->fixturePolicy(),new EdmSubmissionJournal()];
        $result=(new EdmTestClient)->loadSandboxDraft(...$args);
        $this->assertSame('draft_acknowledged',$result['load_status']);
        $this->assertFalse($result['fiscal_success_confirmed']);
        $this->assertDatabaseHas('edm_submission_attempts',['uuid'=>$draft->uuid,'last_load_result'=>'draft_acknowledged','state'=>'outcome_unknown']);
        try {
            (new EdmTestClient)->loadSandboxDraft(...$args);
            $this->fail('Duplicate load accepted');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Yeniden gönderilemez',$e->getMessage());
        }
        Http::assertSentCount(3);
    }

    public function test_load_policy_is_required_and_unsigned_marker_is_rejected(): void
    {
        Http::fake();
        $draft=$this->draft();
        $this->account();
        $party=['identifier'=>'1234567890','name'=>'Test AŞ','address'=>'Örnek','district'=>'Örnek','city'=>'İzmir','tax_office'=>'Örnek'];
        $xml=(new \App\Services\Invoices\UblSaleCandidate(new \App\Services\Invoices\InvoicePreparationXml))->render($draft,$party,$party,'TEMELFATURA','2026-11-26','TEST-1','2026-11-25');
        try {
            (new EdmTestClient)->loadSandboxDraft($draft->id,$xml,'TEMELFATURA','urn:mail:sender@example.test','urn:mail:buyer@example.test',$this->fixturePolicy(),new EdmSubmissionJournal());
            $this->fail('Unsigned candidate sent');
        } catch (DomainException $e) {
            $this->assertStringContainsString('teknik adayı',$e->getMessage());
        }
        $this->assertDatabaseCount('edm_submission_attempts',0);
        Http::assertNothingSent();
        $this->assertFalse(app()->bound(\App\Services\Invoices\EdmSandboxLoadPolicy::class));
    }


    public function test_invoice_center_admin_filters_and_does_not_expose_customer_or_snapshot(): void
    {
        Http::fake();
        $draft=$this->draft();
        $local=$this->draft(false);
        $attempt=$this->claim($draft,$this->account());
        $this->get(route('admin.invoices.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(\App\Models\User::factory()->create())->get(route('admin.invoices.index'))->assertForbidden();
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $response=$this->actingAs($admin)->get(route('admin.invoices.index',['state'=>'uncertain']))
            ->assertOk()->assertSee($draft->uuid)->assertDontSee($local->uuid)
            ->assertSee('Sonuç belirsiz')->assertDontSee('buyer@example.test')->assertDontSee($draft->snapshot_hash);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->get(route('admin.invoices.index',['state'=>'local']))->assertOk()->assertSee($local->uuid)->assertDontSee($draft->uuid);
        Http::assertNothingSent();
    }

    public function test_admin_center_reconciles_existing_test_attempt_only(): void
    {
        $draft=$this->draft();
        $attempt=$this->claim($draft,$this->account());
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->statusSequence('<INVOICE_STATUS UUID="'.$draft->uuid.'" ID="EE12018000000013"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>SEND - SUCCEED</STATUS><GIB_STATUS_CODE>1300</GIB_STATUS_CODE><DIRECTION>Giden</DIRECTION></INVOICE_STATUS>');
        $this->actingAs($admin)->post(route('admin.invoices.reconcile',['attempt'=>$attempt->id]))->assertRedirect(route('admin.invoices.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('edm_submission_attempts',['id'=>$attempt->id,'state'=>'provider_record_found','last_gib_code'=>'1300']);
        $this->get(route('admin.invoices.index',['state'=>'observed']))->assertOk()->assertSee('EE12018000000013');
        $this->assertSame('preparing',Order::findOrFail($draft->order_id)->status);
        Http::assertSentCount(3);
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest')||$r->hasHeader('SOAPAction','SendInvoiceRequest'));
    }

    public function test_center_does_not_reconcile_attempt_after_order_becomes_non_test(): void
    {
        Http::fake();
        $draft=$this->draft();
        $attempt=$this->claim($draft,$this->account());
        Order::whereKey($draft->order_id)->update(['is_test'=>false]);
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->post(route('admin.invoices.reconcile',['attempt'=>$attempt->id]))->assertNotFound();
        Http::assertNothingSent();
    }


    private function smokeFake(bool $accept=true): void
    {
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>function ($request) use ($f,$accept) {
            $method=$request->header('SOAPAction')[0];
            $body=match($method) {
                'LoginRequest'=>'<SESSION_ID>fixture-session</SESSION_ID>',
                'LogoutRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>',
                'GetCompanyRequest'=>'<GetCompanyList><VKN>'.$f::IDENTIFIER.'</VKN><UNVAN>EDM test</UNVAN><GB>'.$f::GB.'</GB><SERIALLIST><SERIAL>A01</SERIAL><YEAR>2026</YEAR><ACTIVEFLAG>1</ACTIVEFLAG><EARCHIVEFLAG>0</EARCHIVEFLAG><INTERNETFLAG>0</INTERNETFLAG></SERIALLIST><SERIALLIST><SERIAL>BAD-ROW</SERIAL></SERIALLIST></GetCompanyList>',
                'CheckUserRequest'=>'<USER><IDENTIFIER>'.$f::IDENTIFIER.'</IDENTIFIER><UNIT>PK</UNIT><ALIAS>'.$f::PK.'</ALIAS></USER><USER><UNIT>OTHER</UNIT><ALIAS>invalid</ALIAS></USER>',
                'LoadInvoiceRequest'=>$accept?'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE UUID="'.$f::UUID.'"/>':'<REQUEST_RETURN><RETURN_CODE>9</RETURN_CODE></REQUEST_RETURN>',
                'GetInvoiceStatusRequest'=>'',
                'GetInvoiceRequest'=>'',
                default=>throw new \RuntimeException('Unexpected SOAP operation'),
            };
            if ($method==='LoadInvoiceRequest') {
                $this->assertDatabaseHas('edm_sandbox_trials',['uuid'=>$f::UUID,'load_status'=>'reserved_unknown']);
                $this->assertSame(0,DB::transactionLevel());
                $this->assertStringContainsString('<INVOICESERIAL_REQUESTED>A01</INVOICESERIAL_REQUESTED>',$request->body());
                $this->assertStringContainsString('<GENERATEINVOICEIDONLOAD>false</GENERATEINVOICEIDONLOAD>',$request->body());
                $this->assertStringContainsString(base64_encode($f::xml('2026-11-26')),$request->body());
            }
            return Http::response($this->soap(substr($method,0,-7),$body));
        }]);
    }

    public function test_controlled_smoke_loads_once_with_explicit_warning_exception_and_never_sends(): void
    {
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        config(['app.url'=>$f::STAGING_URL]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-26 12:00:00','Europe/Istanbul'));
        $this->account();
        $this->smokeFake();
        $hash=hash('sha256',$f::xml('2026-11-26'));
        $r=(new EdmTestClient)->runControlledSmoke('2026-11-26',$hash,$f::CONFIRM);
        $this->assertSame('draft_acknowledged',$r['load_status']);
        $this->assertSame('unconfirmed',$r['status_query']);
        $this->assertFalse($r['fiscal_success_confirmed']);
        $this->assertDatabaseHas('edm_sandbox_trials',['uuid'=>$f::UUID,'series_warnings'=>1,'recipient_warnings'=>1]);
        $this->assertDatabaseCount('orders',0);
        $this->assertDatabaseCount('edm_submission_attempts',0);
        $again=(new EdmTestClient)->runControlledSmoke('2026-11-26',$hash,$f::CONFIRM);
        $this->assertTrue($again['already_reserved']);
        Http::assertSentCount(15);
        Http::assertNotSent(fn($r)=>in_array($r->header('SOAPAction')[0],['SendInvoiceRequest','GetInvoiceSerialRequest'],true));
    }

    public function test_controlled_smoke_refuses_wrong_site_or_digest_before_any_http(): void
    {
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-26 12:00:00','Europe/Istanbul'));
        Http::fake();
        foreach (['https://customer.example.test',$f::STAGING_URL] as $site) {
            config(['app.url'=>$site]);
            try {
                (new EdmTestClient)->runControlledSmoke('2026-11-26',str_repeat('a',64),$f::CONFIRM);
                $this->fail('Unverified trial accepted');
            } catch (DomainException $e) {
                $this->assertStringStartsWith('SMOKE_',$e->getMessage());
            }
        }
        $this->assertDatabaseCount('edm_sandbox_trials',0);
        Http::assertNothingSent();
    }

    public function test_controlled_smoke_uncertain_load_cannot_be_repeated(): void
    {
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        config(['app.url'=>$f::STAGING_URL]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-26 12:00:00','Europe/Istanbul'));
        $this->account();
        $this->smokeFake(false);
        $hash=hash('sha256',$f::xml('2026-11-26'));
        $r=(new EdmTestClient)->runControlledSmoke('2026-11-26',$hash,$f::CONFIRM);
        $this->assertSame('unconfirmed',$r['load_status']);
        $this->assertSame('Lookup: SMOKE_ACK_UNCONFIRMED',$r['diagnostic']);
        $this->assertTrue((new EdmTestClient)->runControlledSmoke('2026-11-26',$hash,$f::CONFIRM)['already_reserved']);
        $this->assertDatabaseCount('edm_sandbox_trials',1);
        Http::assertSentCount(15);
    }


    public function test_sandbox_fault_preserves_only_encrypted_descriptions_without_retry(): void
    {
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        config(['app.url'=>$f::STAGING_URL]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-26 12:00:00','Europe/Istanbul'));
        $this->account();
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>function ($request) use ($f) {
            $method=$request->header('SOAPAction')[0];
            if ($method==='LoadInvoiceRequest') {
                $this->assertDatabaseHas('edm_sandbox_trials',['uuid'=>$f::UUID,'load_status'=>'reserved_unknown']);
                return Http::response('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault><faultstring>private outer message</faultstring><detail><RequestFault xmlns="http://tempuri.org/"><ERROR_CODE xmlns="">11047</ERROR_CODE><ERROR_SHORT_DES xmlns="">Invoice ID invalid</ERROR_SHORT_DES><ERROR_LONG_DES xmlns="">Serial issue; fixture-session; Password: secret</ERROR_LONG_DES><STACKTRACE xmlns="">private-stack</STACKTRACE></RequestFault></detail></s:Fault></s:Body></s:Envelope>',500);
            }
            $body=match($method) {
                'LoginRequest'=>'<SESSION_ID>fixture-session</SESSION_ID>',
                'LogoutRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>',
                'GetCompanyRequest'=>'<GetCompanyList><VKN>'.$f::IDENTIFIER.'</VKN><UNVAN>EDM test</UNVAN><GB>'.$f::GB.'</GB><SERIALLIST><SERIAL>A01</SERIAL><YEAR>2026</YEAR><ACTIVEFLAG>1</ACTIVEFLAG><EARCHIVEFLAG>0</EARCHIVEFLAG><INTERNETFLAG>0</INTERNETFLAG></SERIALLIST></GetCompanyList>',
                'CheckUserRequest'=>'<USER><IDENTIFIER>'.$f::IDENTIFIER.'</IDENTIFIER><UNIT>PK</UNIT><ALIAS>'.$f::PK.'</ALIAS></USER>',
                'GetInvoiceStatusRequest','GetInvoiceRequest'=>'',
                default=>throw new \RuntimeException('Unexpected SOAP operation'),
            };
            return Http::response($this->soap(substr($method,0,-7),$body));
        }]);
        $r=(new EdmTestClient)->runControlledSmoke('2026-11-26',hash('sha256',$f::xml('2026-11-26')),$f::CONFIRM);
        $this->assertSame('Lookup: EDM_11047',$r['diagnostic']);
        $this->assertSame(['number_or_series'],$r['fault_hints']);
        $this->assertArrayNotHasKey('fault_detail_ciphertext',$r);
        $stored=DB::table('edm_sandbox_trials')->where('uuid',$f::UUID)->first();
        $this->assertNotNull($stored->fault_detail_ciphertext);
        $this->assertStringNotContainsString('Invoice ID invalid',$stored->fault_detail_ciphertext);
        $decoded=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($stored->fault_detail_ciphertext),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('Invoice ID invalid',$decoded['ERROR_SHORT_DES']);
        $this->assertStringContainsString('Serial issue',$decoded['ERROR_LONG_DES']);
        $this->assertStringNotContainsString('fixture-session',json_encode($decoded));
        $this->assertStringNotContainsString('secret',json_encode($decoded));
        $this->assertStringNotContainsString('private-stack',json_encode($decoded));
        $this->assertTrue((new EdmTestClient)->runControlledSmoke('2026-11-26',hash('sha256',$f::xml('2026-11-26')),$f::CONFIRM)['already_reserved']);
        Http::assertSentCount(15);
    }
}
