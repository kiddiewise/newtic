<?php

namespace App\Services\Invoices;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DomainException;

/**
 * Offline LoadInvoice SOAP contract rehearsal for ordinary e-Invoice candidates.
 * This deliberately has no HTTP transport, real session parameter or fiscal
 * number allocation. It must never be posted to EDM. Archive routing remains
 * a separate gate.
 */
final class EdmLoadInvoiceRehearsal
{
    private const SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const EDM = 'http://tempuri.org/';
    private const OFFLINE_SESSION = 'NEWTIC-OFFLINE-NOT-A-SESSION';

    private function node(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $child = $parent->appendChild($parent->ownerDocument->createElement($name));
        if ($value !== null) $child->appendChild($parent->ownerDocument->createTextNode($value));
        return $child;
    }

    public function build(
        #[\SensitiveParameter] string $xml,
        #[\SensitiveParameter] array $snapshot,
        string $uuid,
        string $profile,
        #[\SensitiveParameter] string $senderAlias,
        #[\SensitiveParameter] string $receiverAlias,
        \DateTimeInterface $actionDate
    ): array {
        if (!in_array($profile, ['TEMELFATURA','TICARIFATURA'], true)) {
            throw new DomainException('Çevrimdışı LoadInvoice provası yalnızca temel ve ticari faturayı destekler.');
        }
        foreach ([$senderAlias,$receiverAlias] as $alias) {
            if (!preg_match('/^urn:mail:[^\s<>]{1,250}$/Di', $alias)) throw new DomainException('EDM etiket biçimi geçersiz.');
        }
        (new UblCandidatePreflight)->validate($xml, $snapshot, $uuid, $profile);
        $source = new DOMDocument();
        if (!$source->loadXML($xml, LIBXML_NONET)) throw new DomainException('XML okunamadı.');
        $xp = new DOMXPath($source);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $sender = $xp->evaluate('string(/i:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID)');
        $receiver = $xp->evaluate('string(/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID)');

        $doc = new DOMDocument('1.0','UTF-8');
        $env = $doc->appendChild($doc->createElementNS(self::SOAP,'s:Envelope'));
        $body = $env->appendChild($doc->createElementNS(self::SOAP,'s:Body'));
        $op = $body->appendChild($doc->createElementNS(self::EDM,'edm:LoadInvoiceRequest'));
        $header = $this->node($op,'REQUEST_HEADER');
        // CLIENT_TXN_ID is stable per candidate for correlation only; never
        // treat it as EDM-side idempotency or a substitute for a durable ledger.
        foreach ([
            'SESSION_ID'=>self::OFFLINE_SESSION,
            'CLIENT_TXN_ID'=>$uuid,
            'ACTION_DATE'=>$actionDate->format('Y-m-d\TH:i:sP'),
            'REASON'=>'NEWTIC offline contract rehearsal - DO NOT TRANSMIT',
            'APPLICATION_NAME'=>'NEWTIC Commerce',
            'HOSTNAME'=>'NEWTIC',
            'CHANNEL_NAME'=>'TEST',
            'COMPRESSED'=>'N',
        ] as $key=>$value) $this->node($header,$key,$value);
        // Ordering follows the supplied Postman LoadInvoice request example.
        $invoice = $this->node($op,'INVOICE');
        $invoice->setAttribute('TRXID','0');
        $invoice->setAttribute('UUID',$uuid);
        $invoice->setAttribute('ID',UblSaleCandidate::EDM_SERIAL_SENTINEL);
        $invoiceHeader = $this->node($invoice,'HEADER');
        foreach ([
            'SENDER'=>$sender,'RECEIVER'=>$receiver,'FROM'=>$senderAlias,'TO'=>$receiverAlias,
            'INTERNETSALES'=>'false','EARCHIVE'=>'false',
            'EARCHIVE_REPORT_SENDDATE'=>'0001-01-01','CANCEL_EARCHIVE_REPORT_SENDDATE'=>'0001-01-01',
        ] as $key=>$value) $this->node($invoiceHeader,$key,$value);
        $this->node($invoice,'CONTENT',base64_encode($xml));
        foreach (['SENDER'=>[$sender,$senderAlias],'RECEIVER'=>[$receiver,$receiverAlias]] as $name=>[$id,$alias]) {
            $party = $this->node($op,$name);
            $party->setAttribute('vkn',$id);
            $party->setAttribute('alias',$alias);
        }
        $this->node($op,'GENERATEINVOICEIDONLOAD','false');
        $soap = $doc->saveXML();
        if ($soap === false) throw new DomainException('SOAP taslağı oluşturulamadı.');
        return [
            'soap_xml'=>$soap,
            'uuid'=>$uuid,
            'content_sha256'=>hash('sha256',$xml),
            'operation'=>'LoadInvoice',
            'submission_allowed'=>false,
        ];
    }
}
