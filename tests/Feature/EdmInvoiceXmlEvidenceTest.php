<?php

namespace Tests\Feature;

use App\Services\Invoices\EdmTestClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EdmInvoiceXmlEvidenceTest extends TestCase
{
    private const UUID='a268a924-0255-4da6-9b47-94383421cb48';
    private const ID='INV2026000000001';
    private const SELLER='1234567890';
    private const BUYER='1234567891';

    private function soap(string $method,string $body): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><edm:'.$method.'Response xmlns:edm="http://tempuri.org/">'.$body.'</edm:'.$method.'Response></s:Body></s:Envelope>';
    }

    private function xml(string $uuid=self::UUID,string $profile='TEMELFATURA'): string
    {
        // Synthetic structure, not a valid signed fiscal invoice.
        return '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">'
            .'<cbc:UUID>'.$uuid.'</cbc:UUID><cbc:ID>'.self::ID.'</cbc:ID><cbc:ProfileID>'.$profile.'</cbc:ProfileID>'
            .'<cac:AccountingSupplierParty><cac:Party><cac:PartyIdentification><cbc:ID>'.self::SELLER.'</cbc:ID></cac:PartyIdentification></cac:Party></cac:AccountingSupplierParty>'
            .'<cac:AccountingCustomerParty><cac:Party><cac:PartyIdentification><cbc:ID>'.self::BUYER.'</cbc:ID></cac:PartyIdentification></cac:Party></cac:AccountingCustomerParty>'
            .'<cbc:Note>private customer note</cbc:Note><ds:Signature/></Invoice>';
    }

    private function row(string $content,string $uuid=self::UUID,string $profile='TEMELFATURA'): string
    {
        return '<INVOICE TRXID="0" UUID="'.$uuid.'" ID="'.self::ID.'"><HEADER><SENDER>'.self::SELLER.'</SENDER><RECEIVER>'.self::BUYER.'</RECEIVER><PROFILEID>'.$profile.'</PROFILEID></HEADER><CONTENT>'.$content.'</CONTENT></INVOICE>';
    }

    private function fake(string $body): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->soap('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push($this->soap('GetInvoice',$body))
            ->push($this->soap('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
    }

    private function fetchEvidence(string $profile='TEMELFATURA'): array
    {
        return (new EdmTestClient)->invoiceXmlEvidence('fixture','secret',self::UUID,$profile,self::SELLER,self::BUYER);
    }

    public function test_outgoing_xml_evidence_matches_exact_document_without_returning_content(): void
    {
        $xml=$this->xml();
        $this->fake($this->row(base64_encode($xml)));
        $r=$this->fetchEvidence();
        $this->assertSame('ok',$r['status']);
        $this->assertSame(hash('sha256',$xml),$r['data']['xml_sha256']);
        $this->assertSame(strlen($xml),$r['data']['xml_bytes']);
        $this->assertTrue($r['data']['signature_element_present']);
        $this->assertFalse($r['data']['signature_verified']);
        $this->assertFalse($r['data']['fiscal_success_confirmed']);
        $this->assertStringNotContainsString('private customer note',json_encode($r));
        $this->assertStringNotContainsString(base64_encode($xml),json_encode($r));
        Http::assertSentCount(3);
        Http::assertSent(fn($req)=>$req->hasHeader('SOAPAction','GetInvoiceRequest')
            && str_contains($req->body(),'<UUID>'.self::UUID.'</UUID>')
            && str_contains($req->body(),'<DIRECTION>OUT-EINVOICE</DIRECTION>')
            && str_contains($req->body(),'<HEADER_ONLY>N</HEADER_ONLY>')
            && str_contains($req->body(),'<INVOICE_CONTENT_TYPE>XML</INVOICE_CONTENT_TYPE>'));
    }

    public function test_archive_query_uses_distinct_direction_and_profile(): void
    {
        $this->fake($this->row(base64_encode($this->xml(profile:'EARSIVFATURA')),profile:'EARSIVFATURA'));
        $this->assertSame('ok',$this->fetchEvidence('EARSIVFATURA')['status']);
        Http::assertSent(fn($req)=>$req->hasHeader('SOAPAction','GetInvoiceRequest') && str_contains($req->body(),'<DIRECTION>OUT-EARCHIVE</DIRECTION>'));
    }

    public function test_inner_uuid_mismatch_is_rejected(): void
    {
        $this->fake($this->row(base64_encode($this->xml('a268a924-0255-4da6-9b47-94383421cb49'))));
        $this->assertSame('Lookup: GETINVOICE_UBL_IDENTITY_MISMATCH',$this->fetchEvidence()['diagnostic']);
        Http::assertSentCount(3);
    }

    public function test_duplicate_results_are_rejected(): void
    {
        $row=$this->row(base64_encode($this->xml()));
        $this->fake($row.$row);
        $this->assertSame('Lookup: GETINVOICE_FIELD_INVOICE',$this->fetchEvidence()['diagnostic']);
    }

    public function test_invalid_base64_is_rejected(): void
    {
        $this->fake($this->row('not!base64'));
        $this->assertSame('Lookup: GETINVOICE_CONTENT_INVALID',$this->fetchEvidence()['diagnostic']);
    }

    public function test_unsafe_inner_xml_is_rejected(): void
    {
        $this->fake($this->row(base64_encode('<!DOCTYPE Invoice [<!ENTITY x SYSTEM "file:///etc/passwd">]><Invoice>&x;</Invoice>')));
        $this->assertSame('Lookup: GETINVOICE_CONTENT_INVALID',$this->fetchEvidence()['diagnostic']);
    }

    public function test_bad_input_never_calls_edm(): void
    {
        Http::fake();
        $r=(new EdmTestClient)->invoiceXmlEvidence('fixture','secret','invalid','TEMELFATURA',self::SELLER,self::BUYER);
        $this->assertSame('failed',$r['status']);
        Http::assertNothingSent();
    }
}
