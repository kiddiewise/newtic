<?php

namespace App\Services\Invoices;

use DOMElement;
use DOMXPath;

/**
 * Categorize documented REQUEST_ERRORType descriptions. Never return
 * provider free text from ordinary lookup methods. Controlled LoadInvoice
 * can retain a bounded encrypted private copy for operator diagnosis.
 * Hints are not causes.
 */
final class EdmFaultHints
{
    private static function descriptions(DOMElement $fault): array
    {
        $xp=new DOMXPath($fault->ownerDocument);
        $descriptions=[];
        foreach (['ERROR_SHORT_DES','ERROR_LONG_DES'] as $name) {
            $nodes=$xp->query('.//*[local-name()="'.$name.'"]',$fault);
            if ($nodes===false || $nodes->length!==1) continue;
            $node=$nodes->item(0);
            if ($node->namespaceURI!==null && $node->namespaceURI!=='') continue;
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) continue 2;
            }
            $value=trim($node->textContent);
            if (strlen($value)>16384) continue;
            if ($value!=='') $descriptions[$name]=$value;
        }
        return $descriptions;
    }

    /** Keep only the two documented descriptions; never stacktrace, SOAP,
     * transaction headers or session identifier fields. Encrypt before
     * returning anything to the transport layer. No plaintext logs. */
    public static function encryptedDetails(DOMElement $fault, #[\SensitiveParameter] string $session): ?string
    {
        $descriptions=self::descriptions($fault);
        if (!$descriptions) return null;
        foreach ($descriptions as &$text) {
            if ($session!=='' && $session!=='0') $text=str_replace($session,'[REDACTED_SESSION]',$text);
            $text=preg_replace('~(?i)(session[\s_-]*id|password|şifre|parola|authorization|bearer|user[\s_-]*name)\s*[:=]\s*[^;\r\n,]+~u','$1: [REDACTED]',$text)??'[REDACTED]';
        }
        unset($text);
        return \Illuminate\Support\Facades\Crypt::encryptString(json_encode($descriptions,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }

    public static function inspect(DOMElement $fault): array
    {
        $text=implode(' ',self::descriptions($fault));
        if (trim($text)==='') return ['description_unavailable'];
        // Fixed vocabulary only; a match is a clue, not proof of root cause.
        $patterns=[
            'schema_or_xml'=>'~\b(?:schema|schematron|xsd|xml|ubl|extensioncontent)\b|şema|sematron~iu',
            'signature'=>'~\b(?:signature|xades|signing)\b|imza|mali mühür~iu',
            'number_or_series'=>'~\b(?:invoice.?serial|serial|invoice.?id|generateinvoiceidonload)\b|seri|numara~iu',
            'routing_or_identity'=>'~\b(?:sender|receiver|alias|vkn|tckn|taxnumber)\b|gönderici|alıcı|etiket|posta kutusu~iu',
            'permission'=>'~\b(?:permission|unauthorized|forbidden|authorization)\b|yetki|izin~iu',
            'profile_or_type'=>'~\b(?:profileid|invoicetypecode|earsivfatura|temelfatura|ticarifatura)\b|senaryo|belge türü~iu',
            'duplicate'=>'~\b(?:duplicate|already exists)\b|mükerrer|zaten mevcut|daha önce~iu',
            'date'=>'~\b(?:issuedate|date)\b|tarih~iu',
            'amount_or_tax'=>'~\b(?:tax|kdv|payableamount|amount)\b|tutar|vergi~iu',
        ];
        $hints=[];
        foreach ($patterns as $hint=>$pattern) if (preg_match($pattern,$text)===1) $hints[]=$hint;
        return $hints?:['description_unclassified'];
    }
}
