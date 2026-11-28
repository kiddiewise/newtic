<?php
namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\InvoicePreparation;
use App\Models\Order;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Durable order-driven invoicing requests. Sandbox destination is isolated
 * from live customer data and financial reporting. No automatic resubmission. */
final class InvoiceDispatch
{
    public const STATUSES=['preparing','shipped','out_for_delivery','completed'];
    public static function staging(): bool {
        return EdmStoreScope::sandboxOrders();
    }
    public static function ready(?EdmSetting $account): bool {
        return self::staging() && $account?->configured() && $account->revision && $account->probe_status==='ok'
            && DB::table('edm_sandbox_trials')->whereIn('uuid',[EdmSecondSandboxFixture::UUID,EdmSecondSandboxFixture::NAMESPACE_UUID])
            ->where('account_revision',$account->revision)->where('load_status','draft_acknowledged')
            ->whereNotNull('allocated_invoice_id')->exists()
            && is_file(self::templatePath());
    }
    public static function templatePath(): string { return storage_path('app/private/edm-rehearsals/order-general.xslt'); }
    public static function eligible(): Builder {
        return Order::query()->whereIn('status',self::STATUSES)->where('currency','TRY')
            ->where(function(Builder $q){
                $q->where(fn(Builder $b)=>$b->where('payment_method','bank_transfer')->whereHas('paymentNotifications',fn(Builder $n)=>$n->where('status','verified')))
                    ->orWhere(fn(Builder $p)=>$p->where('payment_method','paytr')->whereHas('paytrAttempts',fn(Builder $a)=>$a->where('status','success')->whereNotNull('paid_total_cents')->whereColumn('paid_total_cents','>=','amount_cents')->whereColumn('amount_cents','orders.total_cents')->whereColumn('paytr_payment_attempts.is_test','orders.is_test')));
            });
    }
    public static function pending(): Builder {
        return self::eligible()->when(self::staging(),fn(Builder $q)=>$q->where('is_test',true))->whereNotExists(fn($q)=>$q->selectRaw('1')->from('invoice_dispatch_requests as q')->whereColumn('q.order_id','orders.id'))
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('invoice_preparations as p')->join('edm_submission_attempts as a','a.invoice_preparation_id','=','p.id')->whereColumn('p.order_id','orders.id'));
    }
    public function enqueue(int $orderId,?int $actor,?int $shippingRate,string $source='manual',?int $missingProductRate=null): void {
        DB::transaction(function()use($orderId,$actor,$shippingRate,$source,$missingProductRate){
            $account=EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
            if (!self::staging()||!$account?->configured()||!$account->revision||$account->probe_status!=='ok') throw new DomainException('EDM bağlantısı hazır değil.');
            if ($source==='auto'&&(!$account->auto_invoice||!$account->auto_invoice_since)) throw new DomainException('Otomatik faturalandırma kapalı.');
            $order=Order::query()->lockForUpdate()->findOrFail($orderId);
            if (!$order->is_test||!self::eligible()->whereKey($orderId)->exists()) throw new DomainException('Sipariş ödeme ve ortam koşullarını karşılamıyor.');
            if ($source==='auto'&&($order->status!=='completed'||!$order->statusChanges()->where('to_status','completed')->where('created_at','>=',$account->auto_invoice_since)->exists())) throw new DomainException('Otomatik fatura koşulu sağlanmadı.');
            if (DB::table('invoice_dispatch_requests')->where('order_id',$orderId)->exists()
                ||DB::table('invoice_preparations as p')->join('edm_submission_attempts as a','a.invoice_preparation_id','=','p.id')->where('p.order_id',$orderId)->exists()) throw new DomainException('Bu sipariş için fatura işlemi zaten mevcut.');
            $rate=$order->shipping_cents>0?($shippingRate??$account->shipping_vat_rate):null;
            // An existing local preview already fixes its tax calculation.
            $existing=InvoicePreparation::query()->where('order_id',$orderId)->first();
            if ($existing) $rate=$existing->shipping_rate;
            // Historical snapshots are not guessed from today's product rate.
            // Explicit operator confirmation fills only missing order lines.
            $missing=$order->items()->whereNull('vat_rate')->lockForUpdate()->get(['id','vat_rate']);
            if ($missing->isNotEmpty()) {
                if ($existing) throw new DomainException('Mevcut taslakta KDV kaydı değiştirilemez.');
                if ($source!=='manual'||$actor===null||!in_array($missingProductRate,[1,10,20],true)) {
                    throw new DomainException('Ürün KDV oranı eksik. Eksik ürün KDV alanından geçerli oranı onaylayın.');
                }
                foreach ($missing as $item) {
                    $item->forceFill(['vat_rate'=>$missingProductRate])->save();
                    DB::table('invoice_vat_confirmations')->insert(['order_item_id'=>$item->id,'confirmed_by'=>$actor,
                        'vat_rate'=>$missingProductRate,'created_at'=>now()]);
                }
            }
            if ($order->shipping_cents>0 && $rate===null) throw new DomainException('Kargo KDV oranı eksik. Kargo KDV alanından oran seçin.');
            app(InvoicePreparationService::class)->prepare($order,$rate===null?null:(int)$rate);
            DB::table('invoice_dispatch_requests')->insert(['order_id'=>$orderId,'requested_by'=>$actor,'account_revision'=>$account->revision,
                'shipping_rate'=>$rate,'source'=>$source,'state'=>'queued','created_at'=>now(),'updated_at'=>now()]);
        },3);
    }
    public function process(int $limit=5): array {
        // A crash after the durable processing reservation is ambiguous.
        // Never return it to the queue or automatically resend.
        if (self::staging()) DB::table('invoice_dispatch_requests')->where('state','processing')
            ->where('started_at','<',now()->subMinutes(15))->update(['state'=>'uncertain','diagnostic'=>'WORKER_INTERRUPTED','updated_at'=>now()]);
        $account=EdmSetting::query()->find(1);
        if (!self::ready($account)) return ['processed'=>0,'connection_pending'=>true];
        if ($account->auto_invoice&&$account->auto_invoice_since) {
            $ids=self::pending()->where('is_test',true)->where('status','completed')
                ->whereHas('statusChanges',fn($q)=>$q->where('to_status','completed')->where('created_at','>=',$account->auto_invoice_since))
                ->limit($limit)->pluck('id');
            foreach ($ids as $id) { try {$this->enqueue($id,null,null,'auto');} catch(DomainException) {} }
        }
        $ids=DB::table('invoice_dispatch_requests')->where('state','queued')->orderBy('id')->limit($limit)->pluck('id');
        $count=0;
        foreach ($ids as $id) {
            $request=DB::transaction(function()use($id){
                $r=DB::table('invoice_dispatch_requests')->where('id',$id)->lockForUpdate()->first();
                $a=EdmSetting::query()->find(1);
                if (!$r||$r->state!=='queued'||!self::ready($a)) return null;
                if ($a->revision!==$r->account_revision||($r->source==='auto'&&!$a->auto_invoice)) {
                    DB::table('invoice_dispatch_requests')->where('id',$id)->update(['state'=>'blocked','diagnostic'=>'ACCOUNT_OR_MODE_CHANGED','updated_at'=>now()]);
                    return null;
                }
                DB::table('invoice_dispatch_requests')->where('id',$id)->update(['state'=>'processing','started_at'=>now(),'updated_at'=>now()]);
                return $r;
            },3);
            if (!$request) continue;
            $state='uncertain'; $diagnostic='PROCESSING_UNCONFIRMED';
            try {
                $result=app(EdmTestClient::class)->loadQueuedOrderDraft($request);
                $state=$result['load_status']==='draft_acknowledged'?'acknowledged':'uncertain';
                $diagnostic=$result['diagnostic']??null;
            } catch(DomainException $e) {
                $state='blocked'; $diagnostic=preg_match('/^[A-Z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'ORDER_VALIDATION_FAILED';
            } catch(Throwable) {}
            DB::table('invoice_dispatch_requests')->where('id',$id)->where('state','processing')->update(['state'=>$state,'diagnostic'=>$diagnostic,'updated_at'=>now()]);
            $count++;
        }
        return ['processed'=>$count,'connection_pending'=>false];
    }
}
