<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmailDelivery extends Model
{
    protected $fillable = [
        'dedupe_key','event','order_id','user_id','reference_id',
        'recipient_email','recipient_name','secret_payload','status','attempts',
        'available_at','processing_at','sent_at','last_error',
    ];
    protected $casts = [
        'available_at' => 'datetime', 'processing_at' => 'datetime',
        'sent_at' => 'datetime', 'attempts' => 'integer',
        'secret_payload' => 'encrypted',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
