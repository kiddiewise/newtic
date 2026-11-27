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
            $seriesReasons = [];
            foreach ($row->childNodes as $node) {
                if (!$node instanceof DOMElement || !in_array($node->localName,['SERIALLİST','SERIALLIST'],true)) continue;
                $code = $this->optionalValue($node,'SERIAL');
                $year = $this->optionalValue($node,'YEAR');
                $active = $this->optionalValue($node,'ACTIVEFLAG');
                $archive = $this->optionalValue($node,'EARCHIVEFLAG');
                $internet = $this->optionalValue($node,'INTERNETFLAG');
                $invalid = [
                    'series_code'=>!preg_match('/^[A-Z0-9]{3}$/D',$code),
                    'series_year'=>!preg_match('/^20[0-9]{2}$/D',$year),
                    'series_active_flag'=>!in_array($active,['0','1'],true),
                    'series_archive_flag'=>!in_array($archive,['0','1'],true),
                    'series_internet_flag'=>!in_array($internet,['0','1'],true),
                ];
                if (in_array(true,$invalid,true)) {
                    $unreadableSeries++;
                    foreach ($invalid as $reason=>$bad) {
                        if ($bad) $seriesReasons[$reason]=($seriesReasons[$reason]??0)+1;
                    }
                    continue;
                }
                $series[] = ['code'=>$code,'year'=>(int)$year,'active'=>$active==='1','archive'=>$archive==='1','internet'=>$internet==='1'];
            }
            $data=['title'=>$this->value($row,'UNVAN'),'gb'=>$this->optionalValue($row,'GB'),'pk'=>$this->optionalValue($row,'PK'),'series'=>$series,'unreadable_series'=>$unreadableSeries];
            if ($seriesReasons) $data['series_warning_reasons']=$seriesReasons;
            return $data;
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
            $recipientReasons = [];
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
                if ($id !== $identifier) {
                    $warnings++;
                    $reason=$id===''?'recipient_identifier_missing':'recipient_identifier_mismatch';
                    $recipientReasons[$reason]=($recipientReasons[$reason]??0)+1;
                    continue;
                }
                $matched++;
                $unit = strtoupper($this->optionalValue($node,'UNIT'));
                $alias = $this->optionalValue($node,'ALIAS');
                $removed = $this->optionalValue($node,'ALIAS_REMOVAL_TIME');
                if ($removed !== '' && !preg_match('/^0001-01-01T00:00:00(?:\.0+)?(?:Z|[+-]00:00)?$/D',$removed)) continue;
                $invalid=['recipient_unit'=>!in_array($unit,['PK','GB'],true),
                    'recipient_alias'=>!preg_match('/^urn:mail:[^\s<>]{1,250}$/Di',$alias)];
                if (in_array(true,$invalid,true)) {
                    $warnings++;
                    foreach ($invalid as $reason=>$bad) {
                        if ($bad) $recipientReasons[$reason]=($recipientReasons[$reason]??0)+1;
                    }
                    continue;
                }
                if ($unit === 'PK') $aliases[$alias] = $alias;
                if ($unit === 'GB') $senderAliases[$alias] = $alias;
            }
            if ($rows && !$matched) throw new EdmLookupFailure('CHECKUSER_IDENTIFIER_MISMATCH');
            if ($warnings && !$aliases && !$senderAliases) throw new EdmLookupFailure('CHECKUSER_NO_VALID_ALIAS');
            $data = ['aliases'=>array_values($aliases),'sender_aliases'=>array_values($senderAliases)];
            if ($warnings) {
                $data['unverified_records'] = $warnings;
                $data['recipient_warning_reasons']=$recipientReasons;
            }
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
    /**
     * Explicit, single-use CLI experiment for the merchant's authorized EDM
     * sandbox. No real order or generic submission gate is relaxed. The
     * exact synthetic XML digest must match the independently checked CI bytes.
     * This only loads an unsigned draft; no number allocation or SendInvoice.
     */
    public function runControlledSmoke(string $date,string $checkedSha256,string $confirmation): array
    {
        $f=EdmSandboxSmokeFixture::class;
        if (PHP_SAPI!=='cli' || rtrim((string)config('app.url'),'/')!==$f::STAGING_URL
            || $confirmation!==$f::CONFIRM || $date!==now('Europe/Istanbul')->toDateString()
            || !preg_match('/^[0-9a-f]{64}$/D',$checkedSha256)
            || \Illuminate\Support\Facades\DB::transactionLevel()!==0) {
            throw new \DomainException('SMOKE_SCOPE_OR_CONFIRMATION_INVALID');
        }
        $db=\Illuminate\Support\Facades\DB::class;
        $existing=$db::table('edm_sandbox_trials')->where('uuid',$f::UUID)->first();
        if ($existing) return ['uuid'=>$f::UUID,'load_status'=>$existing->load_status,'diagnostic'=>$existing->diagnostic,
            'status_query'=>$existing->status_query,'xml_query'=>$existing->xml_query,'already_reserved'=>true,
            'fault_hints'=>json_decode($existing->fault_hints??'[]',true,512,JSON_THROW_ON_ERROR),
            'fiscal_success_confirmed'=>false,'automatic_retry'=>false];
        $xml=$f::xml($date);
        if (!hash_equals($checkedSha256,hash('sha256',$xml))) throw new \DomainException('SMOKE_XML_DIGEST_MISMATCH');
        $fields=$f::fields($date);
        if (!hash_equals($checkedSha256,hash('sha256',base64_decode($fields['INVOICE']['CONTENT'],true)))) {
            throw new \DomainException('SMOKE_REQUEST_DIGEST_MISMATCH');
        }
        $account=\App\Models\EdmSetting::query()->findOrFail(1);
        if (!$account->configured() || !$account->revision || $account->probe_status!=='ok') {
            throw new \DomainException('SMOKE_ACCOUNT_NOT_VERIFIED');
        }
        $preview=(new EdmRoutingPreview($this))->inspect($account,$f::IDENTIFIER,$f::IDENTIFIER,$f::GB,$f::PK,$f::SERIES,(int)substr($date,0,4));
        foreach (['company','sender_alias','series','recipient','recipient_alias'] as $check) {
            if (($preview['checks'][$check]??null)!==true) throw new \DomainException('SMOKE_SELECTED_ROUTE_UNVERIFIED');
        }
        // Merchant explicitly authorized a single isolated sandbox attempt
        // despite one unrelated/unclassified series and recipient warning.
        // Do not generalize this exception to other routes, accounts or orders.
        if (!is_int($preview['unreadable_series']) || !is_int($preview['unverified_records'])
            || $preview['unreadable_series']>1 || $preview['unverified_records']>1) {
            throw new \DomainException('SMOKE_ROUTING_WARNINGS_CHANGED');
        }
        $db::transaction(function () use ($db,$account,$f,$date,$checkedSha256,$fields,$preview) {
            $current=\App\Models\EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
            if (!$current?->configured() || $current->probe_status!=='ok' || $current->revision!==$account->revision) {
                throw new \DomainException('SMOKE_ACCOUNT_CHANGED');
            }
            if ($db::table('edm_sandbox_trials')->where('uuid',$f::UUID)->exists()) throw new \DomainException('SMOKE_ALREADY_RESERVED');
            $db::table('edm_sandbox_trials')->insert([
                'uuid'=>$f::UUID,'account_revision'=>$account->revision,'issue_date'=>$date,
                'xml_sha256'=>$checkedSha256,'request_sha256'=>hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR)),
                'series_warnings'=>$preview['unreadable_series'],'recipient_warnings'=>$preview['unverified_records'],
                'load_status'=>'reserved_unknown','created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
        $currentAccount=static fn()=>\App\Models\EdmSetting::query()->whereKey(1)
            ->where('revision',$account->revision)->where('probe_status','ok')->exists();
        $result=$this->read($account->username,$account->password,function ($session) use ($fields,$f,$currentAccount) {
            if (!$currentAccount()) throw new EdmLookupFailure('SMOKE_ACCOUNT_CHANGED');
            $reply=$this->call('LoadInvoice',$session,$fields);
            $returns=[]; $invoices=[];
            foreach ($reply->childNodes as $node) {
                if (!$node instanceof DOMElement) continue;
                if ($node->namespaceURI!==null && $node->namespaceURI!=='') throw new EdmLookupFailure('SMOKE_ACK_NAMESPACE');
                if ($node->localName==='REQUEST_RETURN') $returns[]=$node;
                elseif ($node->localName==='INVOICE') $invoices[]=$node;
                else throw new EdmLookupFailure('SMOKE_ACK_UNEXPECTED_FIELD');
            }
            if (count($returns)!==1 || $this->value($returns[0],'RETURN_CODE')!=='0'
                || count($invoices)!==1 || strtolower($invoices[0]->getAttribute('UUID'))!==$f::UUID) {
                throw new EdmLookupFailure('SMOKE_ACK_UNCONFIRMED');
            }
            return ['draft_acknowledged'=>true];
        });
        $ack=$result['status']==='ok' && ($result['data']['draft_acknowledged']??false)===true;
        $diagnostic=$result['diagnostic']??null;
        if (!is_string($diagnostic) || !preg_match('/^(?:Login|Lookup|Logout): [A-Z0-9_]+$/D',$diagnostic)) $diagnostic=null;
        $loadStatus=$ack?'draft_acknowledged':'unconfirmed';
        $hints=$result['fault_hints']??[];
        // Record the attempt immediately, before any further read operations.
        $db::table('edm_sandbox_trials')->where('uuid',$f::UUID)->where('account_revision',$account->revision)
            ->update(['load_status'=>$loadStatus,'diagnostic'=>$diagnostic,'fault_hints'=>json_encode($hints,JSON_THROW_ON_ERROR),'fault_detail_ciphertext'=>$result['fault_detail_ciphertext']??null,'updated_at'=>now()]);
        $status='not_run'; $evidence='not_run';
        if ($currentAccount()) {
            $s=$this->invoiceStatus($account->username,$account->password,$f::UUID,$f::PROFILE);
            $status=($s['status']??null)==='ok'?'matched':'unconfirmed';
        }
        if ($currentAccount()) {
            $e=$this->invoiceXmlEvidence($account->username,$account->password,$f::UUID,$f::PROFILE,$f::IDENTIFIER,$f::IDENTIFIER);
            $evidence=($e['status']??null)==='ok'?'matched':'unconfirmed';
        }
        $db::table('edm_sandbox_trials')->where('uuid',$f::UUID)->where('account_revision',$account->revision)
            ->update(['status_query'=>$status,'xml_query'=>$evidence,'updated_at'=>now()]);
        return ['uuid'=>$f::UUID,'load_status'=>$loadStatus,'diagnostic'=>$diagnostic,
            'status_query'=>$status,'xml_query'=>$evidence,'already_reserved'=>false,'fault_hints'=>$hints,
            'series_warnings'=>$preview['unreadable_series'],'recipient_warnings'=>$preview['unverified_records'],
            'fiscal_success_confirmed'=>false,'automatic_retry'=>false];
    }

    /**
     * Separate numbered synthetic trial. Only fixed staging CLI, validated
     * exact payload hash, prior failed experiment, same account revision,
     * fresh route checks, durable unique reservation. Never SendInvoice.
     */
    public function runSecondControlledSmoke(string $date,#[\SensitiveParameter] string $template,string $checkedSha256,string $confirmation,bool $namespaceTrial=false): array
    {
        $f=EdmSandboxSmokeFixture::class;
        $second=EdmSecondSandboxFixture::class;
        $uuid=$namespaceTrial?$second::NAMESPACE_UUID:$second::UUID;
        $prior=$namespaceTrial?$second::UUID:$f::UUID;
        $db=\Illuminate\Support\Facades\DB::class;
        if (PHP_SAPI!=='cli'||rtrim((string)config('app.url'),'/')!==$f::STAGING_URL
            ||$confirmation!==($namespaceTrial?'LOAD-ONE-NAMESPACE-CORRECTED-TEST-DRAFT':'LOAD-ONE-NUMBERED-TEST-DRAFT')||$date!==now('Europe/Istanbul')->toDateString()
            ||!preg_match('/^[a-f0-9]{64}$/D',$checkedSha256)||$db::transactionLevel()!==0) {
            throw new \DomainException('SECOND_SCOPE_OR_CONFIRMATION_INVALID');
        }
        $account=\App\Models\EdmSetting::query()->findOrFail(1);
        if (!$account->configured()||!$account->revision||$account->probe_status!=='ok') throw new \DomainException('SECOND_ACCOUNT_NOT_VERIFIED');
        $currentAccount=static fn()=>\App\Models\EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->where('probe_status','ok')->exists();
        $existing=$db::table('edm_sandbox_trials')->where('uuid',$uuid)->first();
        if ($existing) {
            if ($existing->account_revision!==$account->revision) throw new \DomainException('SECOND_ACCOUNT_CHANGED');
            return $this->secondTrialSummary($existing,true);
        }
        $first=$db::table('edm_sandbox_trials')->where('uuid',$prior)->first();
        if (!$first||$first->account_revision!==$account->revision||$first->load_status!=='unconfirmed'
            ||$first->diagnostic!=='Lookup: EDM_11047') throw new \DomainException('SECOND_FIRST_TRIAL_NOT_ELIGIBLE');
        if ($namespaceTrial) {
            // Specific observed supplier tag failure, not a blind retry. Require
            // original first-trial evidence and same account, plus fresh readback
            // of both previously failed independent UUIDs before reserving.
            $details=EdmStoredFault::read($prior,$account);
            if (!str_contains(implode(' ',$details),'<cac:AccountingSupplierParty>')) throw new \DomainException('SECOND_NAMESPACE_FAULT_NOT_CONFIRMED');
            $original=$db::table('edm_sandbox_trials')->where('uuid',$f::UUID)->first();
            if (!$original||$original->account_revision!==$account->revision||$original->load_status!=='unconfirmed'||$original->diagnostic!=='Lookup: EDM_11047') throw new \DomainException('SECOND_ORIGINAL_TRIAL_NOT_ELIGIBLE');
            $old=$this->invoiceStatus($account->username,$account->password,$f::UUID,$f::PROFILE);
            if (!$currentAccount()) throw new \DomainException('SECOND_ACCOUNT_CHANGED');
            $oldEvidence=$this->invoiceXmlEvidence($account->username,$account->password,$f::UUID,$f::PROFILE,$f::IDENTIFIER,$f::IDENTIFIER);
            if (!$currentAccount()||($old['diagnostic']??null)!=='Lookup: EDM_11017'||!in_array($oldEvidence['diagnostic']??null,['Lookup: EDM_11017','Lookup: GETINVOICE_EMPTY_RESULT'],true)) {
                return ['uuid'=>$uuid,'load_status'=>'blocked_original_readback','automatic_retry'=>false,'fiscal_success_confirmed'=>false];
            }
        }
        $candidate=$second::prepare($date,$template,$namespaceTrial);
        if (!hash_equals($checkedSha256,$candidate['xml_sha256'])
            ||!hash_equals($checkedSha256,hash('sha256',base64_decode($candidate['fields']['INVOICE']['CONTENT'],true)))) throw new \DomainException('SECOND_XML_DIGEST_MISMATCH');
        // This is a distinct authorized synthetic experiment, not a retry.
        // Only explicit provider not-found code on both readbacks permits it;
        // this does not prove the old document is absent in every system.
        $oldStatus=$this->invoiceStatus($account->username,$account->password,$prior,$f::PROFILE);
        if (!$currentAccount()) throw new \DomainException('SECOND_ACCOUNT_CHANGED');
        $oldXml=$this->invoiceXmlEvidence($account->username,$account->password,$prior,$f::PROFILE,$f::IDENTIFIER,$f::IDENTIFIER);
        if (!$currentAccount()) throw new \DomainException('SECOND_ACCOUNT_CHANGED');
        $statusCode=$oldStatus['diagnostic']??null;
        $xmlCode=$oldXml['diagnostic']??null;
        if ($statusCode!=='Lookup: EDM_11017'||!in_array($xmlCode,['Lookup: EDM_11017','Lookup: GETINVOICE_EMPTY_RESULT'],true)) {
            return ['uuid'=>$uuid,'load_status'=>'blocked_first_readback','first_status_diagnostic'=>$this->safeDiagnostic($statusCode),
                'first_xml_diagnostic'=>$this->safeDiagnostic($xmlCode),'automatic_retry'=>false,'fiscal_success_confirmed'=>false];
        }
        $preview=(new EdmRoutingPreview($this))->inspect($account,$f::IDENTIFIER,$f::IDENTIFIER,$f::GB,$f::PK,$f::SERIES,(int)substr($date,0,4));
        foreach (['company','sender_alias','series','recipient','recipient_alias'] as $check) {
            if (($preview['checks'][$check]??null)!==true) throw new \DomainException('SECOND_SELECTED_ROUTE_UNVERIFIED');
        }
        if (!is_int($preview['unreadable_series'])||!is_int($preview['unverified_records'])
            ||$preview['unreadable_series']>1||$preview['unverified_records']>1) throw new \DomainException('SECOND_ROUTING_WARNINGS_CHANGED');
        $db::transaction(function() use($db,$account,$f,$uuid,$prior,$date,$candidate,$preview,$statusCode,$xmlCode) {
            $current=\App\Models\EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
            if (!$current?->configured()||$current->revision!==$account->revision||$current->probe_status!=='ok') throw new \DomainException('SECOND_ACCOUNT_CHANGED');
            $first=$db::table('edm_sandbox_trials')->where('uuid',$prior)->lockForUpdate()->first();
            if (!$first||$first->account_revision!==$account->revision||$first->load_status!=='unconfirmed'||$first->diagnostic!=='Lookup: EDM_11047') throw new \DomainException('SECOND_FIRST_TRIAL_CHANGED');
            if ($db::table('edm_sandbox_trials')->where('uuid',$uuid)->exists()) throw new \DomainException('SECOND_ALREADY_RESERVED');
            $db::table('edm_sandbox_trials')->insert(['uuid'=>$uuid,'account_revision'=>$account->revision,
                'issue_date'=>$date,'xml_sha256'=>$candidate['xml_sha256'],'request_sha256'=>$candidate['request_sha256'],
                'xslt_sha256'=>$candidate['xslt_sha256'],'series_warnings'=>$preview['unreadable_series'],
                'recipient_warnings'=>$preview['unverified_records'],'first_status_diagnostic'=>$statusCode,
                'first_xml_diagnostic'=>$xmlCode,'load_status'=>'reserved_unknown','created_at'=>now(),'updated_at'=>now()]);
        },3);
        $result=$this->read($account->username,$account->password,function($session) use($candidate,$uuid,$f,$date,$currentAccount) {
            if (!$currentAccount()) throw new EdmLookupFailure('SECOND_ACCOUNT_CHANGED');
            $reply=$this->call('LoadInvoice',$session,$candidate['fields']);
            try { return EdmNumberedDraftAck::parse($reply,$uuid,$f::SERIES,(int)substr($date,0,4)); }
            catch (\DomainException $e) { throw new EdmLookupFailure($e->getMessage()); }
        });
        $ack=$result['status']==='ok'&&($result['data']['draft_acknowledged']??false)===true;
        $number=$ack?$result['data']['allocated_invoice_id']:null;
        $db::table('edm_sandbox_trials')->where('uuid',$uuid)->where('account_revision',$account->revision)->update([
            'load_status'=>$ack?'draft_acknowledged':'unconfirmed','allocated_invoice_id'=>$number,
            'diagnostic'=>$this->safeDiagnostic($result['diagnostic']??null),
            'fault_hints'=>json_encode($result['fault_hints']??[],JSON_THROW_ON_ERROR),
            'fault_detail_ciphertext'=>$result['fault_detail_ciphertext']??null,'updated_at'=>now()]);
        // Every later action is a read, never another LoadInvoice.
        if ($currentAccount()) {
            $status=$this->invoiceStatus($account->username,$account->password,$uuid,$f::PROFILE);
            $db::table('edm_sandbox_trials')->where('uuid',$uuid)->update(['status_query'=>($status['status']??null)==='ok'&&(!$ack||($status['data']['invoice_number']??null)===$number)?'matched':'unconfirmed','updated_at'=>now()]);
        }
        if ($ack&&$currentAccount()) {
            foreach (['xml','pdf'] as $format) {
                $state='unconfirmed';
                if (!$currentAccount()) break;
                try {
                    $artifact=$this->read($account->username,$account->password,function($session) use($uuid,$f,$format,$number,$account,$currentAccount) {
                        if (!$currentAccount()) throw new EdmLookupFailure('SECOND_ACCOUNT_CHANGED');
                        $reply=$this->call('GetInvoice',$session,['INVOICE_SEARCH_KEY'=>[
                            'LIMIT'=>'2','ID'=>$number,'UUID'=>$uuid,'READ_INCLUDED'=>'true',
                            'DIRECTION'=>'OUT-EINVOICE','SENDER'=>$f::IDENTIFIER,'RECEIVER'=>$f::IDENTIFIER,'ISARCHIVED'=>'true'],
                            'HEADER_ONLY'=>'N','INVOICE_CONTENT_TYPE'=>strtoupper($format)]);
                        if ($format==='xml') {
                            $e=(new EdmInvoiceXmlEvidence)->parse($reply,$uuid,$f::PROFILE,$f::IDENTIFIER,$f::IDENTIFIER,true);
                            if ($e['invoice_number']!==$number) throw new EdmLookupFailure('SECOND_XML_NUMBER_MISMATCH');
                            return ['bytes'=>$e['private_xml_bytes']];
                        }
                        return ['bytes'=>EdmSandboxDocuments::pdf($reply,$uuid,$number)];
                    });
                    if ($artifact['status']==='ok'&&$currentAccount()) {
                        EdmSandboxDocuments::store($uuid,$account->revision,$number,$format,$artifact['data']['bytes']);
                        $state='matched';
                    }
                } catch (Throwable) { /* Keep artifact unconfirmed; no new mutation. */ }
                $db::table('edm_sandbox_trials')->where('uuid',$uuid)->update([$format.'_query'=>$state,'updated_at'=>now()]);
            }
        }
        return $this->secondTrialSummary($db::table('edm_sandbox_trials')->where('uuid',$uuid)->first(),false);
    }

    private function safeDiagnostic(?string $value): ?string
    {
        return is_string($value)&&preg_match('/^(?:Login|Lookup|Logout): [A-Z0-9_]+$/D',$value)?$value:null;
    }

    private function secondTrialSummary(object $row,bool $existing): array
    {
        return ['uuid'=>$row->uuid,'load_status'=>$row->load_status,'allocated_invoice_id'=>$row->allocated_invoice_id,
            'diagnostic'=>$row->diagnostic,'fault_hints'=>json_decode($row->fault_hints??'[]',true,512,JSON_THROW_ON_ERROR),
            'status_query'=>$row->status_query,'xml_query'=>$row->xml_query,'pdf_query'=>$row->pdf_query,
            'first_status_diagnostic'=>$row->first_status_diagnostic,'first_xml_diagnostic'=>$row->first_xml_diagnostic,
            'already_reserved'=>$existing,'automatic_retry'=>false,'fiscal_success_confirmed'=>false];
    }

    public function loadQueuedOrderDraft(object $request): array
    {
        $account=\App\Models\EdmSetting::query()->find(1);
        if (!InvoiceDispatch::ready($account)||$request->account_revision!==$account->revision
            ||!\Illuminate\Support\Facades\DB::table('invoice_dispatch_requests')->where('id',$request->id)->where('order_id',$request->order_id)->where('state','processing')->exists()) throw new \DomainException('ORDER_CONNECTION_NOT_READY');
        $order=InvoiceDispatch::eligible()->where('is_test',true)->find($request->order_id);
        if (!$order) throw new \DomainException('ORDER_PAYMENT_UNVERIFIED');
        $draft=\App\Models\InvoicePreparation::query()->where('order_id',$order->id)->firstOrFail();
        $fresh=(new InvoiceAmounts)->preview($order,$draft->shipping_rate);
        if (!hash_equals($draft->snapshot_hash,hash('sha256',json_encode($fresh,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)))) throw new \DomainException('ORDER_SNAPSHOT_CHANGED');
        $date=now('Europe/Istanbul')->toDateString();
        $template=file_get_contents(InvoiceDispatch::templatePath());
        if (!is_string($template)) throw new \DomainException('ORDER_TEMPLATE_MISSING');
        $fields=EdmOrderDraftPayload::fields($draft,$date,$template,$order);
        $f=EdmSandboxSmokeFixture::class;
        $preview=(new EdmRoutingPreview($this))->inspect($account,$f::IDENTIFIER,$f::IDENTIFIER,$f::GB,$f::PK,$f::SERIES,(int)substr($date,0,4));
        foreach (['company','sender_alias','series','recipient','recipient_alias'] as $key) if (($preview['checks'][$key]??null)!==true) throw new \DomainException('ORDER_ROUTE_UNVERIFIED');
        if (!is_int($preview['unreadable_series'])||!is_int($preview['unverified_records'])||$preview['unreadable_series']>1||$preview['unverified_records']>1) throw new \DomainException('ORDER_ROUTE_WARNINGS_CHANGED');
        $journal=new EdmSubmissionJournal;
        $attempt=$journal->claim($draft->id,$f::PROFILE,hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR)),$account->revision,true);
        $r=$this->read($account->username,$account->password,function($session)use($fields,$account,$draft,$f,$date){
            if (!\App\Models\EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->where('probe_status','ok')->exists()) throw new EdmLookupFailure('ORDER_ACCOUNT_CHANGED');
            $reply=$this->call('LoadInvoice',$session,$fields);
            try { return EdmNumberedDraftAck::parse($reply,$draft->uuid,$f::SERIES,(int)substr($date,0,4)); }
            catch (\DomainException $e) { throw new EdmLookupFailure($e->getMessage()); }
        });
        $ack=$r['status']==='ok'&&($r['data']['draft_acknowledged']??false)===true;
        $journal->recordLoadObservation($attempt->id,$account->revision,$ack,$ack?$r['data']['allocated_invoice_id']:null);
        if (!$ack) \Illuminate\Support\Facades\DB::table('invoice_dispatch_requests')->where('id',$request->id)->update([
            'fault_hints'=>json_encode($r['fault_hints']??[],JSON_THROW_ON_ERROR),'fault_detail_ciphertext'=>$r['fault_detail_ciphertext']??null]);
        if ($ack) { try {$journal->reconcile($attempt->id,$this);}catch(Throwable){} }
        return ['load_status'=>$ack?'draft_acknowledged':'unconfirmed','diagnostic'=>$this->safeDiagnostic($r['diagnostic']??null),'fiscal_success_confirmed'=>false];
    }

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
                    'LIMIT'=>'2','UUID'=>$uuid,
                    'READ_INCLUDED'=>'true','DIRECTION'=>$profile==='EARSIVFATURA'?'OUT-EARCHIVE':'OUT-EINVOICE',
                    'SENDER'=>$seller,'RECEIVER'=>$buyer,
                    'ISARCHIVED'=>'true',
                ],
                'HEADER_ONLY'=>'N','INVOICE_CONTENT_TYPE'=>'XML',
            ]);
            $children=[];
            foreach ($reply->childNodes as $node) if ($node instanceof DOMElement) $children[]=$node;
            if (count($children)===0 || (count($children)===1 && $children[0]->localName==='REQUEST_RETURN'
                && ($children[0]->namespaceURI===null||$children[0]->namespaceURI==='') && $this->value($children[0],'RETURN_CODE')==='0')) {
                throw new EdmLookupFailure('GETINVOICE_EMPTY_RESULT');
            }
            return (new EdmInvoiceXmlEvidence)->parse($reply,$uuid,$profile,$seller,$buyer);
        });
    }

    /** Exact order readback, not a broad mailbox scan. C# sample defaults
     * archived=false (omitted), and accepts OUT. Retry only read-only empty results. */
    public function readOrderDocument(\App\Models\EdmSubmissionAttempt $attempt,\App\Models\EdmSetting $account,string $format): array
    {
        [$current,$row]=EdmOrderDocuments::context($attempt->id);
        if ($current->revision!==$account->revision||$row->observed_invoice_number!==$attempt->observed_invoice_number||
            !in_array($format,['xml','pdf'],true)) throw new \DomainException('ORDER_DOCUMENT_SCOPE');
        $f=EdmSandboxSmokeFixture::class;
        return $this->read($account->username,$account->password,function($session)use($attempt,$account,$format,$f){
            foreach (['OUT','OUT-EINVOICE'] as $direction) {
                if (!\App\Models\EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->where('probe_status','ok')->exists()) throw new EdmLookupFailure('ORDER_ACCOUNT_CHANGED');
                try {
                    $reply=$this->call('GetInvoice',$session,['INVOICE_SEARCH_KEY'=>[
                        'LIMIT'=>'2','ID'=>$attempt->observed_invoice_number,'UUID'=>$attempt->uuid,
                        'READ_INCLUDED'=>'true','DIRECTION'=>$direction],
                        'HEADER_ONLY'=>'N','INVOICE_CONTENT_TYPE'=>strtoupper($format)]);
                } catch(EdmLookupFailure $e) {
                    if ($e->safeCode==='EDM_11017') continue;
                    throw $e;
                }
                $children=[];
                foreach($reply->childNodes as $node) if($node instanceof DOMElement) $children[]=$node;
                if (!$children||(count($children)===1&&$children[0]->localName==='REQUEST_RETURN'&&$this->value($children[0],'RETURN_CODE')==='0')) continue;
                if ($format==='xml') {
                    $e=(new EdmInvoiceXmlEvidence)->parse($reply,$attempt->uuid,$attempt->profile,$f::IDENTIFIER,$f::IDENTIFIER,true);
                    if ($e['invoice_number']!==$attempt->observed_invoice_number) throw new EdmLookupFailure('ORDER_DOCUMENT_NUMBER_MISMATCH');
                    return ['bytes'=>$e['private_xml_bytes'],'signature_present'=>$e['signature_element_present']];
                }
                return ['bytes'=>EdmSandboxDocuments::pdf($reply,$attempt->uuid,$attempt->observed_invoice_number)];
            }
            throw new EdmLookupFailure('GETINVOICE_EMPTY_RESULT');
        });
    }

    public function sendReservedOrderDraft(\App\Models\EdmSubmissionAttempt $attempt,\App\Models\EdmSetting $account): array
    {
        [$current,$row]=EdmOrderDocuments::context($attempt->id);
        if ($current->revision!==$account->revision||$row->observed_invoice_number!==$attempt->observed_invoice_number||
            !\Illuminate\Support\Facades\DB::table('edm_order_send_requests')->where('attempt_id',$attempt->id)
                ->where('account_revision',$account->revision)->where('invoice_id',$attempt->observed_invoice_number)->where('state','outcome_unknown')->exists()) throw new \DomainException('SEND_NOT_RESERVED');
        $f=EdmSandboxSmokeFixture::class;
        // Consumes the one-time transport reservation before login. Never repeat
        // even if the process dies or the provider response is unavailable.
        $claimed=\Illuminate\Support\Facades\DB::table('edm_order_send_requests')->where('attempt_id',$attempt->id)
            ->where('state','outcome_unknown')->update(['state'=>'transport_started','updated_at'=>now()]);
        if ($claimed!==1) throw new \DomainException('SEND_ALREADY_STARTED');
        return $this->read($account->username,$account->password,function($session)use($attempt,$account,$f){
            if (!\App\Models\EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->where('probe_status','ok')->exists()) throw new EdmLookupFailure('ORDER_ACCOUNT_CHANGED');
            $reply=$this->call('SendInvoice',$session,[
                'SENDER'=>['vkn'=>$f::IDENTIFIER,'alias'=>$f::GB],'RECEIVER'=>['vkn'=>$f::IDENTIFIER,'alias'=>$f::PK],
                'INVOICE'=>['UUID'=>$attempt->uuid,'ID'=>$attempt->observed_invoice_number]]);
            if ($this->value($reply,'RETURN_CODE')!=='0') throw new EdmLookupFailure('SEND_RETURN_FAILED');
            $matches=[];
            foreach ($reply->childNodes as $node) if ($node instanceof DOMElement&&$node->localName==='INVOICE') $matches[]=$node;
            if (count($matches)!==1||strtolower($matches[0]->getAttribute('UUID'))!==strtolower($attempt->uuid)||
                $matches[0]->getAttribute('ID')!==$attempt->observed_invoice_number) throw new EdmLookupFailure('SEND_ACK_IDENTITY');
            return ['accepted_for_processing'=>true];
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
            if ($failure->hints) $result['fault_hints']=$failure->hints;
            if ($failure->encryptedDetail!==null) $result['fault_detail_ciphertext']=$failure->encryptedDetail;
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
        if (!in_array($method, ['Login','CheckCounter','Logout','GetCompany','CheckUser','GetInvoiceStatus','GetInvoice','LoadInvoice','SendInvoice'], true)) throw new RuntimeException('Unsupported operation');
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
            'REASON'=>match($method){'LoadInvoice'=>'NEWTIC controlled sandbox draft load','SendInvoice'=>'NEWTIC administrator approved sandbox draft send',default=>'NEWTIC read-only connection check'},
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
            } elseif (in_array($method,['LoadInvoice','SendInvoice'],true) && in_array($key,['SENDER','RECEIVER'],true)) {
                $party=$operation->appendChild($doc->createElement($key));
                $party->setAttribute('vkn',$value['vkn']);
                $party->setAttribute('alias',$value['alias']);
            } elseif ($method==='SendInvoice' && $key==='INVOICE') {
                $invoice=$operation->appendChild($doc->createElement('INVOICE'));
                $invoice->setAttribute('TRXID','0');
                $invoice->setAttribute('UUID',$value['UUID']);
                $invoice->setAttribute('ID',$value['ID']);
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
            $encrypted=null;
            if ($method==='LoadInvoice') {
                // Capture only encrypted description evidence for actual load
                // faults, never the raw response or stack trace.
                try { $encrypted=EdmFaultHints::encryptedDetails($fault->item(0),$session); }
                catch (Throwable) { /* Preserve original provider fault code. */ }
            }
            throw new EdmLookupFailure(preg_match('/^[0-9]{1,6}$/D',$code) ? 'EDM_'.$code : 'SOAP_FAULT', EdmFaultHints::inspect($fault->item(0)),$encrypted);
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
