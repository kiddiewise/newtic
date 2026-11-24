<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

final class PaytrPaymentAttempt extends Model
{
    protected $fillable = [
        'order_id', 'merchant_oid', 'amount_cents', 'currency',
        'status', 'is_test', 'paid_total_cents',
        'failed_reason_code', 'failed_reason_message', 'processed_at',
        'issuance_tracked', 'token_request_started_at', 'reconciliation_checked_at', 'reconciliation_result',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_total_cents' => 'integer',
        'is_test' => 'boolean',
        'processed_at' => 'datetime',
        'issuance_tracked' => 'boolean',
        'token_request_started_at' => 'datetime',
        'reconciliation_checked_at' => 'datetime',
    ];

    public static function createForOrder(Order $order): self
    {
        if ($order->payment_method !== 'paytr' || $order->status !== 'pending_payment'
            || $order->total_cents <= 0
            || $order->currency !== 'TRY') {
            throw new RuntimeException('Only pending PAYTR orders can create attempts.');
        }

        // Independent alphanumeric attempt ID: customer-facing PK-... order
        // numbers include dashes, which PAYTR merchant_oid does not allow.
        return self::query()->create([
            'order_id' => $order->id,
            'merchant_oid' => 'NT'.Str::upper(Str::random(30)),
            'amount_cents' => $order->total_cents,
            'currency' => 'TRY',
            'status' => 'pending',
            'issuance_tracked' => true,
            'is_test' => $order->is_test,
        ]);
    }

    public function canRequestToken(): bool
    {
        return $this->status === 'pending' && $this->reconciliation_result !== 'external_refund' && $this->created_at
            && $this->created_at->gt(now()->subMinutes(30));
    }

    public function reconciliationLabel(): string
    {
        return match ($this->reconciliation_result) {
            'paid' => 'PayTR durum sorgusu ile ödeme doğrulandı',
            'no_success_yet' => 'Başarılı ödeme henüz doğrulanmadı; stok korunuyor',
            'provider_error' => 'PayTR sorgusu başarısız; yeniden kontrol edilecek',
            'context_mismatch' => 'Ödeme bilgileri uyuşmuyor; inceleme gerekli',
            'external_refund' => 'PayTR üzerinde iade bulundu; inceleme gerekli',
            'unopened_expired' => 'Ödeme ekranı açılmadan süre doldu',
            'callback_conflict' => 'Çelişkili PayTR bildirimi; inceleme gerekli',
            'callback_success' => 'İmzalı ödeme bildirimi ile doğrulandı',
            'callback_failed' => 'İmzalı başarısız ödeme bildirimi alındı',
            default => 'Henüz otomatik sorgu yapılmadı',
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
