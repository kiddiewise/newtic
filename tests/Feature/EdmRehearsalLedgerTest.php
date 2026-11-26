<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\InvoicePreparation;
use App\Services\Invoices\EdmRehearsalLedger;
use App\Services\Invoices\EdmInvoiceOutcome;
use App\Services\Invoices\UblSaleCandidate;
use App\Services\Invoices\InvoicePreparationXml;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EdmRehearsalLedgerTest extends TestCase
{
    use RefreshDatabase;

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

    private function reserve(InvoicePreparation $draft,string $alias='urn:mail:buyer@example.test')
    {
        $party=['identifier'=>'1234567890','name'=>'Test AŞ','address'=>'Örnek','district'=>'Örnek','city'=>'İzmir','tax_office'=>'Örnek'];
        $xml=(new UblSaleCandidate(new InvoicePreparationXml))->render($draft,$party,$party,'TEMELFATURA','2026-11-26','TEST-1','2026-11-25');
        return (new EdmRehearsalLedger)->reserve($draft->id,$xml,'TEMELFATURA','urn:mail:sender@example.test',$alias,new \DateTimeImmutable('2026-11-26'));
    }

    public function test_same_rehearsal_is_reserved_once_and_different_routing_rejected(): void
    {
        Http::fake();
        $draft=$this->draft();
        $first=$this->reserve($draft);
        $this->assertSame($first->id,$this->reserve($draft)->id);
        $this->assertSame('offline_reserved',$first->state);
        $this->assertDatabaseCount('edm_invoice_rehearsals',1);
        $this->assertArrayNotHasKey('routing_digest',$first->toArray());
        try {
            $this->reserve($draft,'urn:mail:other@example.test');
            $this->fail('Changed routing accepted');
        } catch (DomainException $e) {
            $this->assertStringContainsString('farklı prova',$e->getMessage());
        }
        $this->assertDatabaseCount('edm_invoice_rehearsals',1);
        Http::assertNothingSent();
    }

    public function test_live_order_cannot_create_offline_rehearsal(): void
    {
        $this->expectException(DomainException::class);
        $this->reserve($this->draft(false));
    }

    public function test_outcome_namespaces_and_no_automatic_retry(): void
    {
        foreach ([
            ['edm','11030','validation'],['edm','11056','duplicate'],['gib','1163','duplicate'],
            ['gib','1220','pending'],['gib','1300','envelope_completed'],['gib','1230','delivery_failed'],
            ['edm','1300','unknown'],['gib','11030','unknown'],['transport','timeout','unknown'],
        ] as [$source,$code,$state]) {
            $result=EdmInvoiceOutcome::classify($source,$code);
            $this->assertSame($state,$result['state']);
            $this->assertFalse($result['automatic_retry']);
        }
        $this->assertNull(EdmInvoiceOutcome::classify('edm','raw private provider fault')['code']);
    }
}
