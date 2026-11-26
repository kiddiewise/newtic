<?php

namespace Tests\Feature;

use App\Models\EdmSetting;
use App\Services\Invoices\EdmDraftRoutingCheck;
use App\Services\Invoices\EdmTestClient;
use App\Services\Invoices\UblSaleCandidate;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EdmDraftRoutingCheckTest extends TestCase
{
    use RefreshDatabase;

    private function account(): EdmSetting
    {
        $account=EdmSetting::query()->firstOrCreate(['id'=>1]);
        $account->forceFill(['username'=>'fixture','password'=>'secret','revision'=>(string)Str::uuid(),'probe_status'=>'ok'])->save();
        return $account;
    }

    private function soap(string $method,string $body): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><edm:'.$method.'Response xmlns:edm="http://tempuri.org/">'.$body.'</edm:'.$method.'Response></s:Body></s:Envelope>';
    }

    private function serial(string $code='INV',string $year='2026',string $active='1',string $archive='0',string $internet='0'): string
    {
        return '<SERIALLIST><SERIAL>'.$code.'</SERIAL><YEAR>'.$year.'</YEAR><ACTIVEFLAG>'.$active.'</ACTIVEFLAG><EARCHIVEFLAG>'.$archive.'</EARCHIVEFLAG><INTERNETFLAG>'.$internet.'</INTERNETFLAG></SERIALLIST>';
    }

    private function fake(string $series,string $recipient='<USER><IDENTIFIER>1234567891</IDENTIFIER><UNIT>PK</UNIT><ALIAS>urn:mail:buyer@example.test</ALIAS></USER>',string $gb='urn:mail:sender@example.test'): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>function ($request) use ($series,$recipient,$gb) {
            $method=$request->header('SOAPAction')[0];
            $body=match ($method) {
                'LoginRequest'=>'<SESSION_ID>fixture-session</SESSION_ID>',
                'GetCompanyRequest'=>'<GetCompanyList><VKN>1234567890</VKN><UNVAN>Fixture</UNVAN><GB>'.$gb.'</GB>'.$series.'</GetCompanyList>',
                'CheckUserRequest'=>$recipient,
                'LogoutRequest'=>'<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>',
                default=>throw new \RuntimeException('Unexpected SOAP mutation'),
            };
            return Http::response($this->soap(substr($method,0,-7),$body));
        }]);
    }

    private function verify(EdmSetting $account,string $id=UblSaleCandidate::EDM_SERIAL_SENTINEL,string $profile='TEMELFATURA'): void
    {
        (new EdmDraftRoutingCheck(new EdmTestClient))->verify($account,'1234567890','1234567891',
            'urn:mail:sender@example.test','urn:mail:buyer@example.test',$profile,'2026-11-26',$id);
    }

    public function test_matching_company_and_recipient_with_unique_series_only_reads(): void
    {
        $account=$this->account();
        $this->fake($this->serial().$this->serial('ARC',archive:'1',internet:'1').$this->serial('OLD',year:'2025'));
        $this->verify($account);
        Http::assertSentCount(6);
        Http::assertNotSent(fn($r)=>in_array($r->header('SOAPAction')[0],['GetInvoiceSerialRequest','LoadInvoiceRequest','SendInvoiceRequest'],true));
    }

    public function test_ambiguous_default_series_stops_before_recipient_lookup(): void
    {
        $account=$this->account();
        $this->fake($this->serial().$this->serial('XYZ'));
        try { $this->verify($account); $this->fail('Ambiguous series accepted'); }
        catch (DomainException $e) { $this->assertStringContainsString('serisi belirsiz',$e->getMessage()); }
        Http::assertSentCount(3);
    }

    public function test_explicit_invoice_series_is_checked_without_allocating_number(): void
    {
        $account=$this->account();
        $this->fake($this->serial().$this->serial('XYZ'));
        $this->verify($account,'XYZ2026000000001');
        Http::assertSentCount(6);
    }

    public function test_wrong_sender_alias_blocks_routing(): void
    {
        $account=$this->account();
        $this->fake($this->serial(),gb:'urn:mail:other@example.test');
        $this->expectException(DomainException::class);
        $this->verify($account);
    }

    public function test_empty_recipient_result_is_not_archive_authorization(): void
    {
        $account=$this->account();
        $this->fake($this->serial(),recipient:'');
        try { $this->verify($account); $this->fail('Empty recipient accepted'); }
        catch (DomainException $e) { $this->assertStringContainsString('otomatik değiştirilemez',$e->getMessage()); }
        Http::assertSentCount(6);
    }

    public function test_rotated_account_and_archive_are_blocked_before_http(): void
    {
        Http::fake();
        $old=$this->account();
        $current=$this->account();
        foreach ([[$old,'TEMELFATURA'],[$current,'EARSIVFATURA']] as [$account,$profile]) {
            try { $this->verify($account,profile:$profile); $this->fail('Unexpected authorization'); }
            catch (DomainException) { $this->assertTrue(true); }
        }
        Http::assertNothingSent();
    }
}
