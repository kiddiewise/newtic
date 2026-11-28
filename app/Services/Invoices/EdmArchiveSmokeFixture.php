<?php
namespace App\Services\Invoices;

use App\Models\InvoicePreparation;
use DomainException;

/** Fixed, isolated synthetic archive experiment, never uses customer orders. */
final class EdmArchiveSmokeFixture
{
    public const UUID='9d21aafb-34e8-43b0-a11c-6f9bf16cd260';
    public const EMAIL='newtic-archive-test@example.invalid';
    public const CONFIRM='LOAD-ONE-SYNTHETIC-ARCHIVE-DRAFT';

    public static function prepare(string $date,string $series,string $website): array
    {
        if ($website!==EdmSandboxSmokeFixture::STAGING_URL || !preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$date))
            throw new DomainException('ARCHIVE_FIXTURE_SCOPE');
        $line=['label'=>'NEWTIC e-Arşiv sentetik test ürünü','quantity'=>1,'unit_gross_cents'=>120,
            'kind'=>'product','gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,'rate'=>20];
        $snapshot=['complete'=>true,'gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,
            'groups'=>[['gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,'rate'=>20]],'lines'=>[$line]];
        $draft=new InvoicePreparation(['uuid'=>self::UUID,'gross_cents'=>120,'amounts'=>$snapshot,
            'snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))]);
        $seller=['identifier'=>EdmSandboxSmokeFixture::IDENTIFIER,'name'=>'EDM Bilişim Sistemleri ve Danışmanlık Hizmetleri A.Ş.',
            'address'=>'NEWTIC sentetik test adresi','district'=>'Esenler','city'=>'İstanbul','tax_office'=>'Esenler'];
        $buyer=['identifier'=>'11111111111','first_name'=>'Test','last_name'=>'Alıcı','address'=>'Sentetik test adresi',
            'district'=>'Esenler','city'=>'İstanbul','email'=>self::EMAIL];
        $candidate=(new UblSaleCandidate(new InvoicePreparationXml))->rehearseArchiveDraft($draft,$seller,$buyer,$date,$website,$series);
        $details=$candidate['header']['INTERNETSALESDETAILS'];
        // WCF serializes the *Specified companion locally; it is not a SOAP element.
        unset($details['odemeTarihiSpecified']);
        $fields=['INVOICE'=>['UUID'=>self::UUID,'ID'=>UblSaleCandidate::EDM_SERIAL_SENTINEL,'HEADER'=>[
            'SENDER'=>EdmSandboxSmokeFixture::IDENTIFIER,'RECEIVER'=>'11111111111',
            'FROM'=>EdmSandboxSmokeFixture::GB,'TO'=>self::EMAIL,
            'INTERNETSALES'=>'true','EARCHIVE'=>'true','INTERNETSALESDETAILS'=>$details,
            'INVOICESERIAL_REQUESTED'=>$series,
            'EARCHIVE_REPORT_SENDDATE'=>'0001-01-01','CANCEL_EARCHIVE_REPORT_SENDDATE'=>'0001-01-01',
        ],'CONTENT'=>base64_encode($candidate['xml'])],
            'SENDER'=>['vkn'=>EdmSandboxSmokeFixture::IDENTIFIER,'alias'=>EdmSandboxSmokeFixture::GB],
            'RECEIVER'=>['vkn'=>'11111111111','alias'=>self::EMAIL],'GENERATEINVOICEIDONLOAD'=>'true'];
        return ['xml'=>$candidate['xml'],'fields'=>$fields,'xml_sha256'=>hash('sha256',$candidate['xml']),
            'request_sha256'=>hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR))];
    }
}
