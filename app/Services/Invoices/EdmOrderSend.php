<?php
namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\EdmSubmissionAttempt;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Explicit test-only final-send request. A committed claim is never retried. */
final class EdmOrderSend
{
    public function submit(int $id,int $actor,EdmTestClient $client): string
    {
        if (DB::transactionLevel()!==0) throw new DomainException('SEND_TRANSACTION');
        [$a,$attempt,$draft,$order]=EdmOrderDocuments::context($id);
        if (!InvoiceDispatch::eligible()->whereKey($order->id)->exists()) throw new DomainException('SEND_ORDER_INELIGIBLE');
        if (DB::table('edm_order_send_requests')->where('attempt_id',$id)->exists()) throw new DomainException('SEND_ALREADY_RESERVED');
        $status=$client->invoiceStatus($a->username,$a->password,$attempt->uuid,$attempt->profile);
        if ($status['status']!=='ok'||($status['data']['invoice_number']??null)!==$attempt->observed_invoice_number||
            ($status['data']['portal_state']??null)!=='draft'||($status['data']['gib']['code']??null)!==null) throw new DomainException('SEND_DRAFT_STATUS_UNVERIFIED');
        $doc=$client->readOrderDocument($attempt,$a,'xml');
        if ($doc['status']!=='ok'||($doc['data']['signature_present']??false)) throw new DomainException('SEND_XML_NOT_VERIFIED');
        EdmOrderDocuments::assertAmounts($doc['data']['bytes'],$draft);
        DB::transaction(function()use($id,$actor,$a,$attempt,$order){
            $account=EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
            $row=EdmSubmissionAttempt::query()->lockForUpdate()->findOrFail($id);
            if (!$account||$account->revision!==$a->revision||$account->probe_status!=='ok'||
                $row->account_revision!==$a->revision||$row->observed_invoice_number!==$attempt->observed_invoice_number||
                !InvoiceDispatch::eligible()->whereKey($order->id)->exists()||DB::table('edm_order_send_requests')->where('attempt_id',$id)->exists()) throw new DomainException('SEND_RESERVATION_CONFLICT');
            DB::table('edm_order_send_requests')->insert(['attempt_id'=>$id,'requested_by'=>$actor,'account_revision'=>$a->revision,
                'invoice_id'=>$row->observed_invoice_number,'state'=>'outcome_unknown','created_at'=>now(),'updated_at'=>now()]);
        },3);
        $result=$client->sendReservedOrderDraft($attempt,$a);
        $state=$result['status']==='ok'?'accepted_for_processing':'unconfirmed';
        DB::table('edm_order_send_requests')->where('attempt_id',$id)->where('account_revision',$a->revision)->update([
            'state'=>$state,'diagnostic'=>$result['diagnostic']??null,'updated_at'=>now()]);
        try {(new EdmSubmissionJournal)->reconcile($id,$client);}catch(\Throwable){}
        return $state;
    }
}
