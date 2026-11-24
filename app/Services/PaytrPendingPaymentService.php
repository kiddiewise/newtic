<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaytrPendingPaymentService
{
    private function validContext(Order $order, PaytrPaymentAttempt $attempt): bool
    {
        return $order->status === 'pending_payment' && $order->payment_method === 'paytr'
            && $attempt->status === 'pending' && $attempt->order_id === $order->id
            && $order->is_test === $attempt->is_test && $order->currency === 'TRY'
            && $attempt->currency === 'TRY' && $order->total_cents > 0
            && $order->total_cents === $attempt->amount_cents
            && $order->paytrAttempts()->count() === 1;
    }

    private function closeUnopened(int $id): ?string
    {
        return DB::transaction(function () use ($id) {
            $attempt = PaytrPaymentAttempt::query()->whereKey($id)->lockForUpdate()->first();
            if (!$attempt || $attempt->status !== 'pending') return 'unchanged';
            $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            if (!$this->validContext($order,$attempt)) return 'unchanged';
            if (!$attempt->issuance_tracked || $attempt->token_request_started_at) return null;
            if ($attempt->canRequestToken()) return 'not_due';
            // Proven no external request was ever started for this attempt.
            // A token request and this cancellation cannot race past the lock.
            if ($order->shippingShipment()->exists() || filled($order->tracking_number)
                || $order->paymentNotifications()->whereIn('status',['pending','verified'])->exists()
                || $order->returnRequests()->whereIn('status', \App\Models\OrderReturnRequest::ACTIVE)->exists()) {
                $attempt->update(['reconciliation_checked_at'=>now(),'reconciliation_result'=>'context_mismatch']);
                return 'context_mismatch';
            }
            $quantities = $order->items()->whereNotNull('product_id')
                ->selectRaw('product_id, SUM(quantity) AS qty')->groupBy('product_id')->pluck('qty','product_id');
            foreach (Product::query()->whereIn('id',$quantities->keys())->where('tracks_stock',true)
                ->orderBy('id')->lockForUpdate()->get() as $product) {
                $product->increment('stock_quantity',(int)$quantities[$product->id]);
            }
            $attempt->update(['status'=>'failed','processed_at'=>now(),'failed_reason_code'=>'UNOPENED',
                'failed_reason_message'=>'Ödeme ekranı açılmadan süre doldu.',
                'reconciliation_checked_at'=>now(),'reconciliation_result'=>'unopened_expired']);
            $order->update(['status'=>'cancelled']);
            $this->event($order,'cancelled','paytr_unopened_expiry','Ödeme formu hiç istenmedi; ayrılan stok geri verildi.');
            return 'unopened_expired';
        },3);
    }

    private function cents(mixed $value): int
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw new RuntimeException('amount');
        if (!preg_match('/^([0-9]{1,10})(?:[.,]([0-9]{1,2}))?$/D',(string)$value,$parts)) throw new RuntimeException('amount');
        return (int)$parts[1]*100 + (int)str_pad($parts[2] ?? '',2,'0');
    }

    private function query(PaytrPaymentAttempt $attempt, PaytrSetting $settings): array
    {
        $token = base64_encode(hash_hmac('sha256',(string)$settings->merchant_id.$attempt->merchant_oid.$settings->merchant_salt,$settings->merchant_key,true));
        $response = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(10)
            ->withOptions(['allow_redirects'=>false,'verify'=>true])
            ->post('https://www.paytr.com/odeme/durum-sorgu',[
                'merchant_id'=>(string)$settings->merchant_id,'merchant_oid'=>$attempt->merchant_oid,'paytr_token'=>$token,
            ]);
        $data = $response->json();
        if (!$response->successful() || !is_array($data)) throw new RuntimeException('response');
        if (($data['status'] ?? null) === 'error' && (string)($data['err_no'] ?? '') === '004') {
            return ['result'=>'no_success_yet'];
        }
        if (($data['status'] ?? null) !== 'success') return ['result'=>'provider_error'];
        // A successful query reports actual payment and original amount in TL,
        // unlike the callback (integer kuruş). Require exact context match.
        if (!in_array($data['currency'] ?? null,['TL','TRY'],true)
            || (string)($data['test_mode'] ?? '') !== ($attempt->is_test ? '1' : '0')
            || ($data['odeme_tipi'] ?? null) !== 'KART'
            || $this->cents($data['payment_amount'] ?? null) !== $attempt->amount_cents
            || ($paid = $this->cents($data['payment_total'] ?? null)) < $attempt->amount_cents) {
            return ['result'=>'context_mismatch'];
        }
        if (array_key_exists('returns',$data) && $data['returns'] !== []) return ['result'=>'external_refund'];
        return ['result'=>'paid','paid'=>$paid];
    }

    public function reconcile(int $id): string
    {
        $closed = $this->closeUnopened($id);
        if ($closed !== null) return $closed;
        $attempt = PaytrPaymentAttempt::query()->find($id);
        if (!$attempt || $attempt->status !== 'pending') return 'unchanged';
        $settings = PaytrSetting::query()->find(1);
        $data = ['result'=>'provider_error'];
        if ($settings?->hasCredentials() && ($attempt->is_test || !config('newtic.checkout_test_mode',true))) {
            try { $data = $this->query($attempt,$settings); }
            catch (\Throwable) { /* retain stock; never log response or secret */ }
        }

        return DB::transaction(function () use ($attempt,$data,$settings) {
            $locked = PaytrPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            if (!$this->validContext($order,$locked)) return 'unchanged';
            $result = $data['result'];
            // A later callback may have already settled the row. A known
            // external refund must stay visible until reviewed, not disappear.
            if ($locked->reconciliation_result === 'external_refund' && $result !== 'external_refund') $result = 'external_refund';
            $current = PaytrSetting::query()->find(1);
            if ($result === 'paid' && (!$current || !$settings
                || $current->merchant_id !== $settings->merchant_id
                || $current->merchant_key !== $settings->merchant_key
                || $current->merchant_salt !== $settings->merchant_salt)) $result = 'context_mismatch';
            $locked->update(['reconciliation_result'=>$result,'reconciliation_checked_at'=>now()]);
            if ($result === 'paid') {
                $locked->update(['status'=>'success','paid_total_cents'=>$data['paid'],'processed_at'=>now()]);
                $order->update(['status'=>'preparing']);
                $this->event($order,'preparing','paytr_status_query','Ödeme PayTR durum sorgusu ile doğrulandı.');
            }
            // 004 is not terminal failure. Never cancel or restock here.
            return $result;
        },3);
    }

    private function event(Order $order,string $status,string $source,string $reason): void
    {
        OrderStatusChange::create(['order_id'=>$order->id,'user_id'=>null,'from_status'=>'pending_payment',
            'to_status'=>$status,'source'=>$source,'reason'=>$reason,'created_at'=>now()]);
    }
}
