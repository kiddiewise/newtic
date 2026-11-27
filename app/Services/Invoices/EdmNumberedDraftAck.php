<?php

namespace App\Services\Invoices;

use DOMElement;
use DomainException;

/** Exact acknowledgement for numbered second sandbox trial, not fiscal success. */
final class EdmNumberedDraftAck
{
    public static function parse(DOMElement $reply,string $uuid,string $series,int $year): array
    {
        $returns=[]; $invoices=[];
        foreach ($reply->childNodes as $node) {
            if (!$node instanceof DOMElement) continue;
            if ($node->namespaceURI!==null && $node->namespaceURI!=='') throw new DomainException('SECOND_ACK_NAMESPACE');
            if ($node->localName==='REQUEST_RETURN') $returns[]=$node;
            elseif ($node->localName==='INVOICE') $invoices[]=$node;
            else throw new DomainException('SECOND_ACK_UNEXPECTED_FIELD');
        }
        if (count($returns)!==1 || count($invoices)!==1) throw new DomainException('SECOND_ACK_COUNT');
        $codes=$returns[0]->getElementsByTagName('RETURN_CODE');
        if ($codes->length!==1 || trim($codes->item(0)->textContent)!=='0') throw new DomainException('SECOND_ACK_RETURN_CODE');
        $invoice=$invoices[0];
        $id=$invoice->getAttribute('ID');
        if (strtolower($invoice->getAttribute('UUID'))!==strtolower($uuid)
            || !preg_match('/^'.preg_quote($series.(string)$year,'/').'[0-9]{9}$/D',$id)
            || substr($id,7)==='000000000') throw new DomainException('SECOND_ACK_ID_OR_UUID');
        return ['uuid'=>$uuid,'allocated_invoice_id'=>$id,'draft_acknowledged'=>true,'fiscal_success_confirmed'=>false];
    }
}
