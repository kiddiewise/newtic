<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number','checkout_token','user_id','customer_name','customer_email',
        'phone','address_label','city','district','street_address','invoice_type',
        'company_title','tax_office','tax_number','order_note','payment_method','status',
        'subtotal_cents','shipping_cents','total_cents','currency','is_test','placed_at',
        'carrier_name','tracking_number','tracking_url',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'shipping_cents' => 'integer',
        'total_cents' => 'integer',
        'placed_at' => 'datetime',
        'is_test' => 'boolean',
    ];

    public function shippingShipment(): HasOne
    {
        return $this->hasOne(ShippingShipment::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderByDesc('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paytrAttempts(): HasMany
    {
        return $this->hasMany(PaytrPaymentAttempt::class)->latest('id');
    }

    public function paymentNotifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class)->latest('id');
    }

    public function latestPaymentNotification(): ?PaymentNotification
    {
        return $this->paymentNotifications()->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function labelForStatus(string $status): string
    {
        return match ($status) {
            'pending_payment' => 'Ödeme bekleniyor',
            'preparing' => 'Hazırlanıyor',
            'shipped' => 'Kargolandı',
            'out_for_delivery' => 'Dağıtıma çıktı',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal edildi',
            default => 'Bilinmiyor',
        };
    }

    public function statusLabel(): string
    {
        return self::labelForStatus($this->status);
    }
}
