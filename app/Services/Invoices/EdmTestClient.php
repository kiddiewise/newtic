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

    private function call(string $method, #[\SensitiveParameter] string $session, #[\SensitiveParameter] array $fields = []): DOMElement
    {
        if (!in_array($method, ['Login','CheckCounter','Logout'], true)) throw new RuntimeException('Unsupported operation');
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
        foreach ($fields as $key=>$value) $this->append($doc,$operation,$key,$value);

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
