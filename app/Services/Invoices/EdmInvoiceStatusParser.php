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

    /** Outgoing states from the EDM e-Fatura document-status table.
     * Inbound ACCEPT/REJECT are intentionally not mapped as outgoing acceptance.
     */
    public const PORTAL_STATES=[
        'draft','packaging','package_failed','sending','waiting_gib','waiting_system',
        'send_failed','send_succeeded','waiting_application','accepted_succeeded',
        'rejected_succeeded','cancelled_succeeded','unknown',
    ];

    private function portalState(string $status,string $profile): string
    {
        // Documented examples vary in whitespace around the hyphen.
        $status=preg_replace('/\s*-\s*/',' - ',trim($status));
        return match ($status) {
            'DRAFT'=>'draft',
            'PACKAGE - PROCESSING'=>'packaging',
            'PACKAGE - FAIL'=>'package_failed',
            'SEND - PROCESSING'=>'sending',
            'SEND - WAIT_GIB_RESPONSE'=>'waiting_gib',
            'SEND - WAIT_SYSTEM_RESPONSE'=>'waiting_system',
            'SEND - FAILED'=>'send_failed',
            'SEND - SUCCEED'=>'send_succeeded',
            'SEND - WAIT_APPLICATION_RESPONSE'=>$profile==='TICARIFATURA'?'waiting_application':'unknown',
            'ACCEPTED - SUCCEED'=>$profile==='TICARIFATURA'?'accepted_succeeded':'unknown',
            'REJECTED - SUCCEED'=>$profile==='TICARIFATURA'?'rejected_succeeded':'unknown',
            'CANCELLED - SUCCEED'=>$profile==='EARSIVFATURA'?'cancelled_succeeded':'unknown',
            default=>'unknown',
        };
    }

    public function parse(DOMElement $reply,string $uuid,string $profile): array
    {
        if (!in_array($profile,['TEMELFATURA','TICARIFATURA','EARSIVFATURA'],true)) throw new EdmLookupFailure('STATUS_PROFILE_INVALID');
        $rows=$this->children($reply,'INVOICE_STATUS');
        if (count($rows)!==1) throw new EdmLookupFailure(count($rows)===0?'STATUS_EMPTY':'STATUS_AMBIGUOUS');
        $row=$rows[0];
        if (strtolower($row->getAttribute('UUID'))!==strtolower($uuid)) throw new EdmLookupFailure('STATUS_UUID_MISMATCH');
        if (!in_array($this->value($row,'DIRECTION',true),['Giden','OUT'],true)) throw new EdmLookupFailure('STATUS_DIRECTION_MISMATCH');
        $headers=$this->children($row,'HEADER');
        if (count($headers)!==1) throw new EdmLookupFailure('STATUS_HEADER_COUNT');
        $archive=$this->value($headers[0],'EARCHIVE',true);
        if (!in_array($archive,['true','false','1','0'],true)
            || in_array($archive,['true','1'],true)!==($profile==='EARSIVFATURA')) throw new EdmLookupFailure('STATUS_ARCHIVE_MISMATCH');
        $reportedProfile=$this->value($headers[0],'PROFILEID');
        if ($reportedProfile!==null && strtoupper($reportedProfile)!==$profile) throw new EdmLookupFailure('STATUS_PROFILE_MISMATCH');
        $id=$row->getAttribute('ID');
        // A missing/invalid/sentinel ID is not a confirmed fiscal number.
        $number=preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D',$id) && $id!==UblSaleCandidate::EDM_SERIAL_SENTINEL ? $id : null;
        $status=$this->value($row,'STATUS',true);
        $portal=$this->portalState($status,$profile);
        // Only bounded technical tokens, not descriptions or arbitrary SOAP fields.
        $token=preg_replace('/\s*-\s*/',' - ',trim($status));
        $token=is_string($token)&&strlen($token)<=64&&preg_match('/^[A-Z][A-Z0-9_]*(?: - [A-Z0-9_]+)*$/D',$token)?$token:null;
        $gib=$this->value($row,'GIB_STATUS_CODE');
        $specified=$this->value($row,'GIB_STATUS_CODESpecified');
        if ($specified!==null && !in_array($specified,['true','false','1','0'],true)) throw new EdmLookupFailure('STATUS_GIB_SPECIFIED_INVALID');
        if (in_array($specified,['false','0'],true)) $gib=null;
        if ($gib!==null && !preg_match('/^[0-9]{4}$/D',$gib)) throw new EdmLookupFailure('STATUS_GIB_CODE_INVALID');
        $response=$this->value($row,'RESPONSE_CODE');
        $commercial=$profile==='TICARIFATURA' ? match ($response) {
            'ACCEPT'=>'accepted',
            'REJECT','RED'=>'rejected',
            null=>'not_reported',
            default=>'unknown',
        } : 'not_applicable';
        // A reported response and portal state can disagree. Do not collapse
        // conflicting evidence or infer a commercial response from portal status.
        if (($portal==='accepted_succeeded' && $commercial==='rejected')
            || ($portal==='rejected_succeeded' && $commercial==='accepted')) $commercial='conflict';
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
            'portal_token'=>$token,
            'gib'=>EdmInvoiceOutcome::classify('gib',$gib??''),
            'commercial_response'=>$commercial,
            'archive_report'=>$archiveReport,
            'archive_cancel_report'=>$archiveCancelReport,
            'automatic_retry'=>false,
            'fiscal_success_confirmed'=>false,
        ];
    }
}
