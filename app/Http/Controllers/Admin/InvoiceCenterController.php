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
        return response()->view('admin.invoices.index',compact('rows','counts','filter'))
            ->header('Cache-Control','private, no-store, max-age=0');
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
