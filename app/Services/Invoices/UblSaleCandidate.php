<?php

namespace App\Services\Invoices;

use App\Models\InvoicePreparation;
use DOMDocument;
use DOMElement;
use DomainException;

/**
 * Unsigned test candidate for ordinary Turkish e-Invoice sales, based on
 * EDM supplied satis_temel.xml and satis_ticari.xml. No network, storage,
 * fiscal number allocation, or public/admin export route. NOT validated
 * against current GIB XSD/Schematron and must not be submitted.
 */
final class UblSaleCandidate
{
    private const INV='urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const CAC='urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC='urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    public const EDM_SERIAL_SENTINEL='ABC2009123456789';

    public function __construct(private InvoicePreparationXml $components) {}

    private function node(DOMElement $parent,string $prefix,string $name,?string $value=null): DOMElement
    {
        $node=$parent->appendChild($parent->ownerDocument->createElementNS($prefix==='cac' ? self::CAC : self::CBC,$prefix.':'.$name));
        if ($value!==null) $node->appendChild($parent->ownerDocument->createTextNode($value));
        return $node;
    }

    private function field(array $data,string $key,int $max=255): string
    {
        $value=$data[$key] ?? null;
        if (!is_string($value) || trim($value)==='' || mb_strlen($value)>$max
            || !preg_match('/^[\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}\t\r\n]+$/uD',$value)) {
            throw new DomainException('Eksik veya geçersiz belge alanı: '.$key);
        }
        return trim($value);
    }

    private function date(string $date): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])) {
            throw new DomainException('Geçersiz belge tarihi.');
        }
        return $date;
    }

    private function party(DOMElement $root,string $type,array $data): void
    {
        $identifier=$this->field($data,'identifier',11);
        if (!preg_match('/^[0-9]{10,11}$/D',$identifier) || $identifier==='11111111111') {
            throw new DomainException('e-Fatura için geçerli taraf kimlik bilgisi gerekiyor.');
        }
        $personal=strlen($identifier)===11;
        if ($personal) {
            $invalid=false;
            (new \App\Rules\TurkishIdentityNumber)->validate('identifier',$identifier,function () use (&$invalid) { $invalid=true; });
            if ($invalid) throw new DomainException('Geçersiz taraf T.C. kimlik numarası.');
        }
        $party=$this->node($this->node($root,'cac',$type),'cac','Party');
        $this->node($this->node($party,'cac','PartyIdentification'),'cbc','ID',$identifier)->setAttribute('schemeID',$personal?'TCKN':'VKN');
        if (!$personal) $this->node($this->node($party,'cac','PartyName'),'cbc','Name',$this->field($data,'name'));
        $address=$this->node($party,'cac','PostalAddress');
        $this->node($address,'cbc','StreetName',$this->field($data,'address',1000));
        $this->node($address,'cbc','CitySubdivisionName',$this->field($data,'district'));
        $this->node($address,'cbc','CityName',$this->field($data,'city'));
        $country=$this->node($address,'cac','Country');
        $this->node($country,'cbc','IdentificationCode','TR');
        $this->node($country,'cbc','Name','Türkiye');
        if (!$personal || isset($data['tax_office'])) {
            $scheme=$this->node($this->node($party,'cac','PartyTaxScheme'),'cac','TaxScheme');
            $this->node($scheme,'cbc','Name',$this->field($data,'tax_office'));
        }
        if ($personal) {
            $person=$this->node($party,'cac','Person');
            $this->node($person,'cbc','FirstName',$this->field($data,'first_name'));
            $this->node($person,'cbc','FamilyName',$this->field($data,'last_name'));
        }
    }

    /** Nine decimal TRY unit price, using integer division rather than float.
     * At the supported quantity cap this preserves rounded line cents. */
    private function netUnitPrice(int $net,int $qty): string
    {
        if ($net<0 || $net>1_000_000_000_000 || $qty<1 || $qty>1_000_000) throw new DomainException('Geçersiz satır miktarı veya tutarı.');
        $wholeCents=intdiv($net,$qty);
        $fraction=intdiv(($net%$qty)*10_000_000+intdiv($qty,2),$qty);
        if ($fraction===10_000_000) { $wholeCents++; $fraction=0; }
        return intdiv($wholeCents,100).'.'.str_pad((string)($wholeCents%100),2,'0',STR_PAD_LEFT).str_pad((string)$fraction,7,'0',STR_PAD_LEFT);
    }

    public function render(InvoicePreparation $draft,array $seller,array $buyer,string $profile,string $issueDate,string $orderNumber,string $orderDate): string
    {
        if (!in_array($profile,['TEMELFATURA','TICARIFATURA'],true)) throw new DomainException('Bu test motoru yalnızca temel/ticari satış faturası adayını destekler.');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$draft->uuid ?? '')) throw new DomainException('Taslak ETTN geçersiz.');
        if ($this->date($orderDate)>$this->date($issueDate)) throw new DomainException('Sipariş tarihi belge tarihinden sonra olamaz.');
        $orderNumber=$this->field(['order'=>$orderNumber],'order',100);
        $parts=new DOMDocument();
        if (!$parts->loadXML($this->components->render($draft),LIBXML_NONET)) throw new DomainException('Hesap bileşenleri okunamadı.');
        $doc=new DOMDocument('1.0','UTF-8');
        $doc->formatOutput=true;
        $root=$doc->appendChild($doc->createElementNS(self::INV,'Invoice'));
        $this->node($root,'cbc','UBLVersionID','2.1');
        $this->node($root,'cbc','CustomizationID','TR1.2');
        $this->node($root,'cbc','ProfileID',$profile);
        $this->node($root,'cbc','ID',self::EDM_SERIAL_SENTINEL);
        $this->node($root,'cbc','CopyIndicator','false');
        $this->node($root,'cbc','UUID',$draft->uuid);
        $this->node($root,'cbc','IssueDate',$issueDate);
        $this->node($root,'cbc','InvoiceTypeCode','SATIS');
        $this->node($root,'cbc','Note','NEWTIC TEKNİK TEST ADAYI — RESMÎ BELGE DEĞİLDİR, GÖNDERİLMEZ.');
        $this->node($root,'cbc','DocumentCurrencyCode','TRY');
        $this->node($root,'cbc','LineCountNumeric',(string)count($draft->amounts['lines']));
        $reference=$this->node($root,'cac','OrderReference');
        $this->node($reference,'cbc','ID',$orderNumber);
        $this->node($reference,'cbc','IssueDate',$orderDate);
        $this->party($root,'AccountingSupplierParty',$seller);
        $this->party($root,'AccountingCustomerParty',$buyer);
        $index=0;
        foreach ($parts->documentElement->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->namespaceURI!==self::CAC) continue;
            if (!in_array($node->localName,['TaxTotal','LegalMonetaryTotal','InvoiceLine'],true)) continue;
            $import=$root->appendChild($doc->importNode($node,true));
            if ($node->localName==='InvoiceLine') {
                $line=$draft->amounts['lines'][$index++];
                $price=$this->node($import,'cac','Price');
                $this->node($price,'cbc','PriceAmount',$this->netUnitPrice($line['net_cents'],$line['quantity']))->setAttribute('currencyID','TRY');
            }
        }
        if ($index!==count($draft->amounts['lines'])) throw new DomainException('Belge satırları uyuşmuyor.');
        return $doc->saveXML();
    }
}
