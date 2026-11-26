<?php

namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\InvoicePreparation;
use App\Models\Order;
use DomainException;

/**
 * Composes local final-draft checks with independent official/provider
 * verification. Neither this policy nor its verification dependency is
 * registered for actual submission until the official rules are available.
 */
final class EdmVerifiedDraftPolicy implements EdmSandboxLoadPolicy
{
    public function __construct(private EdmDraftVerification $verification, private EdmDraftRoutingCheck $routing) {}

    public function authorize(InvoicePreparation $draft, EdmSetting $account,
        #[\SensitiveParameter] string $xml, string $profile,
        #[\SensitiveParameter] string $senderAlias, #[\SensitiveParameter] string $receiverAlias): void
    {
        $snapshot=$draft->amounts;
        if (!is_array($snapshot) || !is_string($draft->snapshot_hash)
            || !hash_equals($draft->snapshot_hash,hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)))
            || ($snapshot['gross_cents']??null)!==$draft->gross_cents) {
            throw new DomainException('Fatura hesap kaydı doğrulanamadı.');
        }
        $order=Order::query()->findOrFail($draft->order_id);
        if (!$order->is_test || $order->currency!=='TRY' || $order->total_cents!==$draft->gross_cents
            || !in_array($order->status,['preparing','shipped','out_for_delivery','completed'],true)
            || !$account->configured() || !$account->revision || $account->probe_status!=='ok') {
            throw new DomainException('EDM test faturası için sipariş veya hesap uygun değil.');
        }
        (new UblCandidatePreflight)->validateProviderDraft($xml,$snapshot,$draft->uuid,$profile);
        // Validate the exact SOAP mapping separately; no transformation or
        // removal of unsigned markers is permitted by this policy.
        $fields=EdmSandboxLoadPayload::fields($xml,$draft->uuid,$profile,$senderAlias,$receiverAlias);
        $document=new \DOMDocument();
        if (!@$document->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING) || $document->doctype) {
            throw new DomainException('Belge tarihi doğrulanamadı.');
        }
        $xpath=new \DOMXPath($document);
        $xpath->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $dates=$xpath->query('/i:Invoice/cbc:IssueDate');
        if ($dates->length!==1) throw new DomainException('Belge tarihi belirsiz.');
        $this->routing->verify($account,$fields['SENDER']['vkn'],$fields['RECEIVER']['vkn'],
            $senderAlias,$receiverAlias,$profile,$dates->item(0)->textContent,$fields['INVOICE']['ID']);
        $this->verification->verify($draft,$account,$xml,$profile,$senderAlias,$receiverAlias);
    }
}
