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

    public function test_unknown_portal_token_visible_to_admin_does_not_unlock_send(): void
    {
        $draft=$this->draft();
        $attempt=$this->claim($draft,$this->account());
        $number='A012026000000305';
        (new EdmSubmissionJournal)->recordLoadObservation($attempt->id,$attempt->account_revision,true,$number);
        $this->statusSequence('<INVOICE_STATUS UUID="'.$draft->uuid.'" ID="'.$number.'"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>DRAFT - EXAMPLE_UNKNOWN</STATUS><DIRECTION>OUT</DIRECTION></INVOICE_STATUS>');
        $updated=(new EdmSubmissionJournal)->reconcile($attempt->id,new EdmTestClient());
        $this->assertSame('unknown',$updated->last_portal_state);
        $this->assertSame('DRAFT - EXAMPLE_UNKNOWN',$updated->last_portal_token);
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk()
            ->assertSee('DRAFT - EXAMPLE_UNKNOWN')->assertSee('Nihai gönderim şu anda kapalı')
            ->assertDontSee('EDM gönderimini başlat');
        Http::assertSentCount(3);
    }

    public function test_verified_draft_displays_send_control(): void
    {
        $draft=$this->draft();
        $attempt=$this->claim($draft,$this->account());
        $number='A012026000000305';
        (new EdmSubmissionJournal)->recordLoadObservation($attempt->id,$attempt->account_revision,true,$number);
        $this->statusSequence('<INVOICE_STATUS UUID="'.$draft->uuid.'" ID="'.$number.'"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>DRAFT</STATUS><DIRECTION>OUT</DIRECTION></INVOICE_STATUS>');
        $updated=(new EdmSubmissionJournal)->reconcile($attempt->id,new EdmTestClient());
        $this->assertSame('draft',$updated->last_portal_state);
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk()->assertSee('EDM gönderimini başlat');
        Http::assertSentCount(3);
    }

    public function test_edm_loaded_draft_displays_send_control_without_sending(): void
    {
        $draft=$this->draft();
        $attempt=$this->claim($draft,$this->account());
        $number='A012026000000305';
        (new EdmSubmissionJournal)->recordLoadObservation($attempt->id,$attempt->account_revision,true,$number);
        $this->statusSequence('<INVOICE_STATUS UUID="'.$draft->uuid.'" ID="'.$number.'"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>LOAD - SUCCEED</STATUS><DIRECTION>OUT</DIRECTION></INVOICE_STATUS>');
        $updated=(new EdmSubmissionJournal)->reconcile($attempt->id,new EdmTestClient());
        $this->assertSame('draft',$updated->last_portal_state);
        $this->assertSame('LOAD - SUCCEED',$updated->last_portal_token);
        $this->assertNull($updated->last_gib_code);
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk()
            ->assertSee('LOAD - SUCCEED')->assertSee('EDM gönderimini başlat');
        Http::assertSentCount(3);
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','SendInvoiceRequest'));
    }

    public function test_edm_load_fail_or_existing_envelope_does_not_unlock_send(): void
    {
        $draft=$this->draft();
        $attempt=$this->claim($draft,$this->account());
        $number='A012026000000305';
        (new EdmSubmissionJournal)->recordLoadObservation($attempt->id,$attempt->account_revision,true,$number);
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $states=['<STATUS>LOAD - FAIL</STATUS>','<STATUS>LOAD - SUCCEED</STATUS><GIB_STATUS_CODE>1300</GIB_STATUS_CODE>'];
        Http::preventStrayRequests();
        $sequence=Http::sequence();
        foreach ($states as $state) {
            $sequence->push($this->soap('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
                ->push($this->soap('GetInvoiceStatus','<INVOICE_STATUS UUID="'.$draft->uuid.'" ID="'.$number.'"><HEADER><EARCHIVE>false</EARCHIVE></HEADER>'.$state.'<DIRECTION>OUT</DIRECTION></INVOICE_STATUS>'))
                ->push($this->soap('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'));
        }
        Http::fake([EdmTestClient::ENDPOINT=>$sequence]);
        foreach ($states as $state) {
            (new EdmSubmissionJournal)->reconcile($attempt->id,new EdmTestClient());
            $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk()
                ->assertSee('Nihai gönderim şu anda kapalı')->assertDontSee('EDM gönderimini başlat');
        }
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','SendInvoiceRequest'));
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

    public function test_second_numbered_smoke_with_pinned_template(): void
    {
        $path=getenv('NEWTIC_EDM_SECOND_XSLT');
        if (!$path) $this->markTestSkipped('Pinned official template required.');
        $template=file_get_contents($path);
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        $s=\App\Services\Invoices\EdmSecondSandboxFixture::class;
        config(['app.url'=>$f::STAGING_URL]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-26 12:00:00','Europe/Istanbul'));
        $account=$this->account();
        DB::table('edm_sandbox_trials')->insert(['uuid'=>$f::UUID,'account_revision'=>$account->revision,
            'issue_date'=>'2026-11-26','xml_sha256'=>str_repeat('a',64),'request_sha256'=>str_repeat('b',64),
            'series_warnings'=>1,'recipient_warnings'=>1,'load_status'=>'unconfirmed','diagnostic'=>'Lookup: EDM_11047',
            'created_at'=>now(),'updated_at'=>now()]);
        $candidate=$s::prepare('2026-11-26',$template);
        $number='A012026000000001';
        $doc=new \DOMDocument();
        $this->assertTrue($doc->loadXML($candidate['xml'],LIBXML_NONET));
        $xp=new \DOMXPath($doc);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->query('/i:Invoice/cbc:ID')->item(0)->textContent=$number;
        $xml=$doc->saveXML();
        $pdf="%PDF-1.4\n% mock transport fixture\n%%EOF\n";
        $oldVisible=true;
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>function($request) use($s,$f,$number,$xml,$pdf,$candidate,&$oldVisible) {
            $method=$request->header('SOAPAction')[0];
            $old=str_contains($request->body(),$f::UUID);
            if ($old&&in_array($method,['GetInvoiceStatusRequest','GetInvoiceRequest'],true)) {
                if ($oldVisible&&$method==='GetInvoiceStatusRequest') {
                    return Http::response($this->soap('GetInvoiceStatus','<INVOICE_STATUS UUID="'.$f::UUID.'" ID="'.$number.'"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>DRAFT</STATUS><DIRECTION>OUT</DIRECTION></INVOICE_STATUS>'));
                }
                return Http::response('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault><faultstring>not found</faultstring><detail><RequestFault><ERROR_CODE>11017</ERROR_CODE></RequestFault></detail></s:Fault></s:Body></s:Envelope>',500);
            }
            $body=match($method) {
                'LoginRequest'=>'<SESSION_ID>fixture-session</SESSION_ID>',
                'LogoutRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>',
                'GetCompanyRequest'=>'<GetCompanyList><VKN>'.$f::IDENTIFIER.'</VKN><UNVAN>EDM test</UNVAN><GB>'.$f::GB.'</GB><SERIALLIST><SERIAL>A01</SERIAL><YEAR>2026</YEAR><ACTIVEFLAG>1</ACTIVEFLAG><EARCHIVEFLAG>0</EARCHIVEFLAG><INTERNETFLAG>0</INTERNETFLAG></SERIALLIST></GetCompanyList>',
                'CheckUserRequest'=>'<USER><IDENTIFIER>'.$f::IDENTIFIER.'</IDENTIFIER><UNIT>PK</UNIT><ALIAS>'.$f::PK.'</ALIAS></USER>',
                'LoadInvoiceRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE UUID="'.$s::UUID.'" ID="'.$number.'"/>',
                'GetInvoiceStatusRequest'=>'<INVOICE_STATUS UUID="'.$s::UUID.'" ID="'.$number.'"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>DRAFT</STATUS><DIRECTION>OUT</DIRECTION></INVOICE_STATUS>',
                'GetInvoiceRequest'=>'<INVOICE UUID="'.$s::UUID.'" ID="'.$number.'"><HEADER><SENDER>'.$f::IDENTIFIER.'</SENDER><RECEIVER>'.$f::IDENTIFIER.'</RECEIVER><PROFILEID>TEMELFATURA</PROFILEID></HEADER><CONTENT>'.base64_encode(str_contains($request->body(),'<INVOICE_CONTENT_TYPE>PDF</INVOICE_CONTENT_TYPE>')?$pdf:$xml).'</CONTENT></INVOICE>',
                default=>throw new \RuntimeException('Unexpected SOAP method'),
            };
            if ($method==='LoadInvoiceRequest') {
                $this->assertDatabaseHas('edm_sandbox_trials',['uuid'=>$s::UUID,'load_status'=>'reserved_unknown']);
                $this->assertSame(0,DB::transactionLevel());
                $this->assertStringContainsString('<GENERATEINVOICEIDONLOAD>true</GENERATEINVOICEIDONLOAD>',$request->body());
                $this->assertStringContainsString('<INVOICESERIAL_REQUESTED>A01</INVOICESERIAL_REQUESTED>',$request->body());
                $this->assertStringContainsString(base64_encode($candidate['xml']),$request->body());
            }
            return Http::response($this->soap(substr($method,0,-7),$body));
        }]);
        $client=new EdmTestClient;
        $args=['2026-11-26',$template,$candidate['xml_sha256'],'LOAD-ONE-NUMBERED-TEST-DRAFT'];
        $blocked=$client->runSecondControlledSmoke(...$args);
        $this->assertSame('blocked_first_readback',$blocked['load_status']);
        $this->assertDatabaseCount('edm_sandbox_trials',1);
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest'));
        $oldVisible=false;
        $result=$client->runSecondControlledSmoke(...$args);
        $this->assertSame('draft_acknowledged',$result['load_status']);
        $this->assertSame($number,$result['allocated_invoice_id']);
        foreach (['status_query','xml_query','pdf_query'] as $field) $this->assertSame('matched',$result[$field]);
        $this->assertFalse($result['fiscal_success_confirmed']);
        $this->assertDatabaseCount('edm_sandbox_trials',2);
        $this->assertDatabaseCount('edm_sandbox_documents',2);
        $this->assertDatabaseCount('orders',0);
        $this->assertDatabaseHas('edm_sandbox_trials',['uuid'=>$f::UUID,'diagnostic'=>'Lookup: EDM_11047']);
        $this->assertTrue($client->runSecondControlledSmoke(...$args)['already_reserved']);
        $this->assertSame(1,Http::recorded(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest'))->count());
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','SendInvoiceRequest'));
        $row=DB::table('edm_sandbox_documents')->where('format','pdf')->first();
        $this->assertStringNotContainsString('%PDF-1.4',$row->ciphertext);
        $download=route('admin.edm.sandbox.download',['uuid'=>$s::UUID,'format'=>'pdf']);
        $this->get($download)->assertRedirect(route('admin.login'));
        $this->actingAs(\App\Models\User::factory()->create())->get($download)->assertForbidden();
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $response=$this->actingAs($admin)->get($download)->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertSame($pdf,$response->getContent());
        $this->assertStringContainsString('attachment',$response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->get(route('admin.edm.sandbox.download',['uuid'=>$s::UUID,'format'=>'xml']))->assertOk()->assertSee($number);
        $this->get(route('admin.edm.edit'))->assertOk()->assertSee($s::UUID)->assertSee($number)->assertSee('PDF indir');
        $this->account();
        $this->get($download)->assertNotFound();
    }

    public function test_second_pdf_transport_rejects_wrong_number_and_non_pdf(): void
    {
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        $s=\App\Services\Invoices\EdmSecondSandboxFixture::class;
        $number='A012026000000001';
        foreach ([['A012026000000002',"%PDF-1.4\n%%EOF"],[$number,'not pdf']] as [$id,$content]) {
            $doc=new \DOMDocument();
            $doc->loadXML('<GetInvoiceResponse><INVOICE UUID="'.$s::UUID.'" ID="'.$id.'"><HEADER><SENDER>'.$f::IDENTIFIER.'</SENDER><RECEIVER>'.$f::IDENTIFIER.'</RECEIVER><PROFILEID>TEMELFATURA</PROFILEID></HEADER><CONTENT>'.base64_encode($content).'</CONTENT></INVOICE></GetInvoiceResponse>',LIBXML_NONET);
            try {
                \App\Services\Invoices\EdmSandboxDocuments::pdf($doc->documentElement,$s::UUID,$number);
                $this->fail('Invalid PDF evidence accepted');
            } catch (\App\Services\Invoices\EdmLookupFailure $e) {
                $this->assertContains($e->safeCode,['PDF_IDENTITY_MISMATCH','PDF_CONTENT_INVALID']);
            }
        }
    }


    private function dispatchOrder(string $status='completed',bool $paid=true,bool $isolated=true): Order
    {
        $o=Order::create(['order_number'=>'PK-'.Str::random(12),'checkout_token'=>(string)Str::uuid(),
            'customer_name'=>'Private Buyer','customer_email'=>'private@example.test','phone'=>'05321234567',
            'address_label'=>'Ev','city'=>'İzmir','district'=>'Karşıyaka','street_address'=>'Private address',
            'payment_method'=>'paytr','status'=>$status,'subtotal_cents'=>12000,'shipping_cents'=>0,
            'total_cents'=>12000,'currency'=>'TRY','is_test'=>$isolated,'placed_at'=>now()]);
        $o->items()->create(['product_name'=>'Private personalized label','sku'=>'SKU-X','quantity'=>2,
            'unit_price_cents'=>6000,'line_total_cents'=>12000,'vat_rate'=>20]);
        if ($paid) $o->paytrAttempts()->create(['merchant_oid'=>'NT'.Str::random(16),'amount_cents'=>12000,
            'paid_total_cents'=>12000,'currency'=>'TRY','status'=>'success','is_test'=>$isolated]);
        return $o;
    }

    public function test_manual_invoice_center_lists_verified_orders_and_accepts_single_and_bulk_requests(): void
    {
        config(['app.url'=>\App\Services\Invoices\EdmSandboxSmokeFixture::STAGING_URL]);
        Http::fake();
        $this->account();
        $a=$this->dispatchOrder('completed'); $b=$this->dispatchOrder('preparing');
        $unpaid=$this->dispatchOrder('completed',false); $cancelled=$this->dispatchOrder('cancelled'); $live=$this->dispatchOrder('completed',true,false);
        $this->get(route('admin.invoices.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(\App\Models\User::factory()->create())->post(route('admin.invoices.create'),['orders'=>[$a->id]])->assertForbidden();
        $admin=\App\Models\User::factory()->create(); $admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk()->assertSee('Fatura kesilmesi bekleyen siparişler')
            ->assertSee($a->order_number)->assertSee($b->order_number)->assertDontSee($unpaid->order_number)->assertDontSee($cancelled->order_number)->assertDontSee($live->order_number);
        $this->post(route('admin.invoices.create'),['orders'=>[$a->id,$b->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoice_dispatch_requests',2);
        $this->assertDatabaseCount('invoice_preparations',2);
        $this->assertDatabaseHas('invoice_dispatch_requests',['order_id'=>$a->id,'state'=>'queued','source'=>'manual','requested_by'=>$admin->id]);
        foreach ([$a,$unpaid,$cancelled,$live] as $o) $this->post(route('admin.invoices.create'),['orders'=>[$o->id]])->assertSessionHasErrors('invoice');
        $this->assertDatabaseCount('invoice_dispatch_requests',2);
        $this->assertDatabaseCount('edm_submission_attempts',0);
        $this->assertTrue(app(\App\Services\Invoices\InvoiceDispatch::class)->process()['connection_pending']);
        Http::assertNothingSent();
    }

    public function test_portable_store_can_queue_only_test_orders_and_requires_account_proof(): void
    {
        config(['app.url'=>'https://independent-shop.example.test','newtic.checkout_test_mode'=>true]);
        Http::preventStrayRequests();
        Http::fake();
        $this->account();
        $test=$this->dispatchOrder();
        $live=$this->dispatchOrder('completed',true,false);
        $service=app(\App\Services\Invoices\InvoiceDispatch::class);
        $service->enqueue($test->id,null,null);
        $this->assertDatabaseHas('invoice_dispatch_requests',['order_id'=>$test->id,'state'=>'queued']);
        $this->assertTrue($service->process()['connection_pending']);
        config(['newtic.checkout_test_mode'=>false]);
        try {
            $service->enqueue($live->id,null,null);
            $this->fail('Live order accepted by sandbox worker');
        } catch (DomainException $e) {}
        $this->assertDatabaseCount('invoice_dispatch_requests',1);
        $this->assertTrue($service->process()['connection_pending']);
        Http::assertNothingSent();
    }

    public function test_order_archive_preflight_reads_series_without_reserving_invoice(): void
    {
        config(['app.url'=>'https://independent-shop.example.test','newtic.checkout_test_mode'=>true]);
        $this->account();
        $order=$this->dispatchOrder();
        $order->forceFill(['invoice_type'=>'individual','billing_tckn'=>null])->save();
        $order->paytrAttempts()->update(['reconciliation_result'=>'callback_success','processed_at'=>now()]);
        $year=now('Europe/Istanbul')->format('Y');
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->soap('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push($this->soap('GetCompany','<GetCompanyList><VKN>3230512384</VKN><UNVAN>Fixture</UNVAN><SERIALLIST><SERIAL>EA1</SERIAL><YEAR>'.$year.'</YEAR><ACTIVEFLAG>1</ACTIVEFLAG><EARCHIVEFLAG>1</EARCHIVEFLAG><INTERNETFLAG>1</INTERNETFLAG></SERIALLIST></GetCompanyList>'))
            ->push($this->soap('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
        $admin=\App\Models\User::factory()->create();
        $admin->forceFill(['is_admin'=>true])->save();
        $url=route('admin.invoices.order-preflight',['order'=>$order->id]);
        $data=['seller_id'=>'3230512384','synthetic_confirm'=>1,'anonymous_test'=>1];
        $this->post($url,$data)->assertRedirect(route('admin.login'));
        $this->actingAs($admin)->post($url,array_diff_key($data,['synthetic_confirm'=>1]))->assertSessionHasErrors('invoice');
        $this->post($url,$data)->assertOk()->assertSee('Sentetik bireysel e-Arşiv adayı')
            ->assertSee('EA1')->assertSee('https://independent-shop.example.test')->assertSee('Belgeyle doğrulanmalı')
            ->assertDontSee('private@example.test');
        $this->assertDatabaseCount('invoice_preparations',0);
        $this->assertDatabaseCount('invoice_dispatch_requests',0);
        $this->assertDatabaseCount('edm_submission_attempts',0);
        Http::assertSentCount(3);
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','SendInvoiceRequest')||$r->hasHeader('SOAPAction','LoadInvoiceRequest'));
        config(['newtic.checkout_test_mode'=>false]);
        $this->post($url,$data)->assertNotFound();
    }

    public function test_buyer_classification_fails_closed_on_uncertain_lookup(): void
    {
        $class=\App\Services\Invoices\EdmOrderArchivePreflight::class;
        $this->assertSame('einvoice',$class::documentType(['status'=>'ok','data'=>['aliases'=>['urn:mail:pk@example.test'],'sender_aliases'=>[]]]));
        $this->assertSame('archive_candidate',$class::documentType(['status'=>'ok','data'=>['aliases'=>[],'sender_aliases'=>[]]]));
        $this->assertSame('unknown',$class::documentType(['status'=>'ok','data'=>['aliases'=>[],'sender_aliases'=>['urn:mail:gb@example.test']]]));
        $this->assertSame('unknown',$class::documentType(['status'=>'ok','data'=>['aliases'=>[],'sender_aliases'=>[],'unverified_records'=>1]]));
        $this->assertSame('unknown',$class::documentType(['status'=>'failed']));
    }

    public function test_completion_automation_is_opt_in_and_does_not_capture_historical_orders(): void
    {
        config(['app.url'=>\App\Services\Invoices\EdmSandboxSmokeFixture::STAGING_URL]);
        $account=$this->account(); $o=$this->dispatchOrder();
        $admin=\App\Models\User::factory()->create(); $admin->forceFill(['is_admin'=>true])->save();
        $this->assertFalse((bool)$account->fresh()->auto_invoice);
        $this->actingAs($admin)->post(route('admin.invoices.settings'),['auto_invoice'=>1,'shipping_vat_rate'=>20])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue((bool)$account->fresh()->auto_invoice);
        $dispatch=app(\App\Services\Invoices\InvoiceDispatch::class);
        try {$dispatch->enqueue($o->id,null,null,'auto');$this->fail('Historical order auto-selected');}catch(DomainException){}
        DB::table('order_status_changes')->insert(['order_id'=>$o->id,'from_status'=>'shipped','to_status'=>'completed','source'=>'admin','created_at'=>now()->addSecond()]);
        $dispatch->enqueue($o->id,null,null,'auto');
        $this->assertDatabaseHas('invoice_dispatch_requests',['order_id'=>$o->id,'source'=>'auto']);
        $this->post(route('admin.invoices.settings'),['auto_invoice'=>0])->assertRedirect();
        $this->assertFalse((bool)$account->fresh()->auto_invoice);
    }

    public function test_queued_order_draft_payload_with_pinned_template_preserves_money_not_customer_data(): void
    {
        $path=getenv('NEWTIC_EDM_SECOND_XSLT');
        if (!$path) $this->markTestSkipped('Pinned official template required.');
        $o=$this->dispatchOrder();
        $draft=app(\App\Services\Invoices\InvoicePreparationService::class)->prepare($o,null);
        $fields=\App\Services\Invoices\EdmOrderDraftPayload::fields($draft,'2026-11-27',file_get_contents($path));
        $xml=base64_decode($fields['INVOICE']['CONTENT'],true);
        $this->assertStringNotContainsString('Private',$xml);
        $this->assertStringNotContainsString('private@example.test',$xml);
        $this->assertStringContainsString('120.00',$xml);
        \App\Services\Invoices\EdmUblNamespace::assertTransport($xml);
        $this->assertStringContainsString($draft->uuid,$xml);
        $this->assertSame('true',$fields['GENERATEINVOICEIDONLOAD']);
        $this->assertSame('A01',$fields['INVOICE']['HEADER']['INVOICESERIAL_REQUESTED']);
    }


    public function test_order_dispatch_worker_with_pinned_template_calls_edm_once_after_numbered_trial(): void
    {
        $path=getenv('NEWTIC_EDM_SECOND_XSLT');
        if (!$path) $this->markTestSkipped('Pinned official template required.');
        $template=file_get_contents($path);
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        $second=\App\Services\Invoices\EdmSecondSandboxFixture::class;
        $service=\App\Services\Invoices\InvoiceDispatch::class;
        config(['app.url'=>$f::STAGING_URL]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 12:00:00','Europe/Istanbul'));
        $account=$this->account();$order=$this->dispatchOrder();
        $dispatcher=app($service);
        $dispatcher->enqueue($order->id,null,null);
        $draft=InvoicePreparation::where('order_id',$order->id)->firstOrFail();
        DB::table('edm_sandbox_trials')->insert(['uuid'=>$second::UUID,'account_revision'=>$account->revision,
            'issue_date'=>'2026-11-27','xml_sha256'=>str_repeat('a',64),'request_sha256'=>str_repeat('b',64),
            'series_warnings'=>0,'recipient_warnings'=>0,'load_status'=>'draft_acknowledged',
            'allocated_invoice_id'=>'A012026000000001','created_at'=>now(),'updated_at'=>now()]);
        $file=$service::templatePath();
        $this->assertFileDoesNotExist($file);
        if (!is_dir(dirname($file))) mkdir(dirname($file),0700,true);
        file_put_contents($file,$template);
        try {
            Http::preventStrayRequests();
            Http::fake([EdmTestClient::ENDPOINT=>function($request)use($f,$draft,$order){
                $m=$request->header('SOAPAction')[0];
                if ($m==='LoadInvoiceRequest') {
                    $this->assertDatabaseHas('invoice_dispatch_requests',['order_id'=>$order->id,'state'=>'processing']);
                    $this->assertDatabaseHas('edm_submission_attempts',['uuid'=>$draft->uuid,'state'=>'outcome_unknown']);
                    $this->assertSame(0,DB::transactionLevel());
                    $this->assertStringContainsString('<GENERATEINVOICEIDONLOAD>true</GENERATEINVOICEIDONLOAD>',$request->body());
                }
                $body=match($m){
                    'LoginRequest'=>'<SESSION_ID>fixture-session</SESSION_ID>',
                    'LogoutRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>',
                    'GetCompanyRequest'=>'<GetCompanyList><VKN>'.$f::IDENTIFIER.'</VKN><UNVAN>EDM test</UNVAN><GB>'.$f::GB.'</GB><SERIALLIST><SERIAL>A01</SERIAL><YEAR>2026</YEAR><ACTIVEFLAG>1</ACTIVEFLAG><EARCHIVEFLAG>0</EARCHIVEFLAG><INTERNETFLAG>0</INTERNETFLAG></SERIALLIST></GetCompanyList>',
                    'CheckUserRequest'=>'<USER><IDENTIFIER>'.$f::IDENTIFIER.'</IDENTIFIER><UNIT>PK</UNIT><ALIAS>'.$f::PK.'</ALIAS></USER>',
                    'LoadInvoiceRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE UUID="'.$draft->uuid.'" ID="A012026000000002"/>',
                    'GetInvoiceStatusRequest'=>'<INVOICE_STATUS UUID="'.$draft->uuid.'" ID="A012026000000002"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>DRAFT</STATUS><DIRECTION>OUT</DIRECTION></INVOICE_STATUS>',
                    default=>throw new \RuntimeException('Unexpected SOAP method'),
                };
                return Http::response($this->soap(substr($m,0,-7),$body));
            }]);
            $this->assertSame(1,$dispatcher->process()['processed']);
            $this->assertSame(0,$dispatcher->process()['processed']);
            $this->assertDatabaseHas('invoice_dispatch_requests',['order_id'=>$order->id,'state'=>'acknowledged']);
            $this->assertDatabaseHas('edm_submission_attempts',['uuid'=>$draft->uuid,'last_load_result'=>'draft_acknowledged','observed_invoice_number'=>'A012026000000002']);
            $this->assertSame(1,Http::recorded(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest'))->count());
            Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','SendInvoiceRequest'));
            $this->assertSame('completed',$order->fresh()->status);
        } finally {unlink($file);}
    }


    public function test_historical_vat_confirmation_fills_only_missing_lines_and_no_false_success(): void
    {
        config(['app.url'=>\App\Services\Invoices\EdmSandboxSmokeFixture::STAGING_URL]);
        Http::fake();
        $this->account();
        $o=$this->dispatchOrder();
        $old=$o->items()->firstOrFail();
        $old->forceFill(['vat_rate'=>null,'quantity'=>1,'line_total_cents'=>6000])->save();
        $known=$o->items()->create(['product_name'=>'Known rate','sku'=>'VAT-KNOWN','quantity'=>1,
            'unit_price_cents'=>6000,'line_total_cents'=>6000,'vat_rate'=>10]);
        $o->forceFill(['shipping_cents'=>1200,'total_cents'=>13200])->save();
        $o->paytrAttempts()->update(['amount_cents'=>13200,'paid_total_cents'=>13200]);
        $admin=\App\Models\User::factory()->create();$admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk()->assertSee('Eksik ürün KDV oranını onayla');
        $this->post(route('admin.invoices.create'),['orders'=>[$o->id],'shipping_rate'=>20])
            ->assertSessionHasErrors('invoice')->assertSessionMissing('success');
        $this->assertNull($old->fresh()->vat_rate);
        $this->assertDatabaseCount('invoice_dispatch_requests',0);
        $this->post(route('admin.invoices.create'),['orders'=>[$o->id],'missing_product_rate'=>20])
            ->assertSessionHasErrors('invoice')->assertSessionMissing('success');
        $this->assertNull($old->fresh()->vat_rate);
        $this->assertDatabaseCount('invoice_vat_confirmations',0);
        $this->post(route('admin.invoices.create'),['orders'=>[$o->id],'shipping_rate'=>20,'missing_product_rate'=>20])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(20,$old->fresh()->vat_rate);
        $this->assertSame(10,$known->fresh()->vat_rate);
        $this->assertDatabaseHas('invoice_vat_confirmations',['order_item_id'=>$old->id,'confirmed_by'=>$admin->id,'vat_rate'=>20]);
        $this->assertDatabaseCount('invoice_vat_confirmations',1);
        $draft=InvoicePreparation::where('order_id',$o->id)->firstOrFail();
        $this->assertSame(13200,$draft->gross_cents);
        $this->assertSame([20,10,20],array_column($draft->amounts['lines'],'rate'));
        $this->assertDatabaseHas('invoice_dispatch_requests',['order_id'=>$o->id,'state'=>'queued']);
        $this->post(route('admin.invoices.create'),['orders'=>[$o->id],'missing_product_rate'=>1])
            ->assertSessionHasErrors('invoice')->assertSessionMissing('success');
        $this->assertSame(20,$old->fresh()->vat_rate);
        $this->assertDatabaseCount('invoice_preparations',1);
        Http::assertNothingSent();
    }


    public function test_provider_order_document_access_requires_admin_and_same_account(): void
    {
        config(['app.url'=>\App\Services\Invoices\EdmSandboxSmokeFixture::STAGING_URL]);
        $a=$this->account();$draft=$this->draft();$attempt=$this->claim($draft,$a);
        $attempt->forceFill(['state'=>'provider_record_found','last_load_result'=>'draft_acknowledged','observed_invoice_number'=>'A012026000000304'])->save();
        $url=route('admin.invoices.documents.download',['attempt'=>$attempt->id,'format'=>'pdf']);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(\App\Models\User::factory()->create())->get($url)->assertForbidden();
        $admin=\App\Models\User::factory()->create();$admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->get($url)->assertNotFound();
        $pdf="%PDF-1.4\nfixture\n%%EOF\n";
        DB::table('edm_order_documents')->insert(['attempt_id'=>$attempt->id,'format'=>'pdf','account_revision'=>$a->revision,
            'invoice_id'=>$attempt->observed_invoice_number,'sha256'=>hash('sha256',$pdf),'byte_count'=>strlen($pdf),
            'signature_present'=>false,'ciphertext'=>\Illuminate\Support\Facades\Crypt::encryptString(base64_encode($pdf)),'created_at'=>now()]);
        $this->get($url)->assertOk()->assertHeader('Content-Type','application/pdf')->assertHeader('Cache-Control','max-age=0, no-store, private');
        $this->account();$this->get($url)->assertNotFound();
    }

    public function test_new_order_payload_preserves_order_reference_and_catalog_labels(): void
    {
        $path=getenv('NEWTIC_EDM_SECOND_XSLT');
        if (!$path) $this->markTestSkipped('Pinned official template required.');
        config(['app.url'=>\App\Services\Invoices\EdmSandboxSmokeFixture::STAGING_URL]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 12:00:00','Europe/Istanbul'));
        $o=$this->dispatchOrder();
        $draft=app(\App\Services\Invoices\InvoicePreparationService::class)->prepare($o,null);
        $r=\App\Services\Invoices\EdmOrderDraftPayload::fields($draft,'2026-11-27',file_get_contents($path),$o);
        $xml=base64_decode($r['INVOICE']['CONTENT'],true);
        $this->assertStringContainsString($o->order_number,$xml);
        $this->assertStringContainsString('Private personalized label',$xml);
        $this->assertStringContainsString('Sipariş alıcısı: Private Buyer',$xml);
        $this->assertStringNotContainsString('private@example.test',$xml);
        \App\Services\Invoices\EdmOrderDocuments::assertAmounts($xml,$draft);
    }

    public function test_order_documents_and_explicit_send_with_pinned_template(): void
    {
        $path=getenv('NEWTIC_EDM_SECOND_XSLT');
        if (!$path) $this->markTestSkipped('Pinned official template required.');
        $f=\App\Services\Invoices\EdmSandboxSmokeFixture::class;
        config(['app.url'=>$f::STAGING_URL]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 12:00:00','Europe/Istanbul'));
        $a=$this->account();$o=$this->dispatchOrder();
        $draft=app(\App\Services\Invoices\InvoicePreparationService::class)->prepare($o,null);
        $attempt=$this->claim($draft,$a);
        $number='A012026000000304';
        $attempt->forceFill(['state'=>'provider_record_found','last_load_result'=>'draft_acknowledged','observed_invoice_number'=>$number])->save();
        $r=\App\Services\Invoices\EdmOrderDraftPayload::fields($draft,'2026-11-27',file_get_contents($path),$o);
        $xml=base64_decode($r['INVOICE']['CONTENT'],true);
        $doc=new \DOMDocument();$doc->loadXML($xml,LIBXML_NONET);
        $xp=new \DOMXPath($doc);$xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->query('/i:Invoice/cbc:ID')->item(0)->textContent=$number;
        $xml=$doc->saveXML();$pdf="%PDF-1.4\nmock only\n%%EOF\n";$sent=false;
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>function($request)use($f,$attempt,$number,$xml,$pdf,&$sent){
            $method=$request->header('SOAPAction')[0];
            if ($method==='GetInvoiceRequest') {
                $this->assertStringNotContainsString('<ISARCHIVED>true</ISARCHIVED>',$request->body());
                $this->assertStringContainsString('<UUID>'.$attempt->uuid.'</UUID>',$request->body());
                if (str_contains($request->body(),'<DIRECTION>OUT</DIRECTION>')) return Http::response($this->soap('GetInvoice',''));
                $content=str_contains($request->body(),'<INVOICE_CONTENT_TYPE>PDF</INVOICE_CONTENT_TYPE>')?$pdf:$xml;
                return Http::response($this->soap('GetInvoice','<INVOICE UUID="'.$attempt->uuid.'" ID="'.$number.'"><HEADER><SENDER>'.$f::IDENTIFIER.'</SENDER><RECEIVER>'.$f::IDENTIFIER.'</RECEIVER><PROFILEID>TEMELFATURA</PROFILEID></HEADER><CONTENT>'.base64_encode($content).'</CONTENT></INVOICE>'));
            }
            if ($method==='SendInvoiceRequest') {
                $this->assertDatabaseHas('edm_order_send_requests',['attempt_id'=>$attempt->id,'state'=>'transport_started']);
                $this->assertSame(0,DB::transactionLevel());
                $this->assertStringContainsString('UUID="'.$attempt->uuid.'"',$request->body());
                $this->assertStringNotContainsString('<CONTENT>',$request->body());
                $sent=true;
            }
            $body=match($method){
                'LoginRequest'=>'<SESSION_ID>fixture-session</SESSION_ID>',
                'LogoutRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>',
                'GetInvoiceStatusRequest'=>'<INVOICE_STATUS UUID="'.$attempt->uuid.'" ID="'.$number.'"><HEADER><EARCHIVE>false</EARCHIVE></HEADER><STATUS>'.($sent?'PACKAGE - PROCESSING':'LOAD - SUCCEED').'</STATUS><DIRECTION>OUT</DIRECTION></INVOICE_STATUS>',
                'SendInvoiceRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE UUID="'.$attempt->uuid.'" ID="'.$number.'"/>',
                default=>throw new \RuntimeException('Unexpected method'),
            };
            return Http::response($this->soap(substr($method,0,-7),$body));
        }]);
        $admin=\App\Models\User::factory()->create();$admin->forceFill(['is_admin'=>true])->save();
        $this->actingAs($admin)->post(route('admin.invoices.documents.refresh',['attempt'=>$attempt->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('edm_order_documents',2);
        $this->get(route('admin.invoices.documents.download',['attempt'=>$attempt->id,'format'=>'xml']))->assertOk()->assertSee($number);
        $this->assertSame($pdf,$this->get(route('admin.invoices.documents.download',['attempt'=>$attempt->id,'format'=>'pdf']))->assertOk()->getContent());
        $url=route('admin.invoices.send',['attempt'=>$attempt->id]);
        $this->post($url,[])->assertSessionHasErrors('confirm_send');
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','SendInvoiceRequest'));
        $this->post($url,['confirm_send'=>1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('edm_order_send_requests',['attempt_id'=>$attempt->id,'state'=>'accepted_for_processing']);
        $this->assertSame('packaging',$attempt->fresh()->last_portal_state);
        $this->assertNull($attempt->fresh()->last_gib_code);
        $this->post($url,['confirm_send'=>1])->assertSessionHasErrors('invoice');
        $this->assertSame(1,Http::recorded(fn($r)=>$r->hasHeader('SOAPAction','SendInvoiceRequest'))->count());
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest'));
    }

}
