<?php

namespace App\Services\Invoices;

use App\Models\InvoicePreparation;
use DOMDocument;
use DOMElement;
use DomainException;

/**
 * Technical UBL monetary components only. The custom root deliberately
 * prevents this file from being mistaken for a valid UBL Invoice.
 */
final class InvoicePreparationXml
{
    private const NWT='urn:newtic:invoice-preparation:1';
    private const CAC='urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC='urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private function node(DOMElement $parent,string $prefix,string $name,?string $value=null): DOMElement
    {
        $ns=match($prefix){'cac'=>self::CAC,'cbc'=>self::CBC,default=>self::NWT};
        $node=$parent->ownerDocument->createElementNS($ns,$prefix.':'.$name);
        $parent->appendChild($node);
        if ($value!==null) $node->appendChild($parent->ownerDocument->createTextNode($value));
        return $node;
    }

    private function money(DOMElement $parent,string $name,int $cents): void
    {
        if ($cents<0) throw new DomainException('Invalid monetary amount');
        $this->node($parent,'cbc',$name,intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT))->setAttribute('currencyID','TRY');
    }

    private function tax(DOMElement $parent,array $groups,int $total): void
    {
        $tax=$this->node($parent,'cac','TaxTotal');
        $this->money($tax,'TaxAmount',$total);
        foreach($groups as $group) {
            $sub=$this->node($tax,'cac','TaxSubtotal');
            $this->money($sub,'TaxableAmount',$group['net_cents']);
            $this->money($sub,'TaxAmount',$group['tax_cents']);
            $this->node($sub,'cbc','Percent',(string)$group['rate']);
            $category=$this->node($sub,'cac','TaxCategory');
            $scheme=$this->node($category,'cac','TaxScheme');
            $this->node($scheme,'cbc','Name','KDV');
            $this->node($scheme,'cbc','TaxTypeCode','0015');
        }
    }

    public function render(InvoicePreparation $preparation): string
    {
        $a=$preparation->amounts;
        if (!is_array($a) || !$a['complete'] || $a['net_cents']+$a['tax_cents']!==$a['gross_cents']
            || $a['gross_cents']!==$preparation->gross_cents
            || !hash_equals($preparation->snapshot_hash,hash('sha256',json_encode($a,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))) {
            throw new DomainException('Taslak tutar kaydı doğrulanamadı.');
        }
        $doc=new DOMDocument('1.0','UTF-8');
        $doc->formatOutput=true;
        $root=$doc->appendChild($doc->createElementNS(self::NWT,'nwt:InvoicePreparation'));
        $root->setAttribute('fiscalDocument','false');
        $this->node($root,'cbc','UUID',$preparation->uuid);
        $this->node($root,'nwt','Status','LOCAL_ONLY_NOT_FOR_SUBMISSION');
        if ($preparation->shipping_rate!==null) $this->node($root,'nwt','ShippingTaxAssumption',(string)$preparation->shipping_rate);
        $this->node($root,'cbc','DocumentCurrencyCode','TRY');
        $this->tax($root,$a['groups'],$a['tax_cents']);
        $total=$this->node($root,'cac','LegalMonetaryTotal');
        $this->money($total,'LineExtensionAmount',$a['net_cents']);
        $this->money($total,'TaxExclusiveAmount',$a['net_cents']);
        $this->money($total,'TaxInclusiveAmount',$a['gross_cents']);
        $this->money($total,'PayableAmount',$a['gross_cents']);
        foreach($a['lines'] as $i=>$line) {
            $row=$this->node($root,'cac','InvoiceLine');
            $this->node($row,'cbc','ID',(string)($i+1));
            $this->node($row,'cbc','InvoicedQuantity',(string)$line['quantity'])->setAttribute('unitCode','C62');
            $this->money($row,'LineExtensionAmount',$line['net_cents']);
            $this->tax($row,[$line],$line['tax_cents']);
            $item=$this->node($row,'cac','Item');
            $this->node($item,'cbc','Name',$line['label']);
            // Net unit-price allocation for fiscal UBL is a later validation gate.
        }
        return $doc->saveXML();
    }
}
