<?php

namespace App\Services\Invoices;

use App\Models\InvoicePreparation;
use DOMDocument;
use DOMElement;
use DOMXPath;
use DomainException;

/**
 * Offline second sandbox fixture. Never load or send a document here.
 * Uses EDM C# sample party address and structure; no live party record is
 * created and unverified sample MERSIS, trade registry or telephone omitted.
 */
final class EdmSecondSandboxFixture
{
    public const UUID='f4e65f2e-0d32-41e7-a6f3-1807d7956ab2';
    public const NAMESPACE_UUID='b907ed71-8846-49a1-b8d3-09c509dd5813';
    public const NAMESPACE_REFERENCE_UUID='e59a66f1-353d-4cd4-bb72-cf6c67f43c71';
    public const REFERENCE_UUID='10d4ef66-8a87-4879-9d30-3ad3a2dd02f9';
    public const EDM_XSLT_SHA256='17fcac0bd820612921eccbf0c280e1512ad2f1cbc1d28cf3ea88141cd1ab7d81';
    public const GIB_XSLT_SHA256='8b3a2551e2dbf0375567de09d504d2b6741fe8881a59f0dbce47bddcfd693620';
    private const CAC='urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC='urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public static function templateKind(#[\SensitiveParameter] string $xslt): string
    {
        if (strlen($xslt)>512*1024 || str_contains(strtolower($xslt),'<!doctype') || str_contains(strtolower($xslt),'<!entity')) {
            throw new DomainException('SECOND_XSLT_INVALID');
        }
        return match(hash('sha256',$xslt)) {
            self::EDM_XSLT_SHA256=>'edm_supplied_general',
            self::GIB_XSLT_SHA256=>'gib_ubltr_reference',
            default=>throw new DomainException('SECOND_XSLT_NOT_PINNED'),
        };
    }

    public static function prepare(string $date,#[\SensitiveParameter] string $xslt,bool $namespaceTrial=false): array
    {
        $kind=self::templateKind($xslt);
        return self::build($date,$xslt,$kind,$namespaceTrial);
    }

    private static function node(DOMElement $parent,string $name,?string $value=null): DOMElement
    {
        $node=$parent->ownerDocument->createElementNS(str_starts_with($name,'cac:')?self::CAC:self::CBC,$name);
        $parent->appendChild($node);
        if ($value!==null) $node->appendChild($parent->ownerDocument->createTextNode($value));
        return $node;
    }

    private static function build(string $date,string $xslt,string $kind,bool $namespaceTrial): array
    {
        $f=EdmSandboxSmokeFixture::class;
        $uuid=$namespaceTrial?self::NAMESPACE_UUID:self::UUID;
        $reference=$namespaceTrial?self::NAMESPACE_REFERENCE_UUID:self::REFERENCE_UUID;
        $line=['label'=>'NEWTIC EDM ikinci entegrasyon testi','quantity'=>1,'unit_gross_cents'=>120,
            'kind'=>'product','gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,'rate'=>20];
        $snapshot=['complete'=>true,'gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,
            'groups'=>[['gross_cents'=>120,'net_cents'=>100,'tax_cents'=>20,'rate'=>20]],'lines'=>[$line]];
        $draft=new InvoicePreparation(['uuid'=>$uuid,'gross_cents'=>120,
            'snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'amounts'=>$snapshot]);
        // Historical sample from CreateInvoiceTypeTestObject. Not a current
        // tax registration claim. The account PDF states Esenler tax office.
        $party=['identifier'=>$f::IDENTIFIER,'name'=>'EDM Bilişim Sistemleri ve Danışmanlık Hizmetleri A.Ş.',
            'address'=>'Cansızoğlu işhanı No:7/35 Mecidiyeköy','district'=>'Şişli','city'=>'İstanbul','tax_office'=>'Esenler'];
        $xml=(new UblSaleCandidate(new InvoicePreparationXml))->rehearseEdmDraft(
            $draft,$party,$party,$f::PROFILE,$date,($namespaceTrial?'NEWTIC-EDM-SMOKE-03':'NEWTIC-EDM-SMOKE-02'),$date)['xml'];
        $doc=new DOMDocument();
        if (!$doc->loadXML($xml,LIBXML_NONET)||$doc->doctype) throw new DomainException('SECOND_XML_INVALID');
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cac',self::CAC);
        $xp->registerNamespace('cbc',self::CBC);
        $root=$doc->documentElement;
        // Party WebsiteURI precedes PartyIdentification in UBL-TR XSD.
        foreach (['AccountingSupplierParty','AccountingCustomerParty'] as $role) {
            $p=$xp->query('/i:Invoice/cac:'.$role.'/cac:Party')->item(0);
            $website=self::node($p,'cbc:WebsiteURI','www.edmbilisim.com.tr');
            $p->insertBefore($website,$p->firstChild);
        }
        // EDM C# default invoice design: AdditionalDocumentReference comes
        // before Signature, base64 binary content with exact attributes.
        $ref=self::node($root,'cac:AdditionalDocumentReference');
        $root->insertBefore($ref,$xp->query('/i:Invoice/cac:Signature')->item(0));
        self::node($ref,'cbc:ID',$reference);
        self::node($ref,'cbc:IssueDate',$date);
        $binary=self::node(self::node($ref,'cac:Attachment'),'cbc:EmbeddedDocumentBinaryObject',base64_encode($xslt));
        foreach (['filename'=>UblSaleCandidate::EDM_SERIAL_SENTINEL.'.xslt','characterSetCode'=>'UTF-8',
            'encodingCode'=>'Base64','mimeCode'=>'application/xml'] as $key=>$value) $binary->setAttribute($key,$value);
        $xml=$doc->saveXML();
        if (!is_string($xml)) throw new DomainException('SECOND_XML_INVALID');
        EdmUblNamespace::assertTransport($xml);
        (new UblCandidatePreflight)->validateProviderDraft($xml,$snapshot,$uuid,$f::PROFILE);
        $fields=EdmSandboxLoadPayload::fields($xml,$uuid,$f::PROFILE,$f::GB,$f::PK);
        $header=$fields['INVOICE']['HEADER'];
        $fields['INVOICE']['HEADER']=array_slice($header,0,6,true)+['INVOICESERIAL_REQUESTED'=>$f::SERIES]+array_slice($header,6,null,true);
        $fields['GENERATEINVOICEIDONLOAD']='true';
        // No change to original unnumbered fixture or the one-shot journal.
        // The next phase must validate exact bytes, reconcile the first UUID,
        // reserve this separate UUID, and explicitly authorize numbering.
        return ['uuid'=>$uuid,'first_uuid'=>$namespaceTrial?self::UUID:$f::UUID,'xml'=>$xml,
            'xml_sha256'=>hash('sha256',$xml),'xslt_sha256'=>hash('sha256',$xslt),
            'template_kind'=>$kind,'fields'=>$fields,'request_sha256'=>hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR)),
            'submission_allowed'=>false,'number_allocated'=>false,'signature_verified'=>false];
    }
}
