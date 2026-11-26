<?php

namespace App\Services\Invoices;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DomainException;

/**
 * NEWTIC preflight for its own unsigned, ordinary TRY sale candidates.
 * Not the official GIB Schematron, signature verification or fiscal approval.
 */
final class UblCandidatePreflight
{
    private const INV = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private function require(bool $condition, string $code): void
    {
        if (!$condition) throw new DomainException('Fatura XML ön kontrolü başarısız: '.$code);
    }

    private function one(DOMXPath $xp, string $path, ?DOMElement $context = null): DOMElement
    {
        $nodes = $xp->query($path, $context);
        $this->require($nodes !== false && $nodes->length === 1 && $nodes->item(0) instanceof DOMElement, 'FIELD_COUNT');
        return $nodes->item(0);
    }

    private function eq(DOMXPath $xp, string $path, string $expected, ?DOMElement $context = null): void
    {
        $this->require($this->one($xp, $path, $context)->textContent === $expected, 'FIELD_VALUE');
    }

    private function money(DOMXPath $xp, string $path, int $cents, ?DOMElement $context = null): void
    {
        $node = $this->one($xp, $path, $context);
        $amount = intdiv($cents, 100).'.'.str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
        $this->require($node->textContent === $amount && $node->getAttribute('currencyID') === 'TRY', 'MONEY_OR_CURRENCY');
    }

    private function tax(DOMXPath $xp, DOMElement $parent, array $groups, int $tax): void
    {
        $total = $this->one($xp, 'cac:TaxTotal', $parent);
        $this->money($xp, 'cbc:TaxAmount', $tax, $total);
        $subs = $xp->query('cac:TaxSubtotal', $total);
        $this->require($subs->length === count($groups), 'TAX_GROUP_COUNT');
        $expected = array_column($groups, null, 'rate');
        foreach ($subs as $sub) {
            $rate = $this->one($xp, 'cbc:Percent', $sub)->textContent;
            $this->require(in_array($rate, ['1', '10', '20'], true) && isset($expected[$rate]), 'TAX_RATE_OR_DUPLICATE');
            $group = $expected[$rate];
            unset($expected[$rate]);
            $this->money($xp, 'cbc:TaxableAmount', $group['net_cents'], $sub);
            $this->money($xp, 'cbc:TaxAmount', $group['tax_cents'], $sub);
            $this->eq($xp, 'cac:TaxCategory/cac:TaxScheme/cbc:TaxTypeCode', '0015', $sub);
        }
        $this->require($expected === [], 'TAX_GROUP_MISSING');
    }

    private function party(DOMXPath $xp, DOMElement $party, bool $anonymous): string
    {
        $id = $this->one($xp, 'cac:PartyIdentification/cbc:ID', $party);
        $value = $id->textContent;
        $scheme = $id->getAttribute('schemeID');
        $this->require(($scheme === 'VKN' && preg_match('/^[0-9]{10}$/D', $value))
            || ($scheme === 'TCKN' && preg_match('/^[0-9]{11}$/D', $value)), 'PARTY_ID');
        $this->require($anonymous || $value !== '11111111111', 'ANONYMOUS_PARTY');
        if ($scheme === 'TCKN' && $value !== '11111111111') {
            $invalid = false;
            (new \App\Rules\TurkishIdentityNumber)->validate('identifier', $value, function () use (&$invalid) { $invalid = true; });
            $this->require(!$invalid, 'TCKN_CHECKSUM');
        }
        $paths = ['cac:PostalAddress/cbc:StreetName','cac:PostalAddress/cbc:CitySubdivisionName','cac:PostalAddress/cbc:CityName'];
        $paths = array_merge($paths, $scheme === 'VKN'
            ? ['cac:PartyName/cbc:Name','cac:PartyTaxScheme/cac:TaxScheme/cbc:Name']
            : ['cac:Person/cbc:FirstName','cac:Person/cbc:FamilyName']);
        foreach ($paths as $path) $this->require(trim($this->one($xp, $path, $party)->textContent) !== '', 'PARTY_REQUIRED');
        $this->eq($xp, 'cac:PostalAddress/cac:Country/cbc:IdentificationCode', 'TR', $party);
        return $value;
    }

    public function validate(string $xml, array $snapshot, string $uuid, string $profile, bool $anonymousArchivePermitted = false): void
    {
        $this->validateDocument($xml,$snapshot,$uuid,$profile,$anonymousArchivePermitted,false);
    }

