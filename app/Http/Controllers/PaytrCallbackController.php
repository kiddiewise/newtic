<?php

namespace App\Http\Controllers;

use App\Models\PaytrPaymentAttempt;
use App\Models\PaytrSetting;
use App\Models\Product;
use App\Support\PaytrSignature;
use App\Support\SystemHealthRecorder;
use App\Support\PaytrTestAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PaytrCallbackController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $settings = PaytrSetting::query()->find(1);
        if (!PaytrTestAccess::acceptsCallbacks($settings)) {
            Log::warning('paytr_callback_rejected', ['reason' => 'settings_gate']);
            return response('PAYTR integration is not active', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $payload = $request->only([
            'merchant_oid', 'status', 'total_amount', 'hash', 'test_mode',
            'payment_type', 'currency', 'payment_amount', 'failed_reason_code', 'failed_reason_msg',
        ]);

        if (! PaytrSignature::notificationIsValid(
            $payload, $settings->merchant_key, $settings->merchant_salt
        )) {
            // No request body, signature, merchant OID or customer data in logs.
            Log::warning('paytr_callback_rejected', ['reason' => 'invalid_signature']);
            return response('Invalid PAYTR notification', 403);
        }

        // These unsigned auxiliary fields must not override signed or
        // persisted data. Payment type and currency are mandatory per API.
        if (($payload['payment_type'] ?? null) !== 'card'
            || ! in_array(($payload['currency'] ?? null), ['TL', 'TRY'], true)) {
            Log::warning('paytr_callback_rejected', ['reason' => 'invalid_payment_context']);
            return response('Unexpected payment context', 422);
        }

        $result = DB::transaction(function () use ($payload): int {
            $attempt = PaytrPaymentAttempt::query()
                ->where('merchant_oid', $payload['merchant_oid'])
                ->lockForUpdate()->first();

            if (! $attempt) {
                Log::warning('paytr_callback_rejected', ['reason' => 'unknown_attempt']);
                return 404;
            }

            // A missing test_mode field is documented as optional. An explicit
            // marker must agree with the immutable attempt, not current settings.
            if (array_key_exists('test_mode', $payload)
                && (string) $payload['test_mode'] !== ($attempt->is_test ? '1' : '0')) {
                return 422;
            }

            // Retry deliveries must NEVER create a second order transition,
            // customer notification, or inventory adjustment.
            if (in_array($attempt->status, ['success', 'failed'], true)) {
                if ($attempt->status !== $payload['status']) {
                    $attempt->update(['reconciliation_result'=>'callback_conflict','reconciliation_checked_at'=>now()]);
                    return 409;
                }
                return 200;
            }
            if ($payload['status'] === 'success' && $attempt->reconciliation_result === 'external_refund') {
                return 409;
            }

            $order = $attempt->order()->lockForUpdate()->firstOrFail();

            if ($attempt->is_test !== $order->is_test
                || $order->payment_method !== 'paytr'
                || $order->currency !== 'TRY'
                || $attempt->currency !== 'TRY'
                || $attempt->amount_cents !== $order->total_cents
                || $order->status !== 'pending_payment') {
                Log::warning('paytr_callback_rejected', ['reason' => 'order_state_mismatch']);
                return 409;
            }

            $total = (int) $payload['total_amount'];
            // The step-2 contract includes payment_amount (the original step-1
            // amount). It is not in the callback HMAC, so check when provided
            // against our persisted signed attempt rather than trusting it.
            if (isset($payload['payment_amount'])
                && (! preg_match('/^[0-9]{1,16}$/D', (string) $payload['payment_amount'])
                    || (int) $payload['payment_amount'] !== $attempt->amount_cents)) {
                Log::warning('paytr_callback_rejected', ['reason' => 'original_amount_mismatch']);
                return 422;
            }
            // PayTR may report total_amount ABOVE payment_amount when
            // installment differences apply. Never accept underpayments.
            // Failed callbacks may report zero, but must still be HMAC-signed.
            if ($payload['status'] === 'success'
                && ($total < $attempt->amount_cents || $total <= 0)) {
                Log::warning('paytr_callback_rejected', ['reason' => 'paid_amount_below_order']);
                return 422;
            }

            $now = now();
            if ($payload['status'] === 'success') {
                $attempt->update([
                    'status' => 'success', 'paid_total_cents' => $total,
                    'processed_at' => $now,
                    'reconciliation_result' => 'callback_success',
                ]);
                $order->update(['status' => 'preparing']);

                return 200;
            }

            $attempt->update([
                'status' => 'failed', 'processed_at' => $now,
                'reconciliation_result' => 'callback_failed',
                'failed_reason_code' => mb_substr((string) ($payload['failed_reason_code'] ?? ''), 0, 12),
                'failed_reason_message' => mb_substr((string) ($payload['failed_reason_msg'] ?? ''), 0, 300),
            ]);

            // A failed transaction closes this single-attempt order
            // and releases the stock reserved during checkout exactly once.
            // Row and attempt locks make repeated PAYTR callbacks idempotent.
            $quantities = $order->items()->whereNotNull('product_id')
                ->selectRaw('product_id, SUM(quantity) as qty')
                ->groupBy('product_id')->pluck('qty', 'product_id');
            $products = Product::query()->whereIn('id', $quantities->keys())
                ->where('tracks_stock', true)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($products as $product) {
                $product->increment('stock_quantity', (int) ($quantities[$product->id] ?? 0));
            }
            $order->update(['status' => 'cancelled']);

            return 200;
        }, 3);

        if ($result === 200) {
            SystemHealthRecorder::record('paytr_callback', true, 'signed_notification');
            Log::info('paytr_callback_accepted', ['result' => 'ok']);
        }

        if ($result !== 200) {
            // Only a signature-verified processing failure is recorded; invalid
            // unauthenticated internet requests cannot flood health storage.
            SystemHealthRecorder::record('paytr_callback', false, 'signed_processing_failed');
        }

        return $result === 200
            ? response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8')
            : response('PAYTR notification could not be processed', $result)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
