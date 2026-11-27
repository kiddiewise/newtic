<?php

namespace App\Services\Invoices;

use App\Models\InvoicePreparation;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** One immutable local calculation snapshot per order. No fiscal number,
 * provider call, customer delivery, or transition to an issued document. */
final class InvoicePreparationService
{
    public function __construct(private InvoiceAmounts $amounts) {}

    public function prepare(Order $order, ?int $shippingRate): InvoicePreparation
    {
        return DB::transaction(function () use ($order,$shippingRate) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->status === 'cancelled') throw new DomainException('İptal edilen sipariş için taslak oluşturulamaz.');
            if ($locked->shipping_cents === 0) $shippingRate = null;
            $amounts = $this->amounts->preview($locked,$shippingRate);
            if (!$amounts['complete']) {
                $missingProduct=collect($amounts['lines'])->contains(fn($line)=>$line['kind']==='product'&&$line['rate']===null);
                if ($missingProduct) throw new DomainException('Ürün KDV oranı eksik. Fatura yönetiminde eksik ürün KDV oranını onaylayın.');
                throw new DomainException('Kargo KDV oranı eksik. Kargo KDV oranını seçin.');
            }
            $hash = hash('sha256',json_encode($amounts,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $existing = InvoicePreparation::query()->where('order_id',$locked->getKey())->first();
            if ($existing) {
                if (!hash_equals($existing->snapshot_hash,$hash)) throw new DomainException('Mevcut taslağın hesap kaydı farklı. İkinci ETTN oluşturulmadı.');
                return $existing;
            }
            return InvoicePreparation::create([
                'order_id'=>$locked->getKey(),'uuid'=>(string)Str::uuid(),
                'gross_cents'=>$amounts['gross_cents'],'shipping_rate'=>$shippingRate,
                'snapshot_hash'=>$hash,'amounts'=>$amounts,
            ]);
        },3);
    }
}
