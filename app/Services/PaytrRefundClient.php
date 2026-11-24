<?php

namespace App\Services;

use App\Models\OrderReturnRequest;
use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaytrRefundClient
{
    public function cents(mixed $value): int
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw new RuntimeException('invalid_amount');
        $value = str_replace(',', '.', (string) $value);
        if (!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) throw new RuntimeException('invalid_amount');
        return (int)$parts[1] * 100 + (int)str_pad($parts[2] ?? '', 2, '0');
    }

    private function post(PaytrSetting $settings, PaytrPaymentAttempt $attempt, string $endpoint, array $extra = []): array
    {
        $id = (string)$settings->merchant_id;
        $token = base64_encode(hash_hmac('sha256', $id.$attempt->merchant_oid.($extra['return_amount'] ?? '').$settings->merchant_salt, $settings->merchant_key, true));
        // No retry of a financial POST. TLS verification remains enabled.
        $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(25)
            ->withOptions(['allow_redirects' => false, 'verify' => true])
            ->post('https://www.paytr.com/odeme/'.$endpoint, ['merchant_id' => $id,
                'merchant_oid' => $attempt->merchant_oid, 'paytr_token' => $token] + $extra);
        $json = $response->json();
        if (!$response->successful() || !is_array($json)) throw new RuntimeException('provider_response');
        return $json;
    }

    public function context(OrderReturnRequest $case): array
    {
        $order = $case->order;
        $settings = PaytrSetting::query()->find(1);
        $attempts = $order->paytrAttempts()->where('status','success')->get();
        $attempt = $attempts->count() === 1 ? $attempts->first() : null;
        if (!$settings?->hasCredentials() || !$attempt || $attempt->is_test !== $order->is_test
            || $attempt->amount_cents !== $order->total_cents
            || $attempt->paid_total_cents < $attempt->amount_cents
            || $order->payment_method !== 'paytr'
            || (!$order->is_test && config('newtic.checkout_test_mode', true))) throw new RuntimeException('payment_context');
        return [$settings, $attempt];
    }

    public function status(PaytrSetting $settings, PaytrPaymentAttempt $attempt): array
    {
        $json = $this->post($settings, $attempt, 'durum-sorgu');
        if (($json['status'] ?? null) !== 'success'
            || !in_array($json['currency'] ?? null, ['TRY','TL'], true)
            || (string)($json['test_mode'] ?? '') !== ($attempt->is_test ? '1' : '0')
            || $this->cents($json['payment_amount'] ?? null) !== $attempt->amount_cents
            || $this->cents($json['payment_total'] ?? null) !== $attempt->paid_total_cents
            || !is_array($json['returns'] ?? null)) throw new RuntimeException('status_mismatch');
        $returns = [];
        foreach ($json['returns'] as $row) {
            if (!is_array($row)) throw new RuntimeException('return_shape');
            $returns[] = ['amount' => $this->cents($row['return_amount'] ?? null),
                'reference' => (string)($row['reference_no'] ?? ''),
                'completed' => filled($row['date_completed'] ?? null)];
        }
        return $returns;
    }

    public function send(OrderReturnRequest $case): string
    {
        [$settings, $attempt] = $this->context($case);
        if (!$attempt->is_test && (!$settings->live_refunds_enabled || !\App\Support\PaytrTestAccess::canonicalHost())) return 'none';
        $returns = $this->status($settings, $attempt);
        // A provider-side refund (including outside NEWTIC) reserves balance.
        // Unknown provider records fail closed rather than over-refund.
        foreach ($returns as $row) {
            if ($row['reference'] === $case->reference) return 'uncertain';
        }
        // Reconcile prior local refunds with provider history before another
        // financial POST. Stale/missing provider history must fail closed.
        foreach ($case->order->returnRequests()->where('id','!=',$case->id)->where('refund_status','verified')->get() as $prior) {
            $matches = array_values(array_filter($returns, fn ($r) => $r['reference'] === $prior->reference));
            if (count($matches) !== 1 || $matches[0]['amount'] !== $prior->refund_cents) return 'none';
        }
        if (array_sum(array_column($returns,'amount')) + $case->refund_cents > $attempt->amount_cents) return 'none';
        $amount = sprintf('%d.%02d', intdiv($case->refund_cents, 100), $case->refund_cents % 100);
        $json = $this->post($settings, $attempt, 'iade', ['return_amount' => $amount, 'reference_no' => $case->reference]);
        if (($json['status'] ?? null) !== 'success'
            || ($json['merchant_oid'] ?? null) !== $attempt->merchant_oid
            || ($json['reference_no'] ?? null) !== $case->reference
            || (string)($json['is_test'] ?? '') !== ($attempt->is_test ? '1' : '0')
            || $this->cents($json['return_amount'] ?? null) !== $case->refund_cents) return 'uncertain';
        return 'submitted';
    }

    public function reconcile(OrderReturnRequest $case): string
    {
        [$settings, $attempt] = $this->context($case);
        $rows = array_values(array_filter($this->status($settings, $attempt), fn ($r) => $r['reference'] === $case->reference));
        if (count($rows) !== 1 || $rows[0]['amount'] !== $case->refund_cents) return 'uncertain';
        return $rows[0]['completed'] ? 'verified' : 'submitted';
    }
}
