<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Support\PaytrSignature;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaytrTokenService
{
    private const ENDPOINT = 'https://www.paytr.com/odeme/api/get-token';

    /**
     * Prepared from persisted order snapshots, not client-supplied prices.
     * No external request occurs unless BOTH configuration gates are enabled.
     */
    public function requestIframeToken(
        Order $order,
        PaytrPaymentAttempt $attempt,
        string $userIp,
        string $successUrl,
        string $failureUrl,
    ): string {
        $settings = PaytrSetting::query()->find(1);

        if (! config('newtic.paytr_card_payments_enabled', false)
            || ! $settings?->is_enabled
            || ! $settings->test_mode
            || ! $settings->hasCredentials()
            || $attempt->order_id !== $order->id
            || $order->payment_method !== 'paytr'
            || $order->status !== 'pending_payment'
            || $attempt->status !== 'pending'
            || ! $attempt->is_test
            || ! $order->is_test
            || $attempt->amount_cents !== $order->total_cents
            || $order->currency !== 'TRY'
            || $attempt->currency !== 'TRY') {
            throw new RuntimeException('PAYTR test ödemesi henüz kullanıma açık değil.');
        }

        if (! filter_var($userIp, FILTER_VALIDATE_IP) || strlen($userIp) > 39) {
            throw new \InvalidArgumentException('Invalid customer IP address.');
        }

        foreach ([$successUrl, $failureUrl] as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false
                || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_HOST) !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
                throw new \InvalidArgumentException('Payment return URL must be HTTPS on this store.');
            }
        }

        $order->loadMissing('items');
        if ($order->items->isEmpty() || $order->total_cents <= 0) {
            throw new RuntimeException('PAYTR payment cannot be created without purchased items.');
        }

        $basket = $order->items->map(static function ($item): array {
            $name = $item->product_name;
            if ($item->option_label && $item->option_value) {
                $name .= ' ('.$item->option_label.': '.$item->option_value.')';
            }

            return [
                mb_substr($name, 0, 190),
                number_format($item->unit_price_cents / 100, 2, '.', ''),
                $item->quantity,
            ];
        })->all();

        if ($order->shipping_cents > 0) {
            $basket[] = ['Kargo', number_format($order->shipping_cents / 100, 2, '.', ''), 1];
        }

        $basketEncoded = base64_encode(json_encode(
            $basket, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));

        $address = implode(', ', array_filter([
            $order->street_address, $order->district, $order->city,
        ]));

        $fields = [
            'merchant_id' => $settings->merchant_id,
            'user_ip' => $userIp,
            'merchant_oid' => $attempt->merchant_oid,
            'email' => mb_substr($order->customer_email, 0, 100),
            'payment_amount' => (string) $order->total_cents,
            'user_basket' => $basketEncoded,
            'no_installment' => '1',
            'max_installment' => '0',
            'currency' => 'TL',
            'test_mode' => '1',
            'debug_on' => '0',
            'user_name' => mb_substr($order->customer_name, 0, 60),
            'user_address' => mb_substr($address, 0, 400),
            'user_phone' => mb_substr($order->phone, 0, 20),
            'merchant_ok_url' => $successUrl,
            'merchant_fail_url' => $failureUrl,
            'timeout_limit' => '30',
            'lang' => 'tr',
        ];

        $fields['paytr_token'] = PaytrSignature::iframeToken(
            $fields, $settings->merchant_key, $settings->merchant_salt
        );

        // Never log the POST body, secret credentials or returned iframe token.
        $response = Http::asForm()->acceptJson()
            ->timeout(20)
            ->post(self::ENDPOINT, $fields);

        if (! $response->successful()) {
            throw new RuntimeException('PAYTR bağlantısı başarısız. Daha sonra tekrar deneyin.');
        }

        $json = $response->json();
        if (! is_array($json) || ($json['status'] ?? null) !== 'success'
            || ! is_string($json['token'] ?? null)
            || ! preg_match('/^[A-Za-z0-9]{16,256}$/D', $json['token'])) {
            throw new RuntimeException('PAYTR test tokenı alınamadı. İşlem onaylanmadı.');
        }

        return $json['token'];
    }
}
