<?php

namespace App\Http\Controllers;

use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Models\Product;
use App\Support\PaytrSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class PaytrCallbackController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Stage two is installed but DISABLED until the order/checkout workflow
        // and verified PAYTR test transaction have passed acceptance testing.
        if (! config('newtic.paytr_webhooks_enabled', false)) {
            return response('PAYTR callback is not active', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $settings = PaytrSetting::query()->find(1);
        if (! $settings?->is_enabled || ! $settings->test_mode
            || ! $settings->hasCredentials()) {
            return response('PAYTR integration is not active', 503);
        }

        $payload = $request->only([
            'merchant_oid', 'status', 'total_amount', 'hash', 'test_mode',
            'payment_type', 'currency', 'failed_reason_code', 'failed_reason_msg',
        ]);

        if (! PaytrSignature::notificationIsValid(
            $payload, $settings->merchant_key, $settings->merchant_salt
        )) {
            return response('Invalid PAYTR notification', 403);
        }

        // These are unsigned auxiliary fields. They cannot override data
        // already verified against the persisted signed payment attempt.
        if (($payload['test_mode'] ?? null) !== '1'
            || ($payload['payment_type'] ?? null) !== 'card'
            || ! in_array(($payload['currency'] ?? null), ['TL', 'TRY'], true)) {
            return response('Unexpected payment context', 422);
        }

        $result = DB::transaction(function () use ($payload): int {
            $attempt = PaytrPaymentAttempt::query()
                ->where('merchant_oid', $payload['merchant_oid'])
                ->lockForUpdate()->first();

            if (! $attempt) {
                return 404;
            }

            // Retry deliveries must NEVER create a second order transition,
            // customer notification, or inventory adjustment.
            if (in_array($attempt->status, ['success', 'failed'], true)) {
                return 200;
            }

            $order = $attempt->order()->lockForUpdate()->firstOrFail();

            if (! $attempt->is_test || ! $order->is_test
                || $order->payment_method !== 'paytr'
                || $order->currency !== 'TRY'
                || $attempt->currency !== 'TRY'
                || $attempt->amount_cents !== $order->total_cents
                || $order->status !== 'pending_payment') {
                return 409;
            }

            $total = (int) $payload['total_amount'];
            // Stage two is single-installment-only. PayTR documents that
            // installment premiums can exceed the originally signed amount;
            // we intentionally reject such a mismatch for this configuration.
            if ($total <= 0 || $total !== $attempt->amount_cents) {
                return 422;
            }

            $now = now();
            if ($payload['status'] === 'success') {
                $attempt->update([
                    'status' => 'success', 'paid_total_cents' => $total,
                    'processed_at' => $now,
                ]);
                $order->update(['status' => 'preparing']);
                return 200;
            }

            $attempt->update([
                'status' => 'failed', 'processed_at' => $now,
                'failed_reason_code' => mb_substr((string) ($payload['failed_reason_code'] ?? ''), 0, 12),
                'failed_reason_message' => mb_substr((string) ($payload['failed_reason_msg'] ?? ''), 0, 300),
            ]);

            // A failed payment attempt does not imply that a different
            // attempt can no longer succeed; order stays pending_payment.
            // Inventory reservation / retry expiry will be finalized with
            // customer-facing PAYTR checkout in the next stage.
            return 200;
        }, 3);

        return $result === 200
            ? response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8')
            : response('PAYTR notification could not be processed', $result)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
