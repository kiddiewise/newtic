<?php

namespace App\Support;

use App\Models\Order;
use DomainException;

/** Never substitute a fictional identity for a known taxpayer. The calling
 * invoice service must explicitly establish that the anonymous consumer
 * fallback is permitted for the particular e-Arsiv document. */
final class InvoiceBuyerIdentifier
{
    public static function resolve(Order $order, bool $anonymousArchivePermitted = false): string
    {
        if ($order->invoice_type === 'corporate') {
            $id = (string) $order->tax_number;
            if (preg_match('/^[0-9]{10,11}$/D', $id) && $id !== '11111111111') return $id;
            throw new DomainException('Kurumsal fatura için geçerli vergi bilgisi gerekiyor.');
        }
        if ($order->billing_tckn !== null && $order->billing_tckn !== '') return $order->billing_tckn;
        if ($anonymousArchivePermitted && $order->invoice_type === 'individual') return '11111111111';
        throw new DomainException('Fatura için alıcı kimlik bilgisi veya doğrulanmış nihai tüketici istisnası gerekiyor.');
    }
}