    /** Narrow NEWTIC ordinary sale provider-draft check. No signature or GIB approval. */
    public function validateProviderDraft(#[\SensitiveParameter] string $xml, #[\SensitiveParameter] array $snapshot, string $uuid, string $profile): void
    {
        $this->require(in_array($profile,['TEMELFATURA','TICARIFATURA'],true),'PROVIDER_PROFILE');
        $this->validateDocument($xml,$snapshot,$uuid,$profile,false,true);
    }

    private function validateDocument(#[\SensitiveParameter] string $xml, #[\SensitiveParameter] array $snapshot, string $uuid, string $profile, bool $anonymousArchivePermitted, bool $providerDraft): void
    {
        InvoiceSnapshotRules::validate($snapshot);
        $this->require(in_array($profile, ['TEMELFATURA','TICARIFATURA','EARSIVFATURA'], true), 'PROFILE');
        $this->require(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $uuid) === 1, 'UUID');
        $this->require(strlen($xml) <= 32*1024*1024 && stripos($xml, '<!DOCTYPE') === false, 'XML_INPUT');
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $this->require($xml !== '' && $doc->loadXML($xml, LIBXML_NONET) && $doc->doctype === null, 'XML_PARSE');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $doc->documentElement;
        $this->require($root instanceof DOMElement && $root->localName === 'Invoice' && $root->namespaceURI === self::INV, 'ROOT');
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('cbc', self::CBC);
        $xp->registerNamespace('cac', self::CAC);
        $xp->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xp->registerNamespace('nwt', 'urn:newtic:unsigned-candidate:1');
        foreach (['UBLVersionID'=>'2.1','CustomizationID'=>'TR1.2','ProfileID'=>$profile,
            'InvoiceTypeCode'=>'SATIS','DocumentCurrencyCode'=>'TRY','UUID'=>$uuid,
            'CopyIndicator'=>'false',
            'LineCountNumeric'=>(string)count($snapshot['lines'])] as $field => $value) {
            $this->eq($xp, 'cbc:'.$field, $value, $root);
        }
        if ($providerDraft) {
            $id=$this->one($xp,'cbc:ID',$root)->textContent;
            $this->require(preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D',$id)===1,'PROVIDER_ID');
            $this->require($xp->query('//*[namespace-uri()="urn:newtic:unsigned-candidate:1"]')->length===0
                && !str_contains($xml,'NEWTIC_UNSIGNED_TEST_ONLY') && !str_contains($xml,'NOT_FOR_SUBMISSION')
                && !str_contains($xml,'RESMÎ BELGE DEĞİLDİR'),'TECHNICAL_MARKER');
            // Restrict to the existing ordinary VAT-inclusive sale model.
            // Discounts/charges/withholding/prepayments require separate allocation logic.
            $this->require($xp->query('//cac:AllowanceCharge|//cac:WithholdingTaxTotal|//cac:PrepaidPayment|//cbc:PrepaidAmount|//cbc:PayableRoundingAmount|//cbc:AllowanceTotalAmount|//cbc:ChargeTotalAmount')->length===0,'UNSUPPORTED_ADJUSTMENT');
        } else {
            $this->eq($xp,'cbc:ID',UblSaleCandidate::EDM_SERIAL_SENTINEL,$root);
            $this->eq($xp, 'ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/nwt:UnsignedCandidate', 'NOT_FOR_SUBMISSION', $root);
            $this->eq($xp, 'cac:Signature/cac:DigitalSignatureAttachment/cac:ExternalReference/cbc:URI', '#NEWTIC_UNSIGNED_TEST_ONLY', $root);
            $this->require($xp->query('//*[namespace-uri()="http://www.w3.org/2000/09/xmldsig#" and local-name()="Signature"]')->length === 0, 'UNEXPECTED_SIGNATURE');
        }
        $seller = $this->party($xp, $this->one($xp, 'cac:AccountingSupplierParty/cac:Party', $root), false);
        $this->party($xp, $this->one($xp, 'cac:AccountingCustomerParty/cac:Party', $root), $profile === 'EARSIVFATURA' && $anonymousArchivePermitted);
        $this->eq($xp, 'cac:Signature/cbc:ID', $seller, $root);
        $this->eq($xp, 'cac:Signature/cac:SignatoryParty/cac:PartyIdentification/cbc:ID', $seller, $root);
        if ($profile === 'EARSIVFATURA') {
            $email = $this->one($xp, 'cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail', $root)->textContent;
            $this->require(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'ARCHIVE_EMAIL');
        }
        $this->tax($xp, $root, $snapshot['groups'], $snapshot['tax_cents']);
        $total = $this->one($xp, 'cac:LegalMonetaryTotal', $root);
        foreach (['LineExtensionAmount'=>'net_cents','TaxExclusiveAmount'=>'net_cents','TaxInclusiveAmount'=>'gross_cents','PayableAmount'=>'gross_cents'] as $field=>$key) {
            $this->money($xp, 'cbc:'.$field, $snapshot[$key], $total);
        }
        $rows = $xp->query('cac:InvoiceLine', $root);
        $this->require($rows->length === count($snapshot['lines']), 'LINE_COUNT');
        foreach ($rows as $i=>$row) {
            $line = $snapshot['lines'][$i];
            $this->eq($xp, 'cbc:ID', (string)($i+1), $row);
            $qty = $this->one($xp, 'cbc:InvoicedQuantity', $row);
            $this->require($qty->textContent === (string)$line['quantity'] && $qty->getAttribute('unitCode') === 'C62', 'QUANTITY');
            $this->eq($xp, 'cac:Item/cbc:Name', $line['label'], $row);
            $this->money($xp, 'cbc:LineExtensionAmount', $line['net_cents'], $row);
            $this->tax($xp, $row, [$line], $line['tax_cents']);
            $price = $this->one($xp, 'cac:Price/cbc:PriceAmount', $row);
            $this->require($price->getAttribute('currencyID') === 'TRY'
                && preg_match('/^([0-9]{1,11})\.([0-9]{2})([0-9]{7})$/D', $price->textContent, $m) === 1, 'UNIT_PRICE');
            $wholeCents = (int)$m[1]*100+(int)$m[2];
            $quantity = $line['quantity'];
            $this->require($wholeCents <= intdiv(1_000_000_000_000, $quantity), 'UNIT_PRICE_RANGE');
            $rounded = $wholeCents*$quantity+intdiv((int)$m[3]*$quantity+5_000_000, 10_000_000);
            $this->require($rounded === $line['net_cents'], 'UNIT_PRICE_TOTAL');
        }
    }
}
