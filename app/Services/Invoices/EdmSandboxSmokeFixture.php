<?php

namespace App\Services\Invoices;

use App\Models\InvoicePreparation;
use DomainException;

/**
 * One intentionally synthetic, unnumbered EDM sandbox smoke document.
 * No real order, customer, stock movement, payment or recipient email.
 * The fixed test ETTN may never be regenerated automatically.
 */
final class EdmSandboxSmokeFixture
{
    public const UUID='6dbdcb87-3d49-4c65-8d36-073a13c644aa';
    public const IDENTIFIER='3230512384';
    public const GB='urn:mail:defaultgb@edmbilisim.com.tr';
    public const PK='urn:mail:defaultpk@edmbilisim.com.tr';
    public const SERIES='A01';
    public const PROFILE='TEMELFATURA';
    public const CONFIRM='LOAD-ONE-UNNUMBERED-TEST-DRAFT';
    public const STAGING_URL='https://powderblue-dinosaur-538060.hostingersite.com';

    public static function xml(string $date): string
    {
        if (!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$date)) throw new DomainException('TEST_DATE_INVALID');
        $line=['label'=>'NEWTIC EDM entegrasyon testi','quantity'=>1,'unit_gross_cents'=>120,
            'kind'=>'product','gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,'rate'=>20];
        $snapshot=['complete'=>true,'gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,
            'groups'=>[['gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,'rate'=>20]],'lines'=>[$line]];
        $draft=new InvoicePreparation(['uuid'=>self::UUID,'gross_cents'=>120,
            'snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'amounts'=>$snapshot]);
        // Provider test identity supplied by merchant. Address is explicitly
        // synthetic; no assertion about the provider's actual street address.
        $party=['identifier'=>self::IDENTIFIER,'name'=>'EDM Bilişim Sistemleri ve Danışmanlık Hizmetleri A.Ş.',
            'address'=>'NEWTIC entegrasyon test adresi','district'=>'Esenler','city'=>'İstanbul','tax_office'=>'Esenler'];
        return (new UblSaleCandidate(new InvoicePreparationXml))->rehearseEdmDraft(
            $draft,$party,$party,self::PROFILE,$date,'NEWTIC-EDM-SMOKE-01',$date)['xml'];
    }

    public static function fields(string $date): array
    {
        $fields=EdmSandboxLoadPayload::fields(self::xml($date),self::UUID,self::PROFILE,self::GB,self::PK);
        // v4.2.1 §4.2: INVOICESERIAL_REQUESTED follows EARCHIVE and
        // INTERNETSALESDETAILS; this ordinary sale has no internet details.
        $header=$fields['INVOICE']['HEADER'];
        $fields['INVOICE']['HEADER']=array_slice($header,0,6,true)+['INVOICESERIAL_REQUESTED'=>self::SERIES]+array_slice($header,6,null,true);
        // No actual fiscal number allocation in the first draft test.
        if ($fields['GENERATEINVOICEIDONLOAD']!=='false' || $fields['INVOICE']['ID']!==UblSaleCandidate::EDM_SERIAL_SENTINEL) {
            throw new DomainException('TEST_SERIAL_POLICY_INVALID');
        }
        return $fields;
    }
}
