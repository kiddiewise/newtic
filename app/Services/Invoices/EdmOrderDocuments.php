<?php
namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\EdmSubmissionAttempt;
use App\Models\InvoicePreparation;
use App\Models\Order;
use DOMDocument;
use DOMXPath;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Exact provider bytes, admin-only sandbox artifacts; never a fiscal archive. */
final class EdmOrderDocuments
{
    public static function context(int $id): array
    {
        $a=EdmSetting::query()->find(1);
        $attempt=EdmSubmissionAttempt::query()->findOrFail($id);
        $draft=InvoicePreparation::query()->findOrFail($attempt->invoice_preparation_id);
        $order=Order::query()->findOrFail($draft->order_id);
        if (!InvoiceDispatch::staging()||!$a?->configured()||$a->probe_status!=='ok'||$a->revision!==$attempt->account_revision||
            !$order->is_test||$attempt->uuid!==$draft->uuid||$attempt->profile!==EdmSandboxSmokeFixture::PROFILE||
            $attempt->last_load_result!=='draft_acknowledged'||$attempt->state!=='provider_record_found'||
            !preg_match('/^A01[0-9]{13}$/D',$attempt->observed_invoice_number??'')) throw new DomainException('ORDER_DOCUMENT_SCOPE');
        return [$a,$attempt,$draft,$order];
    }

    public static function assertAmounts(string $xml,InvoicePreparation $draft): void
    {
        $snapshot=$draft->amounts;
        InvoiceSnapshotRules::validate($snapshot);
        if (!hash_equals($draft->snapshot_hash,hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)))) throw new DomainException('ORDER_DOCUMENT_SNAPSHOT');
        $doc=new DOMDocument();
        if (!@$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)||$doc->doctype) throw new DomainException('ORDER_DOCUMENT_XML');
        $xp=new DOMXPath($doc);
        $xp->registerNamespace('i','urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cac',EdmUblNamespace::NS['cac']);
        $xp->registerNamespace('cbc',EdmUblNamespace::NS['cbc']);
        $cent=static function($path)use($xp):int {
            $n=$xp->query($path);
            if ($n->length!==1||$n->item(0)->getAttribute('currencyID')!=='TRY') throw new DomainException('ORDER_DOCUMENT_AMOUNT_MISSING');
            $v=trim($n->item(0)->textContent);
            if (!preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2})0*)?$/D',$v,$m)) throw new DomainException('ORDER_DOCUMENT_AMOUNT_FORMAT');
            return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
        };
        foreach (['TaxExclusiveAmount'=>'net_cents','TaxInclusiveAmount'=>'gross_cents','PayableAmount'=>'gross_cents'] as $field=>$key)
            if ($cent('/i:Invoice/cac:LegalMonetaryTotal/cbc:'.$field)!==$snapshot[$key]) throw new DomainException('ORDER_DOCUMENT_AMOUNT_MISMATCH');
        if ($cent('/i:Invoice/cac:TaxTotal/cbc:TaxAmount')!==$snapshot['tax_cents']) throw new DomainException('ORDER_DOCUMENT_TAX_MISMATCH');
        if ($xp->query('/i:Invoice/cac:InvoiceLine')->length!==count($snapshot['lines'])) throw new DomainException('ORDER_DOCUMENT_LINE_COUNT');
        foreach ($snapshot['lines'] as $index=>$line) {
            $base='/i:Invoice/cac:InvoiceLine['.($index+1).']';
            if ($cent($base.'/cbc:LineExtensionAmount')!==$line['net_cents']||
                $cent($base.'/cac:TaxTotal/cbc:TaxAmount')!==$line['tax_cents']) throw new DomainException('ORDER_DOCUMENT_LINE_AMOUNT');
        }
    }

    public function refresh(int $id,EdmTestClient $client): array
    {
        [$a,$attempt,$draft]=$this->context($id);
        $out=[];
        foreach (['xml','pdf'] as $format) {
            $state='unconfirmed';$diagnostic='ARTIFACT_NOT_CONFIRMED';$bytes=null;$signed=false;
            try {
                $result=$client->readOrderDocument($attempt,$a,$format);
                if ($result['status']==='ok') {
                    $bytes=$result['data']['bytes'];$signed=(bool)($result['data']['signature_present']??false);
                    if ($format==='xml') self::assertAmounts($bytes,$draft);
                    // PDF identity is validated in SOAP; do not expose until matching XML exists.
                    if ($format==='pdf'&&!DB::table('edm_order_documents')->where('attempt_id',$id)->where('format','xml')->where('account_revision',$a->revision)->exists())
                        throw new DomainException('ORDER_DOCUMENT_XML_REQUIRED');
                    $state='matched';$diagnostic=null;
                } else $diagnostic=$result['diagnostic']??$diagnostic;
            } catch (DomainException $e) {$diagnostic=$e->getMessage();$state='unconfirmed';}
            catch (\Throwable) {$state='unconfirmed';}
            DB::transaction(function()use($id,$a,$attempt,$format,$state,$diagnostic,$bytes,$signed){
                $account=EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
                $row=EdmSubmissionAttempt::query()->lockForUpdate()->findOrFail($id);
                if (!$account||$account->revision!==$a->revision||$row->account_revision!==$a->revision||
                    $row->observed_invoice_number!==$attempt->observed_invoice_number) throw new DomainException('ORDER_DOCUMENT_ACCOUNT_CHANGED');
                if ($state==='matched') {
                    if (!is_string($bytes)||$bytes===''||strlen($bytes)>2*1024*1024) throw new DomainException('ORDER_DOCUMENT_SIZE');
                    DB::table('edm_order_documents')->updateOrInsert(['attempt_id'=>$id,'format'=>$format],[
                        'account_revision'=>$a->revision,'invoice_id'=>$row->observed_invoice_number,'sha256'=>hash('sha256',$bytes),
                        'byte_count'=>strlen($bytes),'signature_present'=>$signed,'ciphertext'=>Crypt::encryptString(base64_encode($bytes)),'created_at'=>now()]);
                }
                DB::table('edm_order_document_checks')->updateOrInsert(['attempt_id'=>$id,'format'=>$format],[
                    'account_revision'=>$a->revision,'state'=>$state,'diagnostic'=>is_string($diagnostic)&&preg_match('/^[A-Z0-9_: ]{1,96}$/Di',$diagnostic)?$diagnostic:null,'checked_at'=>now()]);
            },3);
            $out[$format]=$state;
        }
        return $out;
    }

    public static function download(int $id,string $format): array
    {
        if (!in_array($format,['xml','pdf'],true)) abort(404);
        try {[$a,$attempt]=self::context($id);} catch (\Throwable) {abort(404);}
        $row=DB::table('edm_order_documents')->where('attempt_id',$id)->where('format',$format)
            ->where('account_revision',$a->revision)->where('invoice_id',$attempt->observed_invoice_number)->first();
        if (!$row) abort(404);
        try {$bytes=base64_decode(Crypt::decryptString($row->ciphertext),true);} catch (\Throwable){abort(404);}
        if (!is_string($bytes)||strlen($bytes)!==(int)$row->byte_count||!hash_equals($row->sha256,hash('sha256',$bytes))) abort(404);
        return ['bytes'=>$bytes,'number'=>$row->invoice_id];
    }
}
