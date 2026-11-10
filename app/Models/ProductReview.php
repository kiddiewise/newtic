<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'order_id', 'rating', 'body',
        'status', 'is_test', 'moderated_at', 'moderated_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_test' => 'boolean',
        'moderated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function getPublicNameAttribute(): string
    {
        $name = trim((string) ($this->user?->name ?? ''));
        $first = explode(' ', $name)[0] ?? '';

        return $first === '' ? 'Doğrulanmış alıcı' : $first.' '.(mb_strlen($name) > mb_strlen($first) ? '•' : '');
    }
}
