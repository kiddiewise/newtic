<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentNotification extends Model
{
    protected $fillable = [
        'order_id', 'bank_account_id', 'bank_name_snapshot', 'iban_snapshot',
        'status', 'customer_note', 'admin_note', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Ödeme bildirimi kontrol bekliyor',
            'verified' => 'Ödeme doğrulandı',
            'rejected' => 'Ödeme bildirimi reddedildi',
            default => 'Bilinmiyor',
        };
    }
}
