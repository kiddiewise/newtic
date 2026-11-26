<?php

namespace Tests\Unit;

use App\Models\InvoicePreparation;
use App\Services\Invoices\InvoicePreparationXml;
use App\Services\Invoices\UblSaleCandidate;
use App\Services\Invoices\EdmInternetSaleDetails;
use DateTimeImmutable;
use DomainException;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

final class UblSaleCandidateTest extends TestCase
{
    private function draft(): InvoicePreparation
    {
        $line=['label'=>'Test & ürün','quantity'=>3,'unit_gross_cents'=>9990,'kind'=>'product','gross_cents'=>29970,'net_cents'=>24975,'tax_cents'=>4995,'rate'=>20];
        $a=['complete'=>true,'gross_cents'=>29970,'net_cents'=>24975,'tax_cents'=>4995,'groups'=>[['gross_cents'=>29970,'net_cents'=>24975,'tax_cents'=>4995,'rate'=>20]],'lines'=>[$line]];
        return new InvoicePreparation(['uuid'=>'f4e3d2c1-1234-4abc-8def-123456789abc','gross_cents'=>29970,'snapshot_hash'=>hash('sha256',json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'amounts'=>$a]);
    }

    private function party(): array
    {
        return ['identifier'=>'1234567890','name'=>'Test & AŞ','address'=>'Örnek adres','district'=>'Örnek ilçe','city'=>'İzmir','tax_office'=>'Örnek'];
    }

    private function render(array $buyer,string $profile='TEMELFATURA'): string
    {
        return (new UblSaleCandidate(new InvoicePreparationXml))->render($this->draft(),$this->party(),$buyer,$profile,'2026-11-26','TEST-1','2026-11-25');
    }

    public function test_ordinary_sale_candidate_preserves_uuid_and_totals_and_escapes_text(): void
    {
        $xml=$this->render($this->party());
        $doc=new DOMDocument();
        $this->assertTrue($doc->loadXML($xml,LIBXML_NONET));
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        foreach ([
            '/i:Invoice/cbc:UBLVersionID'=>'2.1',
            '/i:Invoice/cbc:CustomizationID'=>'TR1.2',
            '/i:Invoice/cbc:ProfileID'=>'TEMELFATURA',
            '/i:Invoice/cbc:ID'=>UblSaleCandidate::EDM_SERIAL_SENTINEL,
            '/i:Invoice/cbc:UUID'=>$this->draft()->uuid,
            '/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount'=>'299.70',
            '/i:Invoice/cac:InvoiceLine/cac:Price/cbc:PriceAmount'=>'83.250000000',
            '/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name'=>'Test & AŞ',
        ] as $path=>$value) $this->assertSame($value,$xp->evaluate('string('.$path.')'));
        $this->assertStringContainsString('RESMÎ BELGE DEĞİLDİR',$xml);
        $this->assertStringContainsString('Test &amp; ürün',$xml);
        $this->assertStringNotContainsString('InvoicePreparation',$xml);
    }

    public function test_candidate_contains_required_turkish_xsd_structure_without_claiming_signature(): void
    {
        foreach (['TEMELFATURA','TICARIFATURA','EARSIVFATURA'] as $profile) {
            $xml=$profile==='EARSIVFATURA'
                ? $this->archive($this->party()+['email'=>'buyer@example.test'])['xml']
                : $this->render($this->party(),$profile);
            $doc=new DOMDocument();
            $this->assertTrue($doc->loadXML($xml,LIBXML_NONET));
            $xp=new DOMXPath($doc);
            $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
            $xp->registerNamespace('ext','urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
            $xp->registerNamespace('nwt','urn:newtic:unsigned-candidate:1');
            $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $this->assertSame('UBLExtensions',$doc->documentElement->firstElementChild->localName);
            $this->assertSame('NOT_FOR_SUBMISSION',$xp->evaluate('string(/i:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/nwt:UnsignedCandidate)'));
            $this->assertSame('#NEWTIC_UNSIGNED_TEST_ONLY',$xp->evaluate('string(/i:Invoice/cac:Signature/cac:DigitalSignatureAttachment/cac:ExternalReference/cbc:URI)'));
            $this->assertSame('1234567890',$xp->evaluate('string(/i:Invoice/cac:Signature/cac:SignatoryParty/cac:PartyIdentification/cbc:ID)'));
            $this->assertSame(1.0,$xp->evaluate('count(/i:Invoice/cac:Signature/following-sibling::cac:AccountingSupplierParty)'));
            $this->assertSame(0.0,$xp->evaluate('count(//*[namespace-uri()="http://www.w3.org/2000/09/xmldsig#" and local-name()="Signature"])'));
        }
    }

    /**
     * Optional integration test against the operator-supplied exact GIB package.
     * Set NEWTIC_GIB_XSD to its absolute Invoice schema path in a trusted local environment.
     * The regular CI suite cannot claim GIB schema validation without the package.
     */
    public function test_candidates_against_supplied_gib_xsd_when_configured(): void
    {
        $schema=getenv('NEWTIC_GIB_XSD');
        if (!$schema) $this->markTestSkipped('Operator-supplied GIB XSD package not configured.');
        $this->assertFileExists($schema);
        $buyer=array_replace($this->party(),['identifier'=>'11111111111','first_name'=>'Test','last_name'=>'Alıcı','email'=>'buyer@example.test']);
        foreach ([$this->render($this->party()),$this->render($this->party(),'TICARIFATURA'),$this->archive($buyer,true)['xml']] as $xml) {
            $doc=new DOMDocument();
            $this->assertTrue($doc->loadXML($xml,LIBXML_NONET));
            $this->assertTrue($doc->schemaValidate($schema),'GIB XSD rejected synthetic unsigned candidate.');
        }
    }

    public function test_inconsistent_even_rehashed_snapshot_is_rejected_before_xml(): void
    {
        $draft=$this->draft();
        $amounts=$draft->amounts;
        $amounts['groups'][0]['tax_cents']--;
        $draft->amounts=$amounts;
        $draft->snapshot_hash=hash('sha256',json_encode($amounts,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $this->expectException(DomainException::class);
        (new InvoicePreparationXml)->render($draft);
    }

    public function test_anonymous_identifier_is_not_accepted_for_einvoice(): void
    {
        $this->expectException(DomainException::class);
        $this->render(array_replace($this->party(),['identifier'=>'11111111111']));
    }

    public function test_archive_profile_is_not_inferred_from_einvoice_examples(): void
    {
        $this->expectException(DomainException::class);
        $this->render($this->party(),'EARSIVFATURA');
    }

    public function test_internet_sale_header_maps_documented_nested_fields(): void
    {
        $result=(new EdmInternetSaleDetails)->build('https://shop.example.test','ODEMEARACISI','Test aracı',new DateTimeImmutable('2026-11-25T10:00:00+03:00'),new DateTimeImmutable('2026-11-26T10:00:00+03:00'),'1234567890','Test taşıyıcı');
        $this->assertTrue($result['EARCHIVE']);
        $this->assertTrue($result['INTERNETSALES']);
        $details=$result['INTERNETSALESDETAILS'];
        $this->assertTrue($details['odemeTarihiSpecified']);
        $this->assertSame('Test aracı',$details['odemeAracisiAdi']);
        $this->assertSame('1234567890',$details['gonderiBilgileri']['gonderiTasiyan']['tuzelKisi']['VKN']);
    }

    private function archive(array $buyer,bool $allow=false,string $series='INT'): array
    {
        return (new UblSaleCandidate(new InvoicePreparationXml))->archive(
            $this->draft(),$this->party(),$buyer,'2026-11-26','TEST-1','2026-11-25',
            'https://shop.example.test','ODEMEARACISI','Test aracı',
            new DateTimeImmutable('2026-11-25T10:00:00+03:00'),new DateTimeImmutable('2026-11-26T10:00:00+03:00'),
            '1234567890','Test taşıyıcı',$series,$allow);
    }

    public function test_archive_candidate_keeps_uuid_metadata_and_submission_disabled(): void
    {
        $buyer=array_replace($this->party(),['identifier'=>'11111111111','first_name'=>'Test','last_name'=>'Alıcı','email'=>'buyer@example.test']);
        $result=$this->archive($buyer,true);
        $doc=new DOMDocument();
        $this->assertTrue($doc->loadXML($result['xml'],LIBXML_NONET));
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $this->assertSame('EARSIVFATURA',$xp->evaluate('string(/i:Invoice/cbc:ProfileID)'));
        $this->assertSame('11111111111',$xp->evaluate('string(/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID)'));
        $this->assertSame($this->draft()->uuid,$xp->evaluate('string(/i:Invoice/cbc:UUID)'));
        $this->assertSame('299.70',$xp->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount)'));
        $this->assertSame('INT',$result['requested_series']);
        $this->assertFalse($result['submission_allowed']);
        $this->assertTrue($result['header']['EARCHIVE']);
        $this->assertTrue($result['header']['INTERNETSALES']);
        $this->assertStringContainsString('Test taşıyıcı',$result['xml']);
        $this->assertStringContainsString('buyer@example.test',$result['xml']);
    }

    public function test_archive_fallback_requires_explicit_eligibility(): void
    {
        $this->expectException(DomainException::class);
        $this->archive(array_replace($this->party(),['identifier'=>'11111111111','first_name'=>'Test','last_name'=>'Alıcı','email'=>'buyer@example.test']));
    }

    public function test_archive_rejects_reserved_series(): void
    {
        $this->expectException(DomainException::class);
        $this->archive($this->party()+['email'=>'buyer@example.test'],false,'GIB');
    }

    public function test_archive_rejects_missing_electronic_delivery_address(): void
    {
        $this->expectException(DomainException::class);
        $this->archive($this->party());
    }

    public function test_missing_intermediary_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        (new EdmInternetSaleDetails)->build('https://shop.example.test','ODEMEARACISI',null,new DateTimeImmutable('2026-11-25'),new DateTimeImmutable('2026-11-26'),'1234567890','Test taşıyıcı');
    }

    public function test_preflight_rejects_changed_candidate_fields(): void
    {
        $xml=$this->render($this->party());
        $draft=$this->draft();
        $cases=[
            ['//cbc:ProfileID','TICARIFATURA'],
            ['//cbc:LineCountNumeric','2'],
            ['//cac:LegalMonetaryTotal/cbc:PayableAmount','299.71'],
            ['//cbc:TaxTypeCode','0000'],
            ['//cac:Price/cbc:PriceAmount','83.260000000'],
            ['//nwt:UnsignedCandidate','SUBMISSION_ALLOWED'],
        ];
        foreach ($cases as [$path,$to]) {
            $doc=new DOMDocument();
            $this->assertTrue($doc->loadXML($xml,LIBXML_NONET));
            $xp=new DOMXPath($doc);
            $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            $xp->registerNamespace('nwt','urn:newtic:unsigned-candidate:1');
            $nodes=$xp->query($path);
            $this->assertGreaterThan(0,$nodes->length);
            foreach ($nodes as $node) $node->textContent=$to;
            $changed=$doc->saveXML();
            try {
                (new \App\Services\Invoices\UblCandidatePreflight)->validate($changed,$draft->amounts,$draft->uuid,'TEMELFATURA');
                $this->fail('Tampered XML accepted');
            } catch (DomainException $error) {
                $this->assertStringContainsString('XML ön kontrolü',$error->getMessage());
            }
        }
    }

}
