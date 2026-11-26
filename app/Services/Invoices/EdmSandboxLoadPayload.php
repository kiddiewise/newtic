<?php

namespace App\Services\Invoices;

use DOMDocument;
use DOMXPath;
use DomainException;

/** Narrow SOAP payload mapping; not fiscal validation or signature verification. */
final class EdmSandboxLoadPayload
{
    public static function fields(#[\SensitiveParameter] string $xml,string $uuid,string $profile,
        #[\SensitiveParameter] string $senderAlias,#[\SensitiveParameter] string $receiverAlias): array
    {
        if (!in_array($profile,['TEMELFATURA','TICARIFATURA'],true)
            || strlen($xml)>4*1024*1024 || preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) {
            throw new DomainException('EDM taslak yükleme verisi geçersiz.');
        }
        foreach ([$senderAlias,$receiverAlias] as $alias) {
            if (!preg_match('/^urn:mail:[^\s<>]{1,250}$/Di',$alias)) throw new DomainException('EDM etiketi geçersiz.');
        }
        $doc=new DOMDocument();
        if (!@$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING) || $doc->doctype) throw new DomainException('XML okunamadı.');
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        if ($xp->query('/i:Invoice')->length!==1 || $xp->query('//*[namespace-uri()="urn:newtic:unsigned-candidate:1"]')->length>0
            || str_contains($xml,'NEWTIC_UNSIGNED_TEST_ONLY') || str_contains($xml,'NOT_FOR_SUBMISSION')
            || str_contains($xml,'RESMÎ BELGE DEĞİLDİR')) throw new DomainException('İmzasız NEWTIC teknik adayı gönderilemez.');
        $value=function(string $path) use ($xp): string {
            $nodes=$xp->query($path);
            if ($nodes->length!==1 || trim($nodes->item(0)->textContent)==='') throw new DomainException('UBL kimlik alanı eksik veya belirsiz.');
            return trim($nodes->item(0)->textContent);
        };
        if ($value('/i:Invoice/cbc:UUID')!==$uuid || $value('/i:Invoice/cbc:ProfileID')!==$profile
            || $value('/i:Invoice/cbc:InvoiceTypeCode')!=='SATIS' || $value('/i:Invoice/cbc:DocumentCurrencyCode')!=='TRY') {
            throw new DomainException('UBL belge kimliği uyuşmuyor.');
        }
        $id=$value('/i:Invoice/cbc:ID');
        if (!preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D',$id)) throw new DomainException('UBL belge numarası biçimi geçersiz.');
        $seller=$value('/i:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID');
        $buyer=$value('/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID');
        foreach ([$seller,$buyer] as $party) {
            if (!preg_match('/^[0-9]{10,11}$/D',$party) || $party==='11111111111') throw new DomainException('e-Fatura taraf kimliği geçersiz.');
        }
        return ['INVOICE'=>['UUID'=>$uuid,'ID'=>$id,'HEADER'=>[
            'SENDER'=>$seller,'RECEIVER'=>$buyer,'FROM'=>$senderAlias,'TO'=>$receiverAlias,
            'INTERNETSALES'=>'false','EARCHIVE'=>'false',
            'EARCHIVE_REPORT_SENDDATE'=>'0001-01-01','CANCEL_EARCHIVE_REPORT_SENDDATE'=>'0001-01-01',
        ],'CONTENT'=>base64_encode($xml)],
            'SENDER'=>['vkn'=>$seller,'alias'=>$senderAlias],
            'RECEIVER'=>['vkn'=>$buyer,'alias'=>$receiverAlias],
            'GENERATEINVOICEIDONLOAD'=>'false'];
    }
}
