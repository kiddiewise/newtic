<?php
namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\Order;
use App\Support\InvoiceBuyerIdentifier;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Local preparation only; no provider mutation or fiscal reservation. */
final class EdmArchiveOrderMetadata
{
    private static function source(Order $order,EdmSetting $account): string
    {
        $amounts=(new InvoiceAmounts)->preview($order,$account->shipping_vat_rate===null?null:(int)$account->shipping_vat_rate);
        if (!$amounts['complete']) throw new DomainException('ARCHIVE_AMOUNTS_UNVERIFIED');
        $id=InvoiceBuyerIdentifier::resolve($order,true);
        $payment=$order->payment_method==='paytr'?$order->paytrAttempts()->where('status','success')->get(['id','status','amount_cents','paid_total_cents','currency','is_test','reconciliation_result','processed_at'])->toArray():
            $order->paymentNotifications()->where('status','verified')->get(['id','status','reviewed_at'])->toArray();
        $parts=['order'=>$order->order_number,'currency'=>$order->currency,'status'=>$order->status,
            'placed_at'=>$order->placed_at?->toIso8601String(),'website'=>EdmStoreScope::website(),'revision'=>$account->revision,
            'amounts'=>$amounts,'buyer'=>[$id,$order->invoice_type,$order->customer_name,$order->customer_email,$order->company_title,$order->tax_office,$order->city,$order->district,$order->street_address],
            'payment_method'=>$order->payment_method,'payment'=>$payment,'tracking'=>$order->tracking_number,'carrier'=>$order->carrier_name];
        return hash_hmac('sha256',json_encode($parts,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),(string)config('app.key'));
    }

    public function confirm(Order $order,EdmSetting $account,EdmTestClient $client,int $adminId,string $sellerId,string $series,bool $syntheticAnonymous,array $metadata): array
    {
        if (!EdmStoreScope::sandboxOrders()||!$order->is_test||!$account->configured()||$account->probe_status!=='ok')
            throw new DomainException('ARCHIVE_METADATA_SCOPE');
        if (!preg_match('/^[A-Z0-9]{3}$/D',$series)||!($metadata['confirm_metadata']??false)) throw new DomainException('ARCHIVE_METADATA_CONFIRMATION');
        // Capture before read-only provider queries; recheck source under order lock before persisting.
        $source=self::source($order,$account);
        $result=(new EdmOrderArchivePreflight)->inspect($order,$account,$client,$sellerId,$syntheticAnonymous,$metadata);
        if (!in_array($result['type'],['archive_candidate','archive_test_anonymous'],true)
            ||!$result['internet_details_prepared']||!in_array($series,$result['series'],true)
            ||$result['checks']['recipient_details']!=='ok'||$result['checks']['amounts']!=='ok'||$result['checks']['payment_evidence']!=='ok'
            ||!in_array($result['checks']['series'],['available','partial'],true)) throw new DomainException('ARCHIVE_METADATA_UNVERIFIED');
        DB::transaction(function()use($order,$account,$adminId,$sellerId,$series,$metadata,$result,$source){
            $a=EdmSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $locked=Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($a->revision!==$account->revision||$a->probe_status!=='ok'||!$locked->is_test||!hash_equals($source,self::source($locked,$a)))
                throw new DomainException('ARCHIVE_METADATA_CHANGED');
            if (DB::table('invoice_dispatch_requests')->where('order_id',$locked->id)->exists()
                ||DB::table('invoice_preparations')->where('order_id',$locked->id)->exists())
                throw new DomainException('ARCHIVE_EXISTING_INVOICE');
            if (!\App\Models\User::query()->whereKey($adminId)->where('is_admin',true)->exists()) throw new DomainException('ARCHIVE_METADATA_ADMIN');
            $payload=array_intersect_key($metadata,array_flip(['payment_date','dispatch_date','carrier_vkn','carrier_title']));
            $payload['seller_id']=$sellerId;
            $payload['synthetic_anonymous']=$result['type']==='archive_test_anonymous';
            $existing=DB::table('edm_archive_order_metadata')->where('order_id',$locked->id)->first();
            if ($existing) throw new DomainException('ARCHIVE_METADATA_ALREADY_CONFIRMED');
            DB::table('edm_archive_order_metadata')->insert([
                'order_id'=>$locked->id,'account_revision'=>$a->revision,'website'=>$result['website'],'series'=>$series,
                'series_year'=>(int)now('Europe/Istanbul')->format('Y'),'buyer_classification'=>$result['type'],'source_hash'=>$source,
                'metadata_ciphertext'=>Crypt::encryptString(json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),
                'confirmed_by'=>$adminId,'confirmed_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
        return $result;
    }

    public static function verified(Order $order,EdmSetting $account): bool
    {
        $row=DB::table('edm_archive_order_metadata')->where('order_id',$order->id)->first();
        if (!$row||!$order->is_test||!EdmStoreScope::sandboxOrders()||$account->probe_status!=='ok'
            ||$row->account_revision!==$account->revision||$row->website!==EdmStoreScope::website()
            ||(int)$row->series_year!==(int)now('Europe/Istanbul')->format('Y')) return false;
        try {return hash_equals($row->source_hash,self::source($order,$account));}
        catch(\Throwable){return false;}
    }
}
