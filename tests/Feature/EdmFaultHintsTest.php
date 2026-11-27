<?php

namespace Tests\Feature;

use App\Services\Invoices\EdmTestClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EdmFaultHintsTest extends TestCase
{
    private function envelope(string $method,string $body): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><edm:'.$method.'Response xmlns:edm="http://tempuri.org/">'.$body.'</edm:'.$method.'Response></s:Body></s:Envelope>';
    }

    private function queryFault(string $details): array
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->envelope('Login','<SESSION_ID>secret-session-value</SESSION_ID>'))
            ->push('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><s:Fault><faultcode>s:Server</faultcode><faultstring>private outer fault</faultstring><detail><REQUEST_ERROR><ERROR_CODE>11017</ERROR_CODE>'.$details.'</REQUEST_ERROR></detail></s:Fault></s:Body></s:Envelope>',500)
            ->push($this->envelope('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
        return (new EdmTestClient)->invoiceStatus('fixture','secret','6dbdcb87-3d49-4c65-8d36-073a13c644aa','TEMELFATURA');
    }

    public function test_documented_fault_fields_produce_only_fixed_hints(): void
    {
        $r=$this->queryFault('<ERROR_SHORT_DES>UBL XML schema failed</ERROR_SHORT_DES><ERROR_LONG_DES>ExtensionContent signature; session secret-session-value; private@example.test</ERROR_LONG_DES>');
        $this->assertSame('Lookup: EDM_11017',$r['diagnostic']);
        $this->assertSame(['schema_or_xml','signature'],$r['fault_hints']);
        $this->assertNull($r['data']);
        $this->assertStringNotContainsString('secret-session-value',json_encode($r));
        $this->assertStringNotContainsString('private@example.test',json_encode($r));
        $this->assertStringNotContainsString('private outer fault',json_encode($r));
        Http::assertSentCount(3);
    }

    public function test_missing_description_is_not_invented(): void
    {
        $r=$this->queryFault('');
        $this->assertSame(['description_unavailable'],$r['fault_hints']);
        $this->assertSame('Lookup: EDM_11017',$r['diagnostic']);
    }

    public function test_unknown_description_is_not_exposed(): void
    {
        $r=$this->queryFault('<ERROR_LONG_DES>private-value-1234</ERROR_LONG_DES>');
        $this->assertSame(['description_unclassified'],$r['fault_hints']);
        $this->assertStringNotContainsString('private-value-1234',json_encode($r));
    }

    public function test_stored_fault_classifier_never_returns_provider_text(): void
    {
        $c=\App\Services\Invoices\EdmStoredFault::class;
        $this->assertSame(['dotnet_null_reference','provider_generic_load_failure'], $c::classify(['ERROR_LONG_DES'=>'Fatura yüklenemedi (Object reference not set to an instance of an object.) private@example.test']));
        $this->assertSame(['description_unclassified'],$c::classify(['ERROR_SHORT_DES'=>'arbitrary-secret-value']));
        $this->assertSame(['description_unavailable'],$c::classify([]));
        $this->assertContains('nested_EDM_11029',$c::classify(['ERROR_LONG_DES'=>'11029']));
    }
}
