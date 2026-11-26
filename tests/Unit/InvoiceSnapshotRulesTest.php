<?php

namespace Tests\Unit;

use App\Services\Invoices\InvoiceSnapshotRules;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceSnapshotRulesTest extends TestCase
{
    private static function snapshot(): array
    {
        $first=['label'=>'Test product','quantity'=>3,'unit_gross_cents'=>9990,'kind'=>'product','rate'=>20,'gross_cents'=>29970,'net_cents'=>24975,'tax_cents'=>4995];
        $second=['label'=>'Test product 2','quantity'=>1,'unit_gross_cents'=>1100,'kind'=>'product','rate'=>10,'gross_cents'=>1100,'net_cents'=>1000,'tax_cents'=>100];
        return ['complete'=>true,'lines'=>[$first,$second],'groups'=>[
            ['rate'=>10,'gross_cents'=>1100,'net_cents'=>1000,'tax_cents'=>100],
            ['rate'=>20,'gross_cents'=>29970,'net_cents'=>24975,'tax_cents'=>4995],
        ],'gross_cents'=>31070,'net_cents'=>25975,'tax_cents'=>5095];
    }

    public function test_mixed_valid_snapshot(): void
    {
        InvoiceSnapshotRules::validate(self::snapshot());
        $this->addToAssertionCount(1);
    }

    public static function invalidCases(): iterable
    {
        foreach (['line_total','line_rate','quantity','duplicate_group','missing_group','group_tax','total','empty_lines','bad_currency_precision','invalid_label'] as $kind) yield $kind=>[$kind];
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_snapshots_are_rejected(string $kind): void
    {
        $a=self::snapshot();
        switch ($kind) {
            case 'line_total': $a['lines'][0]['gross_cents']++; break;
            case 'line_rate': $a['lines'][0]['rate']=18; break;
            case 'quantity': $a['lines'][0]['quantity']=0; break;
            case 'duplicate_group': $a['groups'][1]=$a['groups'][0]; break;
            case 'missing_group': array_pop($a['groups']); break;
            case 'group_tax': $a['groups'][0]['tax_cents']--; break;
            case 'total': $a['gross_cents']++; break;
            case 'empty_lines': $a['lines']=[]; break;
            case 'bad_currency_precision': $a['lines'][0]['unit_gross_cents']=99.9; break;
            case 'invalid_label': $a['lines'][0]['label']="test\x01"; break;
        }
        $this->expectException(DomainException::class);
        InvoiceSnapshotRules::validate($a);
    }
}
