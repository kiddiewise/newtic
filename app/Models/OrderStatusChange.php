<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderStatusChange extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'order_id', 'user_id', 'from_status', 'to_status', 'source', 'reason', 'created_at',
    ];
    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
