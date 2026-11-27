<?php

namespace App\Services\Invoices;

use DOMDocument;
use DOMElement;
use DomainException;

/** EDM's sample XML uses lexical cac/cbc tags with root namespace declarations.
 * Preserve the unsigned XML infoset; never rewrite a signed provider document. */
final class EdmUblNamespace
{
    public const XMLNS='http://www.w3.org/2000/xmlns/';
    public const NS=[
        'cbc'=>'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'cac'=>'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'ext'=>'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
        'xades'=>'http://uri.etsi.org/01903/v1.3.2#',
        'xsi'=>'http://www.w3.org/2001/XMLSchema-instance',
        'ds'=>'http://www.w3.org/2000/09/xmldsig#',
    ];
    public static function declare(DOMElement $root): void
    {
        foreach (self::NS as $prefix=>$uri) $root->setAttributeNS(self::XMLNS,'xmlns:'.$prefix,$uri);
    }
    public static function assertTransport(string $xml): void
    {
        $doc=new DOMDocument();
        if (!@$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)||$doc->doctype) throw new DomainException('EDM_NAMESPACE_XML_INVALID');
        $root=$doc->documentElement;
        foreach (self::NS as $prefix=>$uri) if ($root->lookupNamespaceURI($prefix)!==$uri) throw new DomainException('EDM_ROOT_NAMESPACE_MISSING');
        // Exact tag shape required by the provider fault and supplied sample.
        foreach (['AccountingSupplierParty','AccountingCustomerParty'] as $name) {
            $matches=0;
            foreach ($root->childNodes as $node) if ($node instanceof DOMElement && $node->namespaceURI===self::NS['cac'] && $node->localName===$name) {
                if ($node->nodeName!=='cac:'.$name) throw new DomainException('EDM_PARTY_PREFIX_INVALID');
                $matches++;
            }
            if ($matches!==1 || substr_count($xml,'<cac:'.$name.'>')!==1) throw new DomainException('EDM_PARTY_LEXICAL_TAG_MISSING');
        }
    }
}
