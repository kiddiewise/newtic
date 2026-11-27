<?php
namespace App\Services\Invoices;

use App\Models\InvoicePreparation;
use DomainException;
use DOMDocument;
use DOMXPath;

/** Sandbox transport only: preserve quantities, taxes and amounts, replace
 * names and identifiers with the provider's own example identities. */
final class EdmOrderDraftPayload
{
    public static function fields(InvoicePreparation $draft,string $date,string $template): array {
        EdmSecondSandboxFixture::templateKind($template);
        $snapshot=$draft->amounts;
        InvoiceSnapshotRules::validate($snapshot);
        if (!hash_equals($draft->snapshot_hash,hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)))) throw new DomainException('ORDER_SNAPSHOT_CHANGED');
        foreach ($snapshot['lines'] as $i=>&$line) $line['label']=$line['kind']==='shipping'?'Kargo':'Ürün '.($i+1);
        unset($line);
        $copy=new InvoicePreparation(['uuid'=>$draft->uuid,'gross_cents'=>$draft->gross_cents,'amounts'=>$snapshot,
            'snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))]);
        $f=EdmSandboxSmokeFixture::class;
        $party=['identifier'=>$f::IDENTIFIER,'name'=>'EDM Bilişim Sistemleri ve Danışmanlık Hizmetleri A.Ş.',
            'address'=>'Cansızoğlu işhanı No:7/35 Mecidiyeköy','district'=>'Şişli','city'=>'İstanbul','tax_office'=>'Esenler'];
        $xml=(new UblSaleCandidate(new InvoicePreparationXml))->rehearseEdmDraft($copy,$party,$party,$f::PROFILE,$date,$draft->uuid,$date)['xml'];
        $doc=new DOMDocument();
        if (!$doc->loadXML($xml,LIBXML_NONET)||$doc->doctype) throw new DomainException('ORDER_XML_INVALID');
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root=$doc->documentElement;
        $cac='urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
        $cbc='urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $add=static function($parent,$ns,$name,$value=null) use($doc){
            $el=$doc->createElementNS($ns,$name);$parent->appendChild($el);
            if ($value!==null) $el->appendChild($doc->createTextNode($value));
            return $el;
        };
        $ref=$add($root,$cac,'cac:AdditionalDocumentReference');
        $root->insertBefore($ref,$xp->query('cac:Signature',$root)->item(0));
        $add($ref,$cbc,'cbc:ID',$draft->uuid);
        $add($ref,$cbc,'cbc:IssueDate',$date);
        $bin=$add($add($ref,$cac,'cac:Attachment'),$cbc,'cbc:EmbeddedDocumentBinaryObject',base64_encode($template));
        foreach (['filename'=>UblSaleCandidate::EDM_SERIAL_SENTINEL.'.xslt','characterSetCode'=>'UTF-8','encodingCode'=>'Base64','mimeCode'=>'application/xml'] as $k=>$v) $bin->setAttribute($k,$v);
        $xml=$doc->saveXML();
        (new UblCandidatePreflight)->validateProviderDraft($xml,$snapshot,$draft->uuid,$f::PROFILE);
        $fields=EdmSandboxLoadPayload::fields($xml,$draft->uuid,$f::PROFILE,$f::GB,$f::PK);
        $header=$fields['INVOICE']['HEADER'];
        $fields['INVOICE']['HEADER']=array_slice($header,0,6,true)+['INVOICESERIAL_REQUESTED'=>$f::SERIES]+array_slice($header,6,null,true);
        $fields['GENERATEINVOICEIDONLOAD']='true';
        return $fields;
    }
}
