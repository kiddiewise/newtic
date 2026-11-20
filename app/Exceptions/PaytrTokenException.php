<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sanitized token failure. The raw provider response is never surfaced to
 * customers, logs or monitoring because it may contain merchant information.
 */
final class PaytrTokenException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        string $message = 'PAYTR test ödeme formu açılamadı.'
    ) {
        parent::__construct($message);
    }

    public static function fromProviderReason(string $reason): self
    {
        $map = [
            'merchant_id' => '/merchant_id/i',
            'merchant_oid' => '/merchant_oid/i',
            'customer_ip' => '/user_ip|(?:^|[^a-z])ip(?:[^a-z]|$)/iu',
            'customer_email' => '/email|eposta|e-posta/iu',
            'signature' => '/paytr_token|imza|hash|token/iu',
            'basket' => '/user_basket|sepet/iu',
            'amount' => '/payment_amount|tutar/iu',
            'merchant_account' => '/mağaza|magaza|merchant|parola|salt|anahtar/iu',
            'store_domain' => '/domain|site|alan adı|url/iu',
        ];
        foreach ($map as $safeCode => $pattern) {
            if (preg_match($pattern, $reason)) {
                return new self($safeCode);
            }
        }

        return new self('gateway_rejected');
    }
}
