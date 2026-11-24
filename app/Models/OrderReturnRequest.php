<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderReturnRequest extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['items' => 'array', 'items_cents' => 'integer', 'shipping_cents' => 'integer',
        'refund_cents' => 'integer', 'refund_checked_at' => 'datetime', 'received_at' => 'datetime', 'restocked_at' => 'datetime'];

    public const ACTIVE = ['requested', 'approved', 'awaiting_return', 'received', 'refund_review'];
    public const LABELS = [
        'requested' => 'Talep alındı', 'approved' => 'Onaylandı',
        'awaiting_return' => 'İade kargosu bekleniyor', 'received' => 'Ürün teslim alındı',
        'refund_review' => 'Para iadesi kontrol ediliyor', 'completed' => 'Tamamlandı',
        'rejected' => 'Talep sonuçlandırıldı',
    ];
    public const REASONS = [
        'withdrawal' => 'Cayma hakkı / vazgeçtim', 'damaged' => 'Hasarlı veya ayıplı ürün',
        'wrong' => 'Yanlış veya eksik ürün', 'other' => 'Diğer',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function label(): string { return self::LABELS[$this->status] ?? 'Kontrol ediliyor'; }
}
