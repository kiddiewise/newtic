<?php

namespace Tests\Feature;

use App\Services\Invoices\EdmTestClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EdmInvoiceStatusTest extends TestCase
{
    private const UUID='a268a924-0255-4da6-9b47-94383421cb48';

    private function reply(string $method,string $content): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><edm:'.$method.'Response xmlns:edm="http://tempuri.org/">'.$content.'</edm:'.$method.'Response></s:Body></s:Envelope>';
    }

    private function row(string $uuid=self::UUID,string $direction='Giden',string $archive='false',string $extra=''): string
    {
        return '<INVOICE_STATUS TRXID="0" UUID="'.$uuid.'" ID="EE12018000000013"><HEADER><EARCHIVE>'.$archive.'</EARCHIVE></HEADER><STATUS>SEND - SUCCEED</STATUS><STATUS_DESCRIPTION>private provider description</STATUS_DESCRIPTION>'.$extra.'<DIRECTION>'.$direction.'</DIRECTION></INVOICE_STATUS>';
    }

    private function setupReply(string $content): void
    {
        Http::preventStrayRequests();
        Http::fake([EdmTestClient::ENDPOINT=>Http::sequence()
            ->push($this->reply('Login','<SESSION_ID>fixture-session</SESSION_ID>'))
            ->push($this->reply('GetInvoiceStatus',$content))
            ->push($this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'))]);
    }

    public function test_exact_uuid_status_keeps_commercial_and_gib_separate(): void
    {
        $this->setupReply($this->row(extra:'<GIB_STATUS_CODE>1300</GIB_STATUS_CODE><RESPONSE_CODE>ACCEPT</RESPONSE_CODE>'));
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'TICARIFATURA');
        $this->assertSame('ok',$result['status']);
        $this->assertSame('send_succeeded',$result['data']['portal_state']);
        $this->assertSame('envelope_completed',$result['data']['gib']['state']);
        $this->assertSame('accepted',$result['data']['commercial_response']);
        $this->assertSame('EE12018000000013',$result['data']['invoice_number']);
        $this->assertFalse($result['data']['automatic_retry']);
        $this->assertFalse($result['data']['fiscal_success_confirmed']);
        $this->assertStringNotContainsString('private provider description',json_encode($result));
        Http::assertSentCount(3);
        Http::assertSent(fn($r)=>$r->url()===EdmTestClient::ENDPOINT && $r->hasHeader('SOAPAction','GetInvoiceStatusRequest')
            && str_contains($r->body(),'<INVOICE TRXID="0" UUID="'.self::UUID.'"/>'));
        Http::assertNotSent(fn($r)=>$r->hasHeader('SOAPAction','LoadInvoiceRequest') || $r->hasHeader('SOAPAction','SendInvoiceRequest'));
    }

    public function test_portal_success_without_gib_code_remains_unconfirmed(): void
    {
        $this->setupReply($this->row());
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'TEMELFATURA');
        $this->assertSame('ok',$result['status']);
        $this->assertSame('unknown',$result['data']['gib']['state']);
        $this->assertFalse($result['data']['fiscal_success_confirmed']);
    }

    public function test_wrong_uuid_is_not_accepted_and_session_is_closed(): void
    {
        $this->setupReply($this->row('a268a924-0255-4da6-9b47-94383421cb49'));
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'TEMELFATURA');
        $this->assertSame('failed',$result['status']);
        $this->assertSame('Lookup: STATUS_UUID_MISMATCH',$result['diagnostic']);
        $this->assertNull($result['data']);
        $this->assertFalse($result['outcome']['automatic_retry']);
        Http::assertSentCount(3);
    }

    public function test_duplicate_rows_are_ambiguous(): void
    {
        $this->setupReply($this->row().$this->row());
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'TEMELFATURA');
        $this->assertSame('Lookup: STATUS_AMBIGUOUS',$result['diagnostic']);
        $this->assertNull($result['data']);
    }

    public function test_archive_mismatch_is_rejected(): void
    {
        $this->setupReply($this->row(archive:'true'));
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'TEMELFATURA');
        $this->assertSame('Lookup: STATUS_ARCHIVE_MISMATCH',$result['diagnostic']);
    }

    public function test_archive_report_is_not_inferred(): void
    {
        $this->setupReply($this->row(archive:'true',extra:'<EARCHIVE_REPORT_STATUS>opaque</EARCHIVE_REPORT_STATUS>'));
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'EARSIVFATURA');
        $this->assertSame('ok',$result['status']);
        $this->assertSame('uninterpreted',$result['data']['archive_report']);
        $this->assertFalse($result['data']['fiscal_success_confirmed']);
    }

    public function test_unknown_or_empty_status_does_not_authorize_retry(): void
    {
        $this->setupReply('');
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'TEMELFATURA');
        $this->assertSame('Lookup: STATUS_EMPTY',$result['diagnostic']);
        $this->assertFalse($result['outcome']['automatic_retry']);
    }

    public function test_invalid_uuid_never_connects(): void
    {
        Http::fake();
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret','invalid','TEMELFATURA');
        $this->assertSame('failed',$result['status']);
        Http::assertNothingSent();
    }

    public function test_connection_failure_does_not_retry_and_still_logs_out(): void
    {
        Http::preventStrayRequests();
        $methods=[];
        Http::fake([EdmTestClient::ENDPOINT=>function ($request) use (&$methods) {
            $method=$request->header('SOAPAction')[0];
            $methods[]=$method;
            if ($method==='GetInvoiceStatusRequest') throw new ConnectionException('private connection details');
            return Http::response($method==='LoginRequest'
                ? $this->reply('Login','<SESSION_ID>fixture-session</SESSION_ID>')
                : $this->reply('Logout','<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN>'));
        }]);
        $result=(new EdmTestClient)->invoiceStatus('fixture','secret',self::UUID,'TEMELFATURA');
        $this->assertSame(['LoginRequest','GetInvoiceStatusRequest','LogoutRequest'],$methods);
        $this->assertSame('Lookup: NETWORK_TIMEOUT_OR_CONNECTION',$result['diagnostic']);
        $this->assertFalse($result['outcome']['automatic_retry']);
        $this->assertStringNotContainsString('private connection details',json_encode($result));
    }
}
