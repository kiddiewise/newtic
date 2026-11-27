<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdmSetting;
use App\Models\EdmSubmissionAttempt;
use App\Services\Invoices\EdmSubmissionJournal;
use App\Services\Invoices\EdmTestClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Local preparations and observed sandbox evidence, not a fiscal archive. */
final class InvoiceCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $filter=$request->query('state','all');
        if (!is_string($filter) || !in_array($filter,['all','local','uncertain','observed'],true)) $filter='all';
        $query=DB::table('invoice_preparations as p')
            ->join('orders as o','o.id','=','p.order_id')
            ->leftJoin('edm_submission_attempts as a','a.invoice_preparation_id','=','p.id');
        $counts=[
            'all'=>(clone $query)->count(),
            'local'=>(clone $query)->whereNull('a.id')->count(),
            'uncertain'=>(clone $query)->where('a.state','outcome_unknown')->count(),
            'observed'=>(clone $query)->where('a.state','provider_record_found')->count(),
        ];
        if ($filter==='local') $query->whereNull('a.id');
        elseif ($filter==='uncertain') $query->where('a.state','outcome_unknown');
        elseif ($filter==='observed') $query->where('a.state','provider_record_found');
        $account=EdmSetting::query()->find(1);
        $revision=$account?->configured() && $account->probe_status==='ok' ? $account->revision : null;
        // Select only allowlisted metadata; never decrypt snapshot or select
        // credentials, raw XML, payload hashes or customer contact fields.
        $rows=$query->select('p.id','p.order_id','p.uuid','p.gross_cents','p.created_at',
            'o.order_number','o.is_test','o.currency','a.id as attempt_id','a.state',
            'a.profile','a.last_load_result','a.last_lookup_state','a.last_portal_state',
            'a.last_gib_code','a.observed_invoice_number','a.last_lookup_at')
            ->selectRaw('CASE WHEN a.account_revision = ? AND o.is_test = ? THEN 1 ELSE 0 END AS can_reconcile',[$revision,true])
            ->orderByDesc('p.id')->paginate(20)->appends(['state'=>$filter]);
        $pending=\App\Services\Invoices\InvoiceDispatch::pending()->select('id','order_number','status','total_cents','shipping_cents','currency')
            ->withCount(['items as missing_vat_count'=>fn($q)=>$q->whereNull('vat_rate')])
            ->with(['items'=>fn($q)=>$q->whereNull('vat_rate')->select('id','order_id','product_id','product_name','vat_rate'),'items.product:id,vat_rate'])
            ->latest('id')->paginate(20,['*'],'waiting_page');
        $dispatches=DB::table('invoice_dispatch_requests as q')->join('orders as o','o.id','=','q.order_id')
            ->select('q.id','q.order_id','q.state','q.diagnostic','o.order_number')->latest('q.id')->limit(30)->get();
        $auto=(bool)$account?->auto_invoice; $shippingVat=$account?->shipping_vat_rate;
        $dispatchReady=\App\Services\Invoices\InvoiceDispatch::ready($account);
        $canQueue=\App\Services\Invoices\InvoiceDispatch::staging()&&$revision!==null;
        return response()->view('admin.invoices.index',compact('rows','counts','filter','pending','dispatches','auto','shippingVat','dispatchReady','canQueue'))
            ->header('Cache-Control','private, no-store, max-age=0');
    }

    public function createInvoices(Request $request,\App\Services\Invoices\InvoiceDispatch $dispatch): RedirectResponse
    {
        $data=$request->validate(['orders'=>['required','array','min:1','max:20'],'orders.*'=>['required','integer','distinct','exists:orders,id'],
            'shipping_rate'=>['nullable','integer','in:1,10,20'], 'missing_product_rate'=>['nullable','integer','in:1,10,20']]);
        $count=0; $failed=[];
        foreach ($data['orders'] as $id) {
            try {$dispatch->enqueue((int)$id,$request->user()->id,isset($data['shipping_rate'])?(int)$data['shipping_rate']:null,'manual',isset($data['missing_product_rate'])?(int)$data['missing_product_rate']:null);$count++;}
            catch (\DomainException $e) {$failed[]='#'.$id.': '.$e->getMessage();}
        }
        $back=redirect()->route('admin.invoices.index');
        if ($count>0) $back->with('success',$count.' fatura oluşturma isteği kaydedildi. EDM sonucu işlem merkezinde gösterilecek.');
        if ($failed) $back->withErrors(['invoice'=>implode(' | ',$failed)]);
        return $back;
    }

    public function settings(Request $request): RedirectResponse
    {
        $d=$request->validate(['auto_invoice'=>['required','boolean'],'shipping_vat_rate'=>['nullable','integer','in:1,10,20']]);
        DB::transaction(function()use($d){
            $a=EdmSetting::query()->firstOrCreate(['id'=>1]);
            $a=EdmSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $enabled=(bool)$d['auto_invoice'];
            $a->forceFill(['auto_invoice'=>$enabled,'auto_invoice_since'=>$enabled?($a->auto_invoice_since??now()):null,
                'shipping_vat_rate'=>$d['shipping_vat_rate']??null])->save();
        },3);
        return redirect()->route('admin.invoices.index')->with('success','Faturalandırma tercihleri kaydedildi.');
    }

    public function reconcile(EdmSubmissionAttempt $attempt,EdmSubmissionJournal $journal,EdmTestClient $client): RedirectResponse
    {
        // Existing persisted reservation only; no new claim or upload.
        $eligible=DB::table('invoice_preparations as p')->join('orders as o','o.id','=','p.order_id')
            ->where('p.id',$attempt->invoice_preparation_id)->where('o.is_test',true)
            ->where('p.uuid',$attempt->uuid)->exists();
        abort_unless($eligible,404);
        try {
            $journal->reconcile($attempt->id,$client);
        } catch (Throwable) {
            return redirect()->route('admin.invoices.index')->withErrors(['invoice'=>'EDM sorgusu doğrulanamadı. Hesap değişmiş olabilir. Yeniden yükleme yapmayın.']);
        }
        return redirect()->route('admin.invoices.index')->with('success','EDM durum sorgusu kaydedildi. Bu işlem fatura göndermez.');
    }
}
