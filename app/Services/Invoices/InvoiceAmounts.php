<?php

namespace App\Services\Invoices;

use App\Models\Order;
use DomainException;

/**
 * Local monetary preview only; not a fiscal document or authority to send.
 * All monetary inputs and output are integer kuruş. Round net at line level
 * (half up), derive tax as gross minus net, then aggregate the same lines.
 */
final class InvoiceAmounts
{
    private const MAX_CENTS = 1_000_000_000_000;
    private const RATES = [1, 10, 20];

    public static function split(int $gross, int $rate): array
    {
        self::money($gross);
        if (!in_array($rate,self::RATES,true)) throw new DomainException('Desteklenmeyen KDV oranı.');
        $denominator = 100 + $rate;
        $net = intdiv($gross * 100 + intdiv($denominator,2),$denominator);
        return ['gross_cents'=>$gross,'net_cents'=>$net,'tax_cents'=>$gross-$net,'rate'=>$rate];
    }

    private static function money(mixed $value): int
    {
        if (!is_int($value) || $value < 0 || $value > self::MAX_CENTS) {
            throw new DomainException('Sipariş tutarı geçersiz veya hesaplama sınırını aşıyor.');
        }
        return $value;
    }

    public function preview(Order $order, ?int $shippingRate = null): array
    {
        if ($order->currency !== 'TRY') throw new DomainException('Bu hesaplama yalnızca TRY siparişlerini destekler.');
        if ($shippingRate !== null && !in_array($shippingRate,self::RATES,true)) throw new DomainException('Kargo KDV oranı geçersiz.');
        $order->loadMissing('items');
        if ($order->items->isEmpty()) throw new DomainException('Siparişte ürün kaydı bulunamadı.');

        $subtotal = self::money($order->subtotal_cents);
        $shipping = self::money($order->shipping_cents);
        $total = self::money($order->total_cents);
        $lines = [];
        $groups = [];
        $unknown = 0;
        $productGross = 0;
        $append = function (string $label, int $qty, int $unit, int $gross, ?int $rate, string $kind) use (&$lines,&$groups,&$unknown): void {
            if ($rate === null) {
                $amounts = ['gross_cents'=>$gross,'net_cents'=>null,'tax_cents'=>null,'rate'=>null];
                $unknown = self::money($unknown+$gross);
            } else {
                $amounts = self::split($gross,$rate);
                $groups[$rate] ??= ['rate'=>$rate,'gross_cents'=>0,'net_cents'=>0,'tax_cents'=>0];
                foreach (['gross_cents','net_cents','tax_cents'] as $key) {
                    $groups[$rate][$key] = self::money($groups[$rate][$key]+$amounts[$key]);
                }
            }
            $lines[] = ['label'=>$label,'quantity'=>$qty,'unit_gross_cents'=>$unit,'kind'=>$kind]+$amounts;
        };

        foreach ($order->items as $item) {
            $unit = self::money($item->unit_price_cents);
            $gross = self::money($item->line_total_cents);
            $qty = $item->quantity;
            if (!is_int($qty) || $qty < 1 || $qty > 1_000_000 || $unit > intdiv(self::MAX_CENTS,$qty) || $unit*$qty !== $gross) {
                throw new DomainException('Ürün satırı miktar ve fiyat toplamı uyuşmuyor.');
            }
            $label = $item->product_name.($item->option_value ? ' · '.$item->option_value : '');
            $append($label,$qty,$unit,$gross,$item->vat_rate,'product');
            $productGross = self::money($productGross+$gross);
        }
        if ($productGross !== $subtotal || self::money($subtotal+$shipping) !== $total) {
            throw new DomainException('Sipariş toplamı kayıtlı ürün ve kargo tutarlarıyla uyuşmuyor.');
        }
        if ($shipping > 0) $append('Kargo',1,$shipping,$shipping,$shippingRate,'shipping');
        ksort($groups);
        $complete = !in_array(null,array_column($lines,'rate'),true);
        $net = array_sum(array_column($groups,'net_cents'));
        $tax = array_sum(array_column($groups,'tax_cents'));
        if ($net+$tax+$unknown !== $total) throw new DomainException('KDV hesaplaması siparişle uyuşmuyor.');

        return [
            'lines'=>$lines,'groups'=>array_values($groups),'gross_cents'=>$total,
            'known_net_cents'=>$net,'known_tax_cents'=>$tax,'unclassified_cents'=>$unknown,
            'complete'=>$complete,'net_cents'=>$complete ? $net : null,'tax_cents'=>$complete ? $tax : null,
            'shipping_assumption'=>$shipping>0 && $shippingRate!==null,
        ];
    }
}
