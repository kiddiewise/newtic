<?php

namespace App\Services\Invoices;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** EDM v4.2.1 §5.6, Request-Response §1.4. Narrow outgoing XML evidence.
 * No document persistence, raw XML output, signature verification or fiscal approval.
 */
final class EdmInvoiceXmlEvidence
{
    private function one(DOMElement $parent,string $name): DOMElement
    {
        $nodes=[];
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->localName!==$name) continue;
            if ($node->namespaceURI!==null && $node->namespaceURI!=='') throw new EdmLookupFailure('GETINVOICE_NAMESPACE');
            $nodes[]=$node;
        }
        if (count($nodes)!==1) throw new EdmLookupFailure('GETINVOICE_FIELD_'.$name);
        return $nodes[0];
    }

    private function text(DOMElement $node): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) throw new EdmLookupFailure('GETINVOICE_FIELD_SHAPE');
        }
        return trim($node->textContent);
    }

    public function parse(DOMElement $reply,string $uuid,string $profile,#[\SensitiveParameter] string $seller,#[\SensitiveParameter] string $buyer,bool $includeBytes=false): array
    {
        $invoice=$this->one($reply,'INVOICE');
        if (strtolower($invoice->getAttribute('UUID'))!==strtolower($uuid)) throw new EdmLookupFailure('GETINVOICE_UUID_MISMATCH');
        $header=$this->one($invoice,'HEADER');
        if ($this->text($this->one($header,'SENDER'))!==$seller
            || $this->text($this->one($header,'RECEIVER'))!==$buyer
            || $this->text($this->one($header,'PROFILEID'))!==$profile) throw new EdmLookupFailure('GETINVOICE_HEADER_MISMATCH');
        $number=$invoice->getAttribute('ID');
        if (!preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D',$number)) throw new EdmLookupFailure('GETINVOICE_NUMBER_INVALID');
        $encoded=preg_replace('/[\r\n\t ]/','',$this->text($this->one($invoice,'CONTENT')));
        if ($encoded==='' || strlen($encoded)>3*1024*1024) throw new EdmLookupFailure('GETINVOICE_CONTENT_SIZE');
        $xml=base64_decode($encoded,true);
        if ($xml===false || base64_encode($xml)!==$encoded || preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) {
            throw new EdmLookupFailure('GETINVOICE_CONTENT_INVALID');
        }
        $doc=new DOMDocument();
        if (!@$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING) || $doc->doctype) throw new EdmLookupFailure('GETINVOICE_XML_INVALID');
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('ds','http://www.w3.org/2000/09/xmldsig#');
        $value=static function(string $path) use ($xp): string {
            $nodes=$xp->query($path);
            if ($nodes===false || $nodes->length!==1) throw new EdmLookupFailure('GETINVOICE_UBL_FIELD_COUNT');
            foreach ($nodes->item(0)->childNodes as $child) {
                if ($child instanceof DOMElement) throw new EdmLookupFailure('GETINVOICE_UBL_FIELD_SHAPE');
            }
            return trim($nodes->item(0)->textContent);
        };
        if (strtolower($value('/i:Invoice/cbc:UUID'))!==strtolower($uuid)
            || $value('/i:Invoice/cbc:ID')!==$number || $value('/i:Invoice/cbc:ProfileID')!==$profile
            || $value('/i:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID')!==$seller
            || $value('/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID')!==$buyer) {
            throw new EdmLookupFailure('GETINVOICE_UBL_IDENTITY_MISMATCH');
        }
        $signatureCount=$xp->query('//ds:Signature')->length;
        $result=['uuid'=>strtolower($uuid),'invoice_number'=>$number===UblSaleCandidate::EDM_SERIAL_SENTINEL?null:$number,
            'profile'=>$profile,'xml_sha256'=>hash('sha256',$xml),'xml_bytes'=>strlen($xml),
            'signature_element_present'=>$signatureCount>0,'signature_verified'=>false,
            'fiscal_success_confirmed'=>false,'automatic_retry'=>false];
        if ($includeBytes) $result['private_xml_bytes']=$xml;
        return $result;
    }
}
