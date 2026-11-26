<?php

namespace App\Services\Invoices;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DomainException;

/**
 * Conservative interpreter for SVRL from a trusted, separately verified
 * Schematron processor. Not a Schematron engine, package attestation,
 * or proof of the rules executed. Never include assertion text in output.
 */
final class GibSvrlResult
{
    public static function inspect(#[\SensitiveParameter] string $xml): array
    {
        if ($xml==='' || strlen($xml)>8*1024*1024 || preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) {
            throw new DomainException('Schematron sonuç dosyası geçersiz.');
        }
        $doc=new DOMDocument();
        if (!@$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING) || $doc->doctype) {
            throw new DomainException('Schematron sonucu okunamadı.');
        }
        $root=$doc->documentElement;
        if (!$root instanceof DOMElement || $root->localName!=='schematron-output'
            || $root->namespaceURI!=='http://purl.oclc.org/dsdl/svrl') {
            throw new DomainException('SVRL sonuç biçimi doğrulanamadı.');
        }
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('svrl','http://purl.oclc.org/dsdl/svrl');
        $patterns=$xp->query('/svrl:schematron-output/svrl:active-pattern')->length;
        $fired=$xp->query('/svrl:schematron-output/svrl:fired-rule')->length;
        $failed=$xp->query('//svrl:failed-assert')->length;
        $reports=$xp->query('//svrl:successful-report')->length;
        // Some Schematron rules use reports as warnings or errors. Until
        // the official package's conventions are reviewed, block all reports.
        return ['active_patterns'=>$patterns,'fired_rules'=>$fired,'failed_assertions'=>$failed,
            'reports'=>$reports,'no_findings'=>$patterns>0 && $fired>0 && $failed===0 && $reports===0,
            'official_validation_confirmed'=>false];
    }
}
