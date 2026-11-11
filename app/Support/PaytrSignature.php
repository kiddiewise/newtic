<?php

namespace App\Support;

final class PaytrSignature
{
    /** PAYTR iFrame API step 1, as defined in the merchant documentation. */
    public static function iframeToken(array $fields, string $merchantKey, string $merchantSalt): string
    {
        $data = '';
        foreach ([
            'merchant_id', 'user_ip', 'merchant_oid', 'email',
            'payment_amount', 'user_basket', 'no_installment',
            'max_installment', 'currency', 'test_mode',
        ] as $field) {
            if (! array_key_exists($field, $fields) || ! is_scalar($fields[$field])) {
                throw new \InvalidArgumentException('PAYTR token field is missing: '.$field);
            }
            $data .= (string) $fields[$field];
        }

        return base64_encode(hash_hmac('sha256', $data.$merchantSalt, $merchantKey, true));
    }

    /** PAYTR iFrame API step 2 callback hash. */
    public static function notificationHash(
        string $merchantOid,
        string $status,
        string $totalAmount,
        string $merchantKey,
        string $merchantSalt,
    ): string {
        return base64_encode(hash_hmac(
            'sha256', $merchantOid.$merchantSalt.$status.$totalAmount, $merchantKey, true
        ));
    }

    public static function notificationIsValid(
        array $payload,
        string $merchantKey,
        string $merchantSalt,
    ): bool {
        foreach (['merchant_oid', 'status', 'total_amount', 'hash'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field])) {
                return false;
            }
        }

        if (! preg_match('/^[A-Za-z0-9]{1,64}$/D', $payload['merchant_oid'])
            || ! in_array($payload['status'], ['success', 'failed'], true)
            || ! preg_match('/^[0-9]{1,16}$/D', $payload['total_amount'])
            || strlen($payload['hash']) !== 44) {
            return false;
        }

        return hash_equals(
            self::notificationHash(
                $payload['merchant_oid'], $payload['status'],
                $payload['total_amount'], $merchantKey, $merchantSalt
            ),
            $payload['hash'],
        );
    }
}
