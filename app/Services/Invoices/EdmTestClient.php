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
            if ($company->length !== 1) throw new RuntimeException('Ambiguous company');
            $row = $company->item(0);
            if ($this->value($row,'VKN') !== $identifier) throw new RuntimeException('Company mismatch');
            $series = [];
            foreach ($row->childNodes as $node) {
                if (!$node instanceof DOMElement || !in_array($node->localName,['SERIALLİST','SERIALLIST'],true)) continue;
                $code = $this->value($node,'SERIAL');
                $year = $this->value($node,'YEAR');
                $active = $this->value($node,'ACTIVEFLAG');
                $archive = $this->value($node,'EARCHIVEFLAG');
                $internet = $this->value($node,'INTERNETFLAG');
                if (!preg_match('/^[A-Z0-9]{3}$/D',$code) || !preg_match('/^20[0-9]{2}$/D',$year)
                    || !in_array($active,['0','1'],true) || !in_array($archive,['0','1'],true) || !in_array($internet,['0','1'],true)) {
                    throw new RuntimeException('Invalid series metadata');
                }
                $series[] = ['code'=>$code,'year'=>(int)$year,'active'=>$active==='1','archive'=>$archive==='1','internet'=>$internet==='1'];
            }
            return ['title'=>$this->value($row,'UNVAN'),'gb'=>$this->optionalValue($row,'GB'),'pk'=>$this->optionalValue($row,'PK'),'series'=>$series];
        });
    }

    /** Empty list is distinct from a failed lookup; neither issues a document. */
    public function taxpayer(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $identifier): array
    {
        return $this->read($username,$password,function ($session) use ($identifier) {
            $this->validateIdentifier($identifier);
            $reply = $this->call('CheckUser',$session,['USER'=>['IDENTIFIER'=>$identifier]]);
            $aliases = [];
            foreach ($reply->childNodes as $node) {
                if (!$node instanceof DOMElement) continue;
                if ($node->localName !== 'USER') throw new RuntimeException('Unexpected lookup content');
                if ($this->value($node,'IDENTIFIER') !== $identifier) throw new RuntimeException('Recipient mismatch');
                $unit = $this->value($node,'UNIT');
                $alias = $this->value($node,'ALIAS');
                if (!in_array($unit,['PK','GB'],true) || !preg_match('/^urn:mail:[^\s<>]{1,250}$/D',$alias)) throw new RuntimeException('Invalid alias');
                if ($this->optionalValue($node,'ALIAS_REMOVAL_TIME') !== '') continue;
                if ($unit === 'PK') $aliases[$alias] = $alias;
            }
            return ['aliases'=>array_values($aliases)];
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
        try {
            $reply = $this->call('Login','0',['USER_NAME'=>$username,'PASSWORD'=>$password]);
            $session = $this->value($reply,'SESSION_ID');
            if ($session === '' || $session === '0' || strlen($session)>256) {
                $session = null;
                throw new RuntimeException('Invalid session');
            }
            $result = ['status'=>'ok','data'=>$operation($session)];
        } catch (Throwable) {
            // Never persist raw provider errors or personal identifiers.
        } finally {
            if ($session !== null) {
                try {
                    $reply = $this->call('Logout',$session);
                    if ($this->value($reply,'RETURN_CODE') !== '0') throw new RuntimeException('Logout failed');
                } catch (Throwable) {
                    $result = ['status'=>'logout_failed','data'=>null];
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
        if (!in_array($method, ['Login','CheckCounter','Logout','GetCompany','CheckUser'], true)) throw new RuntimeException('Unsupported operation');
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
            'REASON'=>'NEWTIC read-only connection check',
            'APPLICATION_NAME'=>'NEWTIC Commerce',
            'HOSTNAME'=>'NEWTIC',
            'CHANNEL_NAME'=>'TEST',
            'COMPRESSED'=>'N',
        ] as $key=>$value) $this->append($doc,$header,$key,$value);
        foreach ($fields as $key=>$value) {
            if (is_array($value)) {
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
        if (!$response->successful()) throw new RuntimeException('EDM HTTP failure');
        $xml = $response->body();
        if (strlen($xml)>1048576 || preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new RuntimeException('Unsafe XML');
        $reply = new DOMDocument();
        if (!@$reply->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) || $reply->doctype) throw new RuntimeException('Invalid XML');
        $xpath = new DOMXPath($reply);
        $xpath->registerNamespace('s',self::SOAP);
        $xpath->registerNamespace('edm',self::SERVICE);
        $nodes = $xpath->query('/s:Envelope/s:Body/edm:'.$method.'Response');
        if ($nodes === false || $nodes->length !== 1 || $xpath->query('/s:Envelope/s:Body/s:Fault')->length !== 0) throw new RuntimeException('Unexpected SOAP response');
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
        if ($nodes->length !== 1) throw new RuntimeException('Missing EDM field');
        return trim($nodes->item(0)->textContent);
    }
}
