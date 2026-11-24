<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturnRequest;
use App\Models\OrderStatusChange;
use App\Models\Product;
use App\Support\OrderPaymentBreakdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OrderReturnService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['return' => $message]);
    }

    public function create(Order $order, array $data, ?int $actor): OrderReturnRequest
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') $this->fail('İptal edilen sipariş için yeni talep açılamaz.');
            if ($order->returnRequests()->whereIn('status', OrderReturnRequest::ACTIVE)->exists()) {
                $this->fail('Bu siparişin açık talebi zaten var. Mevcut talebi takip edebilirsiniz.');
            }
            $kind = $data['kind'];
            // Accept a withdrawal notice even during shipping. Do not promise
            // that a carrier can still stop the outbound parcel.
            $lines = $order->items()->get()->keyBy('id');
            $used = [];
            foreach ($order->returnRequests()->where('status', 'completed')->get() as $prior) {
                foreach ($prior->items as $item) $used[$item['id']] = ($used[$item['id']] ?? 0) + $item['quantity'];
            }
            $selected = [];
            foreach ($data['items'] as $id => $qty) {
                $qty = (int) $qty;
                if ($qty === 0) continue;
                $line = $lines->get($id);
                if (!$line || $qty < 0 || $qty > $line->quantity - ($used[$id] ?? 0)) {
                    $this->fail('Seçilen ürün veya adet bu sipariş için geçerli değil.');
                }
                $selected[] = ['id' => $line->id, 'product_id' => $line->product_id,
                    'name' => $line->product_name, 'option' => $line->option_value,
                    'quantity' => $qty, 'cents' => $line->unit_price_cents * $qty];
            }
            if (!$selected) $this->fail('En az bir ürün ve adet seçin.');
            if (in_array($order->status, ['pending_payment','preparing'], true) && collect($selected)->sum('quantity') !== $lines->sum('quantity')) {
                $this->fail('Gönderim öncesinde tam sipariş iptali seçin. Kısmi gönderim henüz desteklenmiyor.');
            }
            $itemsCents = array_sum(array_column($selected, 'cents'));
            $case = $order->returnRequests()->create([
                'reference' => 'IR'.Str::upper(Str::random(24)), 'kind' => $kind,
                'status' => 'requested', 'reason' => $data['reason'],
                'customer_note' => $data['note'] ?? null, 'items' => $selected,
                'items_cents' => $itemsCents,
            ]);
            $this->event($case, 'requested', $actor);
            return $case;
        }, 3);
    }

    public function review(OrderReturnRequest $case, array $data, int $actor): void
    {
        DB::transaction(function () use ($case, $data, $actor) {
            $order = Order::query()->whereKey($case->order_id)->lockForUpdate()->firstOrFail();
            $case = OrderReturnRequest::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            if (!in_array($case->status, OrderReturnRequest::ACTIVE, true)) $this->fail('Bu talep sonuçlandırılmış.');
            if ($action === 'complete_unpaid' && $order->status === 'cancelled' && $case->refund_status === 'none'
                && !$order->paytrAttempts()->whereIn('status',['pending','success'])->exists()
                && !$order->paymentNotifications()->whereIn('status',['pending','verified'])->exists()) {
                $case->status = 'completed';
                $case->handled_by = $actor;
                $case->save();
                $this->event($case, 'already_cancelled', $actor);
                return;
            }
            if ($case->refund_status !== 'none' && !($action === 'received' && $case->status === 'refund_review' && !$case->received_at)) $this->fail('Finansal işlem başladı; önce sonucunu kontrol edin.');
            if ($action === 'approve' && $case->status === 'requested') {
                $case->status = 'approved';
            } elseif ($action === 'return_code' && in_array($case->status, ['approved','awaiting_return'], true)) {
                if (blank($data['return_carrier'] ?? null) || blank($data['return_code'] ?? null)) $this->fail('Taşıyıcı ve geçerli iade kodunu girin.');
                $case->return_carrier = $data['return_carrier'];
                $case->return_code = $data['return_code'];
                $case->status = 'awaiting_return';
            } elseif ($action === 'received' && in_array($case->status, ['approved','awaiting_return','refund_review'], true) && !$case->received_at) {
                $case->received_at = now();
                if ($case->status !== 'refund_review') $case->status = 'received';
            } elseif ($action === 'reject' && $case->status === 'requested') {
                if (blank($data['public_note'] ?? null)) $this->fail('Müşteriye açık gerekçeyi yazın; yasal haklar saklıdır.');
                $case->status = 'rejected';
            } elseif ($action === 'complete_unpaid' && $case->status === 'approved') {
                // Full unpaid bank cancellation only. Never infer non-payment
                // from a missing PAYTR callback or an unchecked bank claim.
                if ($order->status !== 'pending_payment' || $order->payment_method !== 'bank_transfer'
                    || $order->paymentNotifications()->whereIn('status', ['pending','verified'])->exists()
                    || $order->shippingShipment()->exists() || filled($order->tracking_number)
                    || !$this->allItems($order, $case) || empty($data['confirmed'])) {
                    $this->fail('Yalnızca banka hareketi kontrol edilmiş, gönderilmemiş, ödenmemiş tam sipariş iptal edilebilir.');
                }
                $this->restock($case);
                $this->cancelOrder($order, $actor);
                $case->status = 'completed';
            } else {
                $this->fail('Bu aşamada seçilen işlem yapılamaz.');
            }
            if (filled($data['public_note'] ?? null)) $case->public_note = $data['public_note'];
            $case->handled_by = $actor;
            $case->save();
            $this->event($case, $action, $actor);
        }, 3);
    }

    public function reserveRefund(OrderReturnRequest $case, array $data, int $actor): OrderReturnRequest
    {
        return DB::transaction(function () use ($case, $data, $actor) {
            $order = Order::query()->whereKey($case->order_id)->lockForUpdate()->firstOrFail();
            $case = OrderReturnRequest::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            if (!in_array($case->status, ['approved','awaiting_return','received'], true) || $case->refund_status !== 'none'
                || empty($data['confirmed']) || !OrderPaymentBreakdown::fromOrder($order)['payment_verified']) {
                $this->fail('İade için ödeme doğrulaması, talep onayı ve yönetici onayı gerekir.');
            }
            if ($order->status === 'cancelled') $this->fail('İptal edilen siparişin ödemesi ayrıca incelenmelidir.');
            // A separate cancellation never claims the parcel has stopped.
            // An administrator may have to refund before physical receipt due to legal deadlines.
            // The shipment is never marked cancelled and stock remains reserved until receipt.
            $usedShipping = (int) $order->returnRequests()->where('id','!=',$case->id)
                ->whereIn('refund_status', ['processing','uncertain','submitted','verified'])->sum('shipping_cents');
            $shipping = !empty($data['shipping']) ? max(0, $order->shipping_cents - $usedShipping) : 0;
            // Full withdrawal before shipping includes the original delivery charge.
            if ($this->allItems($order, $case) && ($case->reason === 'withdrawal' || in_array($order->status, ['pending_payment','preparing'], true))) $shipping = max(0, $order->shipping_cents - $usedShipping);
            $amount = $case->items_cents + $shipping;
            $used = (int) $order->returnRequests()->where('id','!=',$case->id)
                ->whereIn('refund_status', ['processing','uncertain','submitted','verified'])->sum('refund_cents');
            if ($amount <= 0 || $amount > $order->total_cents - $used) $this->fail('İade tutarı kalan sipariş bedelini aşıyor.');
            $case->forceFill(['shipping_cents' => $shipping, 'refund_cents' => $amount,
                'refund_status' => 'processing', 'status' => 'refund_review', 'handled_by' => $actor])->save();
            $this->event($case, 'refund_reserved', $actor);
            return $case;
        }, 3);
    }

    public function refundResult(OrderReturnRequest $case, string $state, int $actor, ?string $bankReference = null): void
    {
        DB::transaction(function () use ($case, $state, $actor, $bankReference) {
            $order = Order::query()->whereKey($case->order_id)->lockForUpdate()->firstOrFail();
            $case = OrderReturnRequest::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            if (!in_array($case->refund_status, ['processing','uncertain','submitted'], true)
                || !in_array($state, ['uncertain','submitted','verified','none'], true)) $this->fail('İade işlemi bu aşamada güncellenemez.');
            $case->refund_status = $state;
            if ($bankReference !== null) $case->bank_reference = $bankReference;
            if ($state === 'none') {
                $case->status = $case->received_at ? 'received' : 'approved';
                $case->refund_cents = 0;
                $case->shipping_cents = 0;
            }
            // Verified refund is kept separate from physical stock return.
            $case->save();
            $this->event($case, 'refund_'.$state, $actor);
        }, 3);
    }

    public function finish(OrderReturnRequest $case, array $data, int $actor): void
    {
        DB::transaction(function () use ($case, $data, $actor) {
            $order = Order::query()->whereKey($case->order_id)->lockForUpdate()->firstOrFail();
            $case = OrderReturnRequest::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') $this->fail('Sipariş başka bir işlemle iptal edilmiş. Stok kontrolü gerekir.');
            if ($case->status !== 'refund_review' || $case->refund_status !== 'verified') $this->fail('Önce para iadesini doğrulayın.');
            $unshipped = !$order->shippingShipment()->exists() && blank($order->tracking_number) && $order->status === 'preparing';
            if (!$case->received_at && !$unshipped) $this->fail('Fiziksel iade sürecini sonuçlandırmadan talebi kapatmayın.');
            if (!empty($data['restock'])) {
                if (!$case->received_at && !$unshipped) $this->fail('Ürün geri alınmadan stok artırılamaz.');
                $this->restock($case);
            }
            if ($this->allItems($order, $case) && $unshipped && $case->refund_cents === $order->total_cents) {
                $this->cancelOrder($order, $actor);
            }
            $case->status = 'completed';
            $case->save();
            $this->event($case, 'completed', $actor);
        }, 3);
    }

    private function allItems(Order $order, OrderReturnRequest $case): bool
    {
        $selected = collect($case->items)->keyBy('id');
        return $order->items()->get()->every(fn ($line) => (int)($selected[$line->id]['quantity'] ?? 0) === $line->quantity);
    }

    private function restock(OrderReturnRequest $case): void
    {
        if ($case->restocked_at) $this->fail('Stok daha önce geri alındı.');
        foreach (collect($case->items)->sortBy('product_id') as $item) {
            if ($item['product_id']) Product::query()->whereKey($item['product_id'])->where('tracks_stock', true)
                ->increment('stock_quantity', $item['quantity']);
        }
        $case->restocked_at = now();
    }

    private function cancelOrder(Order $order, int $actor): void
    {
        $previous = $order->status;
        $order->update(['status' => 'cancelled']);
        OrderStatusChange::create(['order_id' => $order->id, 'user_id' => $actor,
            'from_status' => $previous, 'to_status' => 'cancelled', 'source' => 'admin_forward', 'created_at' => now()]);
    }

    private function event(OrderReturnRequest $case, string $event, ?int $actor): void
    {
        DB::table('order_return_events')->insert(['order_return_request_id' => $case->id,
            'user_id' => $actor, 'event' => $event, 'created_at' => now()]);
    }
}
