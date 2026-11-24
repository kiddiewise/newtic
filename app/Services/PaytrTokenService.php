<?php

namespace App\Services;

use App\Exceptions\PaytrTokenException;
use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Support\PaytrSignature;
use App\Support\PaytrTestAccess;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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

        if (! PaytrTestAccess::available($settings)
            || $attempt->order_id !== $order->id
            || $order->payment_method !== 'paytr'
            || $order->status !== 'pending_payment'
            || $attempt->status !== 'pending'
            || $attempt->is_test !== $order->is_test
            || $attempt->is_test !== $settings->test_mode
            || $attempt->amount_cents !== $order->total_cents
            || $order->currency !== 'TRY'
            || $attempt->currency !== 'TRY') {
            throw new RuntimeException('PAYTR ödemesi henüz kullanıma açık değil.');
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
            'no_installment' => $settings->installments_enabled ? '0' : '1',
            'max_installment' => $settings->installments_enabled ? (string) ($settings->max_installment ?? 0) : '0',
            'currency' => 'TL',
            'test_mode' => $attempt->is_test ? '1' : '0',
            'debug_on' => $attempt->is_test ? '1' : '0',
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

        // Persist the fact that a provider request MAY be sent before doing
        // network I/O. A timeout or crash is not evidence of non-issuance.
        // Lock in the same order as callback/reconciliation: attempt, order.
        DB::transaction(function () use ($attempt, $order): void {
            $locked = PaytrPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canRequestToken() || $lockedOrder->status !== 'pending_payment'
                || $locked->order_id !== $lockedOrder->id
                || $lockedOrder->returnRequests()->whereIn('status', \App\Models\OrderReturnRequest::ACTIVE)->exists()) {
                throw new RuntimeException('PAYTR payment window closed.');
            }
            $locked->update(['token_request_started_at' => now()]);
        }, 3);

        // Never log the POST body, secret credentials or returned iframe token.
        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)
                ->withOptions(['allow_redirects'=>false, 'verify'=>true])
                ->post(self::ENDPOINT, $fields);
        } catch (ConnectionException $exception) {
            throw new PaytrTokenException('connection_failed');
        }

        if (! $response->successful()) {
            throw new PaytrTokenException('gateway_http_error');
        }

        // PAYTR's test gateway provides a failure reason when debug_on=1.
        // Convert it to a fixed, non-sensitive class. NEVER log/echo the body.
        $json = $response->json();
        if (! is_array($json)) {
            throw new PaytrTokenException('unexpected_response');
        }
        if (($json['status'] ?? null) === 'failed') {
            throw PaytrTokenException::fromProviderReason(
                is_string($json['reason'] ?? null) ? $json['reason'] : ''
            );
        }
        if (($json['status'] ?? null) !== 'success'
            || ! is_string($json['token'] ?? null)
            // PAYTR returns an opaque string. Its value is not guaranteed to
            // be alphanumeric; base64 tokens contain +, / or =. Enforce
            // length/control character safety and URI-encode on rendering.
            || strlen($json['token']) < 16 || strlen($json['token']) > 256
            || preg_match('/[\\x00-\\x20\\x7F]/', $json['token'])) {
            throw new PaytrTokenException('invalid_token_response');
        }

        return $json['token'];
    }
}
