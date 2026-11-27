<?php

namespace Tests\Unit;

use App\Services\Invoices\EdmNumberedDraftAck;
use App\Services\Invoices\EdmSecondSandboxFixture;
use DOMDocument;
use DomainException;
use PHPUnit\Framework\TestCase;

final class EdmNumberedDraftAckTest extends TestCase
{
    private function reply(string $body): \DOMElement
    {
        $d=new DOMDocument();
        $d->loadXML('<LoadInvoiceResponse>'.$body.'</LoadInvoiceResponse>',LIBXML_NONET);
        return $d->documentElement;
    }

    public function test_numbered_ack_requires_exact_uuid_and_selected_series_year(): void
    {
        $u=EdmSecondSandboxFixture::UUID;
        $r=EdmNumberedDraftAck::parse($this->reply('<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE UUID="'.$u.'" ID="A012026000000001"/>'),$u,'A01',2026);
        $this->assertSame('A012026000000001',$r['allocated_invoice_id']);
        $this->assertTrue($r['draft_acknowledged']);
        $this->assertFalse($r['fiscal_success_confirmed']);
    }

    public function test_rejects_sentinel_wrong_year_wrong_series_and_duplicate_response(): void
    {
        $u=EdmSecondSandboxFixture::UUID;
        foreach (['ABC2009123456789','A012025000000001','EFA2026000000001','A012026000000000'] as $id) {
            try {
                EdmNumberedDraftAck::parse($this->reply('<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE UUID="'.$u.'" ID="'.$id.'"/>'),$u,'A01',2026);
                $this->fail('Incorrect allocated number accepted');
            } catch (DomainException $e) {
                $this->assertSame('SECOND_ACK_ID_OR_UUID',$e->getMessage());
            }
        }
        $this->expectException(DomainException::class);
        EdmNumberedDraftAck::parse($this->reply('<REQUEST_RETURN><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE UUID="'.$u.'" ID="A012026000000001"/><INVOICE UUID="'.$u.'" ID="A012026000000002"/>'),$u,'A01',2026);
    }
}
