<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number','checkout_token','user_id','customer_name','customer_email',
        'phone','address_label','city','district','street_address','invoice_type',
        'company_title','tax_office','tax_number','order_note','payment_method','status',
        'subtotal_cents','shipping_cents','total_cents','currency','is_test','placed_at',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'shipping_cents' => 'integer',
        'total_cents' => 'integer',
        'placed_at' => 'datetime',
        'is_test' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_payment' => 'Ödeme bekleniyor',
            'preparing' => 'Hazırlanıyor',
            'shipped' => 'Kargolandı',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal edildi',
            default => 'Bilinmiyor',
        };
    }
}
