<?php

namespace Tests\Feature;

use App\Models\EdmSetting;
use App\Models\InvoicePreparation;
use App\Models\Order;
use App\Services\Invoices\EdmSubmissionJournal;
use App\Services\Invoices\EdmTestClient;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EdmSubmissionJournalTest extends TestCase
{
    use DatabaseMigrations;

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
}
