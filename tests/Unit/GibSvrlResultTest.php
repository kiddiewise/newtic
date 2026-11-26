<?php

namespace Tests\Unit;

use App\Services\Invoices\GibSvrlResult;
use DomainException;
use PHPUnit\Framework\TestCase;

final class GibSvrlResultTest extends TestCase
{
    private function report(string $contents): string
    {
        return '<svrl:schematron-output xmlns:svrl="http://purl.oclc.org/dsdl/svrl">'.$contents.'</svrl:schematron-output>';
    }

    public function test_no_findings_is_not_official_approval(): void
    {
        $result=GibSvrlResult::inspect($this->report('<svrl:active-pattern id="fixture"/><svrl:fired-rule context="Invoice"/>'));
        $this->assertTrue($result['no_findings']);
        $this->assertFalse($result['official_validation_confirmed']);
    }

    public function test_empty_report_and_any_finding_block(): void
    {
        foreach (['','<svrl:failed-assert><svrl:text>private value</svrl:text></svrl:failed-assert>',
            '<svrl:successful-report><svrl:text>private value</svrl:text></svrl:successful-report>'] as $i=>$finding) {
            $body=$i===0?'':'<svrl:active-pattern/><svrl:fired-rule/>'.$finding;
            $result=GibSvrlResult::inspect($this->report($body));
            $this->assertFalse($result['no_findings']);
            $this->assertStringNotContainsString('private value',json_encode($result));
        }
    }

    public function test_wrong_namespace_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        GibSvrlResult::inspect('<schematron-output><fired-rule/></schematron-output>');
    }
}
