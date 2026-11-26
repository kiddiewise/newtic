<?php

namespace App\Services\Invoices;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * EDM SOAP 1.1 contract, supplied Request-Response examples §§1.1, 1.2, 1.12.
 * Deliberately no live URL, invoice mutation, retry, or raw response logging.
 */
final class EdmTestClient
{
    public const ENDPOINT = 'https://test.edmbilisim.com.tr/EFaturaEDM21ea/EFaturaEDM.svc';
    private const SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const SERVICE = 'http://tempuri.org/';

    public function probe(string $username, #[\SensitiveParameter] string $password): array
    {
        $session = null;
        $result = ['status'=>'failed','counter'=>null];
        try {
            $response = $this->call('Login', '0', ['USER_NAME'=>$username,'PASSWORD'=>$password]);
            $session = $this->value($response, 'SESSION_ID');
            if ($session === '' || $session === '0' || strlen($session) > 256) {
                $session = null;
                throw new RuntimeException('Invalid session response');
            }
            $result = ['status'=>'counter_failed','counter'=>null];
            $response = $this->call('CheckCounter', $session);
            $counter = $this->value($response, 'COUNTER_LEFT');
            if (!preg_match('/^-?[0-9]{1,10}$/D', $counter) || (float)$counter < -2147483648 || (float)$counter > 2147483647) {
                throw new RuntimeException('Invalid counter response');
            }
            $result = ['status'=>'ok','counter'=>(int)$counter];
        } catch (Throwable) {
            // The provider may echo credentials, tokens or identities in faults.
            // Do not report or persist raw exceptions, request XML, or responses.
        } finally {
            if ($session !== null) {
                try {
                    $response = $this->call('Logout', $session);
                    $returns = $response->getElementsByTagName('RETURN_CODE');
                    if ($returns->length !== 1 || trim($returns->item(0)->textContent) !== '0') {
                        throw new RuntimeException('Logout not confirmed');
                    }
                } catch (Throwable) {
                    if ($result['status'] === 'ok') $result['status'] = 'logout_failed';
                }
            }
        }
        return $result;
    }

    /** Read-only company metadata, filtered to fiscal routing fields. */
    public function company(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $identifier): array
    {
        return $this->read($username,$password,function ($session) use ($username,$password,$identifier) {
            $this->validateIdentifier($identifier);
            $reply = $this->call('GetCompany',$session,['USER_NAME'=>$username,'PASSWORD'=>$password,'TAXNUMBER'=>$identifier]);
            $company = $reply->getElementsByTagName('GetCompanyList');
            $matches = [];
            foreach ($company as $candidate) {
                if ($this->optionalValue($candidate,'VKN') === $identifier) $matches[] = $candidate;
            }
            if (count($matches) !== 1) throw new EdmLookupFailure($company->length === 0 ? 'COMPANY_EMPTY' : 'COMPANY_MISMATCH');
            $row = $matches[0];
            $series = [];
            $unreadableSeries = 0;
            foreach ($row->childNodes as $node) {
                if (!$node instanceof DOMElement || !in_array($node->localName,['SERIALLİST','SERIALLIST'],true)) continue;
                $code = $this->optionalValue($node,'SERIAL');
                $year = $this->optionalValue($node,'YEAR');
                $active = $this->optionalValue($node,'ACTIVEFLAG');
                $archive = $this->optionalValue($node,'EARCHIVEFLAG');
                $internet = $this->optionalValue($node,'INTERNETFLAG');
                if (!preg_match('/^[A-Z0-9]{3}$/D',$code) || !preg_match('/^20[0-9]{2}$/D',$year)
                    || !in_array($active,['0','1'],true) || !in_array($archive,['0','1'],true) || !in_array($internet,['0','1'],true)) {
                    $unreadableSeries++;
                    continue;
                }
                $series[] = ['code'=>$code,'year'=>(int)$year,'active'=>$active==='1','archive'=>$archive==='1','internet'=>$internet==='1'];
            }
            return ['title'=>$this->value($row,'UNVAN'),'gb'=>$this->optionalValue($row,'GB'),'pk'=>$this->optionalValue($row,'PK'),'series'=>$series,'unreadable_series'=>$unreadableSeries];
        });
    }

    /** Empty list is distinct from a failed lookup; neither issues a document. */
    public function taxpayer(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $identifier): array
    {
        return $this->read($username,$password,function ($session) use ($identifier) {
            $this->validateIdentifier($identifier);
            $reply = $this->call('CheckUser',$session,['USER'=>['IDENTIFIER'=>$identifier]]);
            $aliases = [];
            $senderAliases = [];
            $warnings = 0;
            $matched = 0;
            $rows = [];
            foreach ($reply->childNodes as $node) {
                if (!$node instanceof DOMElement) continue;
                if ($node->localName === 'USER') {
                    $rows[] = $node;
                } elseif ($node->localName === 'REQUEST_RETURN') {
                    if ($this->value($node,'RETURN_CODE') !== '0') throw new EdmLookupFailure('CHECKUSER_RETURN_FAILED');
                } else {
                    throw new EdmLookupFailure('CHECKUSER_UNEXPECTED_RESPONSE_ELEMENT');
                }
            }
            foreach ($rows as $node) {
                $id = $this->optionalValue($node,'IDENTIFIER');
                if ($id !== $identifier) { $warnings++; continue; }
                $matched++;
                $unit = strtoupper($this->optionalValue($node,'UNIT'));
                $alias = $this->optionalValue($node,'ALIAS');
                $removed = $this->optionalValue($node,'ALIAS_REMOVAL_TIME');
                if ($removed !== '' && !preg_match('/^0001-01-01T00:00:00(?:\.0+)?(?:Z|[+-]00:00)?$/D',$removed)) continue;
                if (!in_array($unit,['PK','GB'],true) || !preg_match('/^urn:mail:[^\s<>]{1,250}$/Di',$alias)) { $warnings++; continue; }
                if ($unit === 'PK') $aliases[$alias] = $alias;
                if ($unit === 'GB') $senderAliases[$alias] = $alias;
            }
            if ($rows && !$matched) throw new EdmLookupFailure('CHECKUSER_IDENTIFIER_MISMATCH');
            if ($warnings && !$aliases && !$senderAliases) throw new EdmLookupFailure('CHECKUSER_NO_VALID_ALIAS');
            $data = ['aliases'=>array_values($aliases),'sender_aliases'=>array_values($senderAliases)];
            if ($warnings) $data['unverified_records'] = $warnings;
            return $data;
        });
    }


    /**
     * Read-only sandbox lookup of one outgoing invoice by exact UUID.
     * EDM portal, GIB envelope and commercial response are independent.
     * No result permits a retry or marks a local invoice as issued.
     */
    public function invoiceStatus(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, string $uuid, string $profile): array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',$uuid)
            || !in_array($profile,['TEMELFATURA','TICARIFATURA','EARSIVFATURA'],true)) {
            return ['status'=>'failed','data'=>null,'diagnostic'=>'Lookup: INVALID_STATUS_QUERY'];
        }
        $result=$this->read($username,$password,function ($session) use ($uuid,$profile) {
            $reply=$this->call('GetInvoiceStatus',$session,['INVOICE'=>['UUID'=>$uuid]]);
            return (new EdmInvoiceStatusParser)->parse($reply,$uuid,$profile);
        });
        if ($result['status']!=='ok') {
            $diagnostic=$result['diagnostic']??'';
            $code=preg_match('/^Lookup: EDM_([0-9]{4,6})$/D',$diagnostic,$m) ? $m[1] : '';
            $result['outcome']=EdmInvoiceOutcome::classify($code!==''?'edm':'transport',$code);
        }
        return $result;
    }


    /**
     * Draft loading only, on the hard-coded sandbox. No service binding for
     * EdmSandboxLoadPolicy exists; current unsigned candidates always fail.
     */
    public function loadSandboxDraft(int $preparationId, #[\SensitiveParameter] string $xml, string $profile,
        #[\SensitiveParameter] string $senderAlias, #[\SensitiveParameter] string $receiverAlias,
        EdmSandboxLoadPolicy $policy, EdmSubmissionJournal $journal): array
    {
        $draft=\App\Models\InvoicePreparation::query()->findOrFail($preparationId);
        $account=\App\Models\EdmSetting::query()->findOrFail(1);
        if (!$account->configured() || !$account->revision || $account->probe_status!=='ok') {
            throw new \DomainException('EDM test hesabı doğrulanmamış.');
        }
        $fields=EdmSandboxLoadPayload::fields($xml,$draft->uuid,$profile,$senderAlias,$receiverAlias);
        // Required real policy is deliberately absent; no bool/env bypass.
        $policy->authorize($draft,$account,$xml,$profile,$senderAlias,$receiverAlias);
        $digest=hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR));
        $attempt=$journal->claim($draft->id,$profile,$digest,$account->revision);
        $result=$this->read($account->username,$account->password,function ($session) use ($fields,$draft,$account) {
            if (!\App\Models\EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->exists()) {
                throw new EdmLookupFailure('LOAD_ACCOUNT_CHANGED');
            }
            $reply=$this->call('LoadInvoice',$session,$fields);
            $returns=$reply->getElementsByTagName('REQUEST_RETURN');
            $invoices=$reply->getElementsByTagName('INVOICE');
            if ($returns->length!==1 || $this->value($returns->item(0),'RETURN_CODE')!=='0'
                || $invoices->length!==1 || strtolower($invoices->item(0)->getAttribute('UUID'))!==strtolower($draft->uuid)) {
                throw new EdmLookupFailure('LOAD_ACK_UNCONFIRMED');
            }
            return ['uuid'=>$draft->uuid,'draft_acknowledged'=>true];
        });
        $ack=$result['status']==='ok' && ($result['data']['draft_acknowledged']??false)===true;
        $journal->recordLoadObservation($attempt->id,$account->revision,$ack);
        // Acknowledgement is not issue, GIB delivery, or commercial acceptance.
        return ['attempt_id'=>$attempt->id,'uuid'=>$attempt->uuid,
            'load_status'=>$ack?'draft_acknowledged':'unconfirmed',
            'automatic_retry'=>false,'fiscal_success_confirmed'=>false];
    }

    /**
     * Read one outgoing document by ETTN using EDM GetInvoice.
     * Return bounded evidence only; exact XML bytes are not exported here.
     */
    public function invoiceXmlEvidence(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password,
        string $uuid,string $profile,#[\SensitiveParameter] string $seller,#[\SensitiveParameter] string $buyer): array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',$uuid)
            || !in_array($profile,['TEMELFATURA','TICARIFATURA','EARSIVFATURA'],true)
            || !preg_match('/^[0-9]{10,11}$/D',$seller) || $seller==='11111111111'
            || !preg_match('/^[0-9]{10,11}$/D',$buyer) || ($buyer==='11111111111' && $profile!=='EARSIVFATURA')) {
            return ['status'=>'failed','data'=>null,'diagnostic'=>'Lookup: INVALID_XML_QUERY'];
        }
        return $this->read($username,$password,function ($session) use ($uuid,$profile,$seller,$buyer) {
            // C# *Specified properties determine serialization of their value
            // fields, not additional XML elements. Match documented SOAP.
            $reply=$this->call('GetInvoice',$session,[
                'INVOICE_SEARCH_KEY'=>[
                    'LIMIT'=>'2','UUID'=>$uuid,'SENDER'=>$seller,'RECEIVER'=>$buyer,
                    'READ_INCLUDED'=>'true','DIRECTION'=>$profile==='EARSIVFATURA'?'OUT-EARCHIVE':'OUT-EINVOICE',
                    'ISARCHIVED'=>'true',
                ],
                'HEADER_ONLY'=>'N','INVOICE_CONTENT_TYPE'=>'XML',
            ]);
            return (new EdmInvoiceXmlEvidence)->parse($reply,$uuid,$profile,$seller,$buyer);
        });
    }

    private function validateIdentifier(#[\SensitiveParameter] string $identifier): void
    {
        if (!preg_match('/^[0-9]{10,11}$/D',$identifier) || $identifier === '11111111111') throw new RuntimeException('Invalid lookup identifier');
    }

    private function read(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, callable $operation): array
    {
        $session = null;
        $result = ['status'=>'failed','data'=>null];
        $stage = 'Login';
        try {
            $reply = $this->call('Login','0',['USER_NAME'=>$username,'PASSWORD'=>$password]);
            $session = $this->value($reply,'SESSION_ID');
            if ($session === '' || $session === '0' || strlen($session)>256) {
                $session = null;
                throw new RuntimeException('Invalid session');
            }
            $stage = 'Lookup';
            $result = ['status'=>'ok','data'=>$operation($session)];
        } catch (EdmLookupFailure $failure) {
            $result['diagnostic'] = $stage.': '.$failure->safeCode;
        } catch (\Illuminate\Http\Client\ConnectionException) {
            $result['diagnostic'] = $stage.': NETWORK_TIMEOUT_OR_CONNECTION';
        } catch (Throwable) {
            $result['diagnostic'] = $stage.': RESPONSE_PROCESSING_FAILED';
        } finally {
            if ($session !== null) {
                try {
                    $reply = $this->call('Logout',$session);
                    if ($this->value($reply,'RETURN_CODE') !== '0') throw new RuntimeException('Logout failed');
                } catch (Throwable) {
                    if ($result['status'] === 'ok') $result = ['status'=>'logout_failed','data'=>null,'diagnostic'=>'Logout: NOT_CONFIRMED'];
                }
            }
        }
        return $result;
    }

    private function optionalValue(DOMElement $element, string $name): string
    {
        $nodes = $element->getElementsByTagName($name);
        if ($nodes->length === 0) return '';
        return $this->value($element,$name);
    }

    private function call(string $method, #[\SensitiveParameter] string $session, #[\SensitiveParameter] array $fields = []): DOMElement
    {
        if (!in_array($method, ['Login','CheckCounter','Logout','GetCompany','CheckUser','GetInvoiceStatus','GetInvoice','LoadInvoice'], true)) throw new RuntimeException('Unsupported operation');
        $doc = new DOMDocument('1.0','UTF-8');
        $envelope = $doc->appendChild($doc->createElementNS(self::SOAP,'s:Envelope'));
        $envelope->appendChild($doc->createElementNS(self::SOAP,'s:Header'));
        $body = $envelope->appendChild($doc->createElementNS(self::SOAP,'s:Body'));
        // Prefixed operation, unqualified children as shown in EDM samples.
        $operation = $body->appendChild($doc->createElementNS(self::SERVICE,'edm:'.$method.'Request'));
        $header = $operation->appendChild($doc->createElement('REQUEST_HEADER'));
        foreach ([
            'SESSION_ID'=>$session,
            'CLIENT_TXN_ID'=>(string)Str::uuid(),
            'ACTION_DATE'=>now()->toIso8601String(),
            'REASON'=>$method==='LoadInvoice'?'NEWTIC controlled sandbox draft load':'NEWTIC read-only connection check',
            'APPLICATION_NAME'=>'NEWTIC Commerce',
            'HOSTNAME'=>'NEWTIC',
            'CHANNEL_NAME'=>'TEST',
            'COMPRESSED'=>'N',
        ] as $key=>$value) $this->append($doc,$header,$key,$value);
        foreach ($fields as $key=>$value) {
            if ($method==='LoadInvoice' && $key==='INVOICE') {
                $invoice=$operation->appendChild($doc->createElement('INVOICE'));
                $invoice->setAttribute('TRXID','0');
                $invoice->setAttribute('UUID',$value['UUID']);
                $invoice->setAttribute('ID',$value['ID']);
                $invoiceHeader=$invoice->appendChild($doc->createElement('HEADER'));
                foreach ($value['HEADER'] as $name=>$text) $this->append($doc,$invoiceHeader,$name,$text);
                $this->append($doc,$invoice,'CONTENT',$value['CONTENT']);
            } elseif ($method==='LoadInvoice' && in_array($key,['SENDER','RECEIVER'],true)) {
                $party=$operation->appendChild($doc->createElement($key));
                $party->setAttribute('vkn',$value['vkn']);
                $party->setAttribute('alias',$value['alias']);
            } elseif ($method==='GetInvoiceStatus' && $key==='INVOICE') {
                $invoice=$operation->appendChild($doc->createElement('INVOICE'));
                $invoice->setAttribute('TRXID','0');
                $invoice->setAttribute('UUID',$value['UUID']);
            } elseif (is_array($value)) {
                $parent = $operation->appendChild($doc->createElement($key));
                foreach ($value as $child=>$text) $this->append($doc,$parent,$child,$text);
            } else {
                $this->append($doc,$operation,$key,$value);
            }
        }

        $response = Http::connectTimeout(5)->timeout(12)
            ->withoutRedirecting()
            ->withHeaders(['SOAPAction'=>$method.'Request','Accept'=>'text/xml'])
            ->withBody($doc->saveXML(),'text/xml; charset=utf-8')
            ->post(self::ENDPOINT);
        $xml = $response->body();
        if (strlen($xml)>4194304) throw new EdmLookupFailure('RESPONSE_TOO_LARGE');
        if (preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new EdmLookupFailure('UNSAFE_XML');
        if (trim($xml)==='') throw new EdmLookupFailure('EMPTY_HTTP_'.$response->status());
        $reply = new DOMDocument();
        if (!@$reply->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) || $reply->doctype) throw new EdmLookupFailure('NON_XML_HTTP_'.$response->status());
        $xpath = new DOMXPath($reply);
        $xpath->registerNamespace('s',self::SOAP);
        $xpath->registerNamespace('edm',self::SERVICE);
        $fault = $xpath->query('/s:Envelope/s:Body/s:Fault');
        if ($fault !== false && $fault->length > 0) {
            $codes = $xpath->query('.//*[local-name()="ERROR_CODE"]',$fault->item(0));
            $code = $codes !== false && $codes->length === 1 ? trim($codes->item(0)->textContent) : '';
            throw new EdmLookupFailure(preg_match('/^[0-9]{1,6}$/D',$code) ? 'EDM_'.$code : 'SOAP_FAULT');
        }
        if (!$response->successful()) throw new EdmLookupFailure('HTTP_'.$response->status());
        $nodes = $xpath->query('/s:Envelope/s:Body/edm:'.$method.'Response');
        if ($nodes === false || $nodes->length !== 1 || $xpath->query('/s:Envelope/s:Body/s:Fault')->length !== 0) throw new EdmLookupFailure('UNEXPECTED_SOAP_'.$method.'_RESPONSE');
        return $nodes->item(0);
    }

    private function append(DOMDocument $doc, DOMElement $parent, string $name, string $value): void
    {
        $node = $parent->appendChild($doc->createElement($name));
        $node->appendChild($doc->createTextNode($value));
    }

    private function value(DOMElement $element, string $name): string
    {
        $nodes = $element->getElementsByTagName($name);
        if ($nodes->length !== 1) throw new EdmLookupFailure('FIELD_'.$name.($nodes->length===0 ? '_MISSING' : '_AMBIGUOUS'));
        return trim($nodes->item(0)->textContent);
    }
}
