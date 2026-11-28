<?php
namespace App\Services\Invoices;

use App\Models\Order;
use App\Models\EdmSetting;
use App\Support\InvoiceBuyerIdentifier;
use DomainException;

/** Read-only order preflight. No fiscal number, draft reservation or mutation. */
final class EdmOrderArchivePreflight
{
    public static function documentType(array $result): string
    {
        $d=$result['data']??null;
        if (($result['status']??null)!=='ok'||!is_array($d)||($d['unverified_records']??0)!==0
            ||!is_array($d['aliases']??null)||!is_array($d['sender_aliases']??null)) return 'unknown';
        if (count($d['aliases'])>0) return 'einvoice';
        return count($d['sender_aliases'])===0?'archive_candidate':'unknown';
    }

    public function inspect(Order $order,EdmSetting $account,EdmTestClient $client,string $sellerId,bool $syntheticAnonymous=false,array $metadata=[]): array
    {
        if (!EdmStoreScope::sandboxOrders()||!$order->is_test||!$account->configured()||!$account->revision||$account->probe_status!=='ok'
            ||!InvoiceDispatch::eligible()->whereKey($order->id)->exists()) throw new DomainException('PREFLIGHT_SCOPE');
        $checks=[];$type='unknown';$series=[];$gross=null;$paid=null;
        $checks['website']='ok';
        $checks['buyer']='missing';
        try {
            $id=InvoiceBuyerIdentifier::resolve($order,$syntheticAnonymous);
            if ($id==='11111111111') {
                if ($syntheticAnonymous&&$order->invoice_type==='individual'&&!filled($order->billing_tckn)) {
                    $type='archive_test_anonymous';
                    $checks['buyer']='synthetic';
                }
            } elseif (preg_match('/^[0-9]{10,11}$/D',$id)) {
                if (strlen($id)===11) {
                    $invalid=false;
                    (new \App\Rules\TurkishIdentityNumber)->validate('identifier',$id,function()use(&$invalid){$invalid=true;});
                    if ($invalid) throw new DomainException('INVALID_TCKN');
                }
                $r=$client->taxpayer($account->username,$account->password,$id);
                $this->unchanged($account);
                $type=self::documentType($r);
                $checks['buyer']=$type==='unknown'?'unverified':'ok';
            }
        } catch (DomainException $e) {
            if ($e->getMessage()==='ACCOUNT_CHANGED') throw $e;
        }
        if (in_array($type,['archive_candidate','archive_test_anonymous'],true)) {
            $this->unchanged($account);
            $r=$client->company($account->username,$account->password,$sellerId);
            $this->unchanged($account);
            $s=EdmArchiveSeriesReadiness::inspect($r,(int)now('Europe/Istanbul')->format('Y'));
            $checks['series']=$s['state'];$series=$s['series'];
        } else $checks['series']='not_applicable';

        $checks['recipient_details']=filled($order->customer_name)&&filter_var($order->customer_email,FILTER_VALIDATE_EMAIL)
            &&filled($order->city)&&filled($order->district)&&filled($order->street_address)
            &&($order->invoice_type!=='corporate'||(filled($order->company_title)&&filled($order->tax_office)))?'ok':'missing';
        try {
            $amounts=(new InvoiceAmounts)->preview($order,$account->shipping_vat_rate===null?null:(int)$account->shipping_vat_rate);
            $checks['amounts']=$amounts['complete']?'ok':'missing';
            $gross=$amounts['gross_cents'];
        } catch (DomainException) {$checks['amounts']='unverified';}

        if ($order->payment_method==='paytr') {
            $payments=$order->paytrAttempts()->where('status','success')->where('is_test',true)->get();
            if ($payments->count()===1) {
                $p=$payments->first();
                if ($p->currency==='TRY'&&$p->amount_cents===$order->total_cents&&$p->paid_total_cents!==null
                    &&$p->paid_total_cents>=$p->amount_cents&&in_array($p->reconciliation_result,['callback_success','paid'],true)) $paid=$p->processed_at;
            }
        } elseif ($order->payment_method==='bank_transfer') {
            $payments=$order->paymentNotifications()->where('status','verified')->get();
            if ($payments->count()===1) $paid=$payments->first()->reviewed_at;
        }
        $checks['payment_evidence']=$paid?'ok':'unverified';
        // The system stores processing/review time, not necessarily actual settlement date.
        $checks['actual_payment_date']='requires_confirmation';
        $checks['dispatch_date']='requires_confirmation';
        $checks['carrier_identity']='requires_confirmation';
        $internetDetailsVerified=false;
        if ($metadata!==[] && ($metadata['confirm_metadata']??false) && $paid && $order->placed_at
            && in_array($order->status,['shipped','out_for_delivery','completed'],true)) {
            try {
                $tz='Europe/Istanbul';
                $paymentDate=\Carbon\CarbonImmutable::createFromFormat('!Y-m-d',$metadata['payment_date']??'',$tz);
                $dispatchDate=\Carbon\CarbonImmutable::createFromFormat('!Y-m-d',$metadata['dispatch_date']??'',$tz);
                if (!$paymentDate||!$dispatchDate||$paymentDate->format('Y-m-d')!==($metadata['payment_date']??null)
                    ||$dispatchDate->format('Y-m-d')!==($metadata['dispatch_date']??null)
                    ||$paymentDate->lt($order->placed_at->copy()->timezone($tz)->startOfDay())
                    ||$paymentDate->gt($paid->copy()->timezone($tz)->startOfDay())||$dispatchDate->gt(now($tz)->startOfDay()))
                    throw new DomainException('DATE_UNVERIFIED');
                $method=$order->payment_method==='paytr'?'ODEMEARACISI':'EFT/HAVALE';
                (new EdmInternetSaleDetails)->build(EdmStoreScope::website(),$method,
                    $method==='ODEMEARACISI'?'PAYTR':null,$paymentDate,$dispatchDate,$metadata['carrier_vkn']??'',$metadata['carrier_title']??'');
                foreach (['actual_payment_date','dispatch_date','carrier_identity'] as $field) $checks[$field]='operator_confirmed';
                $internetDetailsVerified=true;
            } catch (\Throwable) {
                // Never replace missing or inconsistent source evidence with guessed values.
            }
        }
        $carrier=$order->shippingShipment?->carrier?->name?:$order->carrier_name;
        $this->unchanged($account);
        return ['type'=>$type,'website'=>EdmStoreScope::website(),'checks'=>$checks,'series'=>$series,
            'gross_cents'=>$gross,'payment_method'=>$order->payment_method,
            'payment_verified_at'=>$paid?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
            'carrier_name'=>is_string($carrier)?$carrier:null,
            'internet_details_prepared'=>$internetDetailsVerified,'submission_allowed'=>false];
    }

    private function unchanged(EdmSetting $account): void
    {
        if (!EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->where('probe_status','ok')->exists())
            throw new DomainException('ACCOUNT_CHANGED');
    }
}
