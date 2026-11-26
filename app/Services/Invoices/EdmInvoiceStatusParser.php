<?php

namespace App\Services\Invoices;

use DOMElement;

/** Supplied EDM v4.2.1 §5.7 and Request-Response §1.5.
 * Never returns untrusted descriptions, customer fields, or raw SOAP.
 */
final class EdmInvoiceStatusParser
{
    private function children(DOMElement $parent,string $name): array
    {
        $matches=[];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName===$name) {
                if ($node->namespaceURI!==null && $node->namespaceURI!=='') throw new EdmLookupFailure('STATUS_FIELD_NAMESPACE');
                $matches[]=$node;
            }
        }
        return $matches;
    }

    private function value(DOMElement $parent,string $name,bool $required=false): ?string
    {
        $nodes=$this->children($parent,$name);
        if (count($nodes)>1 || ($required && count($nodes)!==1)) throw new EdmLookupFailure('STATUS_FIELD_COUNT_'.$name);
        if (!$nodes) return null;
        foreach ($nodes[0]->childNodes as $child) {
            if ($child instanceof DOMElement) throw new EdmLookupFailure('STATUS_FIELD_SHAPE');
        }
        $text=trim($nodes[0]->textContent);
        if ($required && $text==='') throw new EdmLookupFailure('STATUS_FIELD_EMPTY_'.$name);
        return $text===''?null:$text;
    }

    public function parse(DOMElement $reply,string $uuid,string $profile): array
    {
        if (!in_array($profile,['TEMELFATURA','TICARIFATURA','EARSIVFATURA'],true)) throw new EdmLookupFailure('STATUS_PROFILE_INVALID');
        $rows=$this->children($reply,'INVOICE_STATUS');
        if (count($rows)!==1) throw new EdmLookupFailure(count($rows)===0?'STATUS_EMPTY':'STATUS_AMBIGUOUS');
        $row=$rows[0];
        if (strtolower($row->getAttribute('UUID'))!==strtolower($uuid)) throw new EdmLookupFailure('STATUS_UUID_MISMATCH');
        if ($this->value($row,'DIRECTION',true)!=='Giden') throw new EdmLookupFailure('STATUS_DIRECTION_MISMATCH');
        $headers=$this->children($row,'HEADER');
        if (count($headers)!==1) throw new EdmLookupFailure('STATUS_HEADER_COUNT');
        $archive=$this->value($headers[0],'EARCHIVE',true);
        if (!in_array($archive,['true','false','1','0'],true)
            || in_array($archive,['true','1'],true)!==($profile==='EARSIVFATURA')) throw new EdmLookupFailure('STATUS_ARCHIVE_MISMATCH');
        $id=$row->getAttribute('ID');
        // A missing/invalid/sentinel ID is not a confirmed fiscal number.
        $number=preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D',$id) && $id!==UblSaleCandidate::EDM_SERIAL_SENTINEL ? $id : null;
        $status=$this->value($row,'STATUS',true);
        // Only documented examples are surfaced as specific portal states.
        $portal=match ($status) {
            'SEND - SUCCEED'=>'send_succeeded',
            'ACCEPT- SUCCEED','ACCEPT - SUCCEED'=>'accept_succeeded',
            default=>'unknown',
        };
        $gib=$this->value($row,'GIB_STATUS_CODE');
        if ($gib!==null && !preg_match('/^[0-9]{4}$/D',$gib)) throw new EdmLookupFailure('STATUS_GIB_CODE_INVALID');
        $response=$this->value($row,'RESPONSE_CODE');
        $commercial=$profile==='TICARIFATURA' ? match ($response) {
            'ACCEPT'=>'accepted',
            'REJECT','RED'=>'rejected',
            null=>'not_reported',
            default=>'unknown',
        } : 'not_applicable';
        // Archive reporting fields have separate semantics; do not infer
        // successful reporting from a portal status or envelope code.
        $archiveReport=$profile==='EARSIVFATURA' ?
            ($this->value($row,'EARCHIVE_REPORT_STATUS')===null?'not_reported':'uninterpreted') : 'not_applicable';
        $archiveCancelReport=$profile==='EARSIVFATURA' ?
            ($this->value($row,'EARCHIVE_CANCEL_REPORT_STATUS')===null?'not_reported':'uninterpreted') : 'not_applicable';
        return [
            'uuid'=>strtolower($uuid),
            'invoice_number'=>$number,
            'portal_state'=>$portal,
            'gib'=>EdmInvoiceOutcome::classify('gib',$gib??''),
            'commercial_response'=>$commercial,
            'archive_report'=>$archiveReport,
            'archive_cancel_report'=>$archiveCancelReport,
            'automatic_retry'=>false,
            'fiscal_success_confirmed'=>false,
        ];
    }
}
