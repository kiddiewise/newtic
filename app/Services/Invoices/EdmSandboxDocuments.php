<?php

namespace App\Services\Invoices;

use DOMElement;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Private sandbox artifacts only, tied to the exact numbered trial. */
final class EdmSandboxDocuments
{
    public static function pdf(DOMElement $reply,string $uuid,string $number): string
    {
        $one=static function(DOMElement $parent,string $name): DOMElement {
            $nodes=[];
            foreach ($parent->childNodes as $node) {
                if (!$node instanceof DOMElement || $node->localName!==$name) continue;
                if ($node->namespaceURI!==null && $node->namespaceURI!=='') throw new EdmLookupFailure('PDF_NAMESPACE');
                $nodes[]=$node;
            }
            if (count($nodes)!==1) throw new EdmLookupFailure('PDF_FIELD_COUNT');
            return $nodes[0];
        };
        $value=static function(DOMElement $node): string {
            foreach ($node->childNodes as $child) if ($child instanceof DOMElement) throw new EdmLookupFailure('PDF_FIELD_SHAPE');
            return trim($node->textContent);
        };
        $i=$one($reply,'INVOICE');
        $header=$one($i,'HEADER');
        $f=EdmSandboxSmokeFixture::class;
        if (strtolower($i->getAttribute('UUID'))!==strtolower($uuid)||$i->getAttribute('ID')!==$number
            ||$value($one($header,'SENDER'))!==$f::IDENTIFIER||$value($one($header,'RECEIVER'))!==($uuid===EdmArchiveSmokeFixture::UUID?'11111111111':$f::IDENTIFIER)
            ||$value($one($header,'PROFILEID'))!==($uuid===EdmArchiveSmokeFixture::UUID?'EARSIVFATURA':$f::PROFILE)) throw new EdmLookupFailure('PDF_IDENTITY_MISMATCH');
        $encoded=preg_replace('/[\r\n\t ]/','',$value($one($i,'CONTENT')));
        if (!$encoded || strlen($encoded)>3*1024*1024) throw new EdmLookupFailure('PDF_CONTENT_SIZE');
        $bytes=base64_decode($encoded,true);
        if ($bytes===false||base64_encode($bytes)!==$encoded||!preg_match('/^%PDF-[12]\.[0-9]/D',$bytes)
            ||!str_ends_with(rtrim($bytes),"%%EOF")) throw new EdmLookupFailure('PDF_CONTENT_INVALID');
        return $bytes;
    }

    public static function store(string $uuid,string $revision,string $number,string $format,#[\SensitiveParameter] string $bytes): array
    {
        if (!in_array($uuid,[EdmSecondSandboxFixture::UUID,EdmSecondSandboxFixture::NAMESPACE_UUID,EdmArchiveSmokeFixture::UUID],true)||!in_array($format,['xml','pdf'],true)||strlen($bytes)>2*1024*1024||$bytes==='') {
            throw new DomainException('SANDBOX_ARTIFACT_SCOPE');
        }
        $digest=hash('sha256',$bytes);
        DB::transaction(function() use($uuid,$revision,$number,$format,$bytes,$digest) {
            $account=\App\Models\EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
            $trial=DB::table('edm_sandbox_trials')->where('uuid',$uuid)->lockForUpdate()->first();
            if (!$account||$account->revision!==$revision||$account->probe_status!=='ok'||!$trial
                ||$trial->account_revision!==$revision||$trial->allocated_invoice_id!==$number
                ||$trial->load_status!=='draft_acknowledged') throw new DomainException('SANDBOX_ARTIFACT_ACCOUNT_OR_TRIAL');
            $existing=DB::table('edm_sandbox_documents')->where('uuid',$uuid)->where('format',$format)->first();
            if ($existing) {
                if (!hash_equals($existing->sha256,$digest)) throw new DomainException('SANDBOX_ARTIFACT_CHANGED');
                return;
            }
            DB::table('edm_sandbox_documents')->insert(['uuid'=>$uuid,'format'=>$format,'invoice_id'=>$number,
                'account_revision'=>$revision,'sha256'=>$digest,'byte_count'=>strlen($bytes),
                'ciphertext'=>Crypt::encryptString(base64_encode($bytes)),'created_at'=>now()]);
        });
        return ['format'=>$format,'sha256'=>$digest,'bytes'=>strlen($bytes)];
    }

    public static function download(string $uuid,string $format): array
    {
        if (rtrim((string)config('app.url'),'/')!==EdmSandboxSmokeFixture::STAGING_URL
            ||!in_array($uuid,[EdmSecondSandboxFixture::UUID,EdmSecondSandboxFixture::NAMESPACE_UUID,EdmArchiveSmokeFixture::UUID],true)||!in_array($format,['xml','pdf'],true)) abort(404);
        $account=\App\Models\EdmSetting::query()->find(1);
        if (!$account?->configured()||!$account->revision) abort(404);
        $row=DB::table('edm_sandbox_documents')->where('uuid',$uuid)->where('format',$format)
            ->where('account_revision',$account->revision)->first();
        $trial=DB::table('edm_sandbox_trials')->where('uuid',$uuid)->where('account_revision',$account->revision)->first();
        if (!$row||!$trial||$trial->load_status!=='draft_acknowledged'||$trial->allocated_invoice_id!==$row->invoice_id) abort(404);
        try { $bytes=base64_decode(Crypt::decryptString($row->ciphertext),true); }
        catch (\Throwable) { abort(404); }
        if (!is_string($bytes)||strlen($bytes)!==(int)$row->byte_count||!hash_equals($row->sha256,hash('sha256',$bytes))) abort(404);
        return ['bytes'=>$bytes,'number'=>$row->invoice_id];
    }
}
