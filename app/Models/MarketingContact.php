<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class MarketingContact extends Model
{
    protected $fillable = [
        'user_id', 'email', 'name', 'status', 'consent_source',
        'consent_proof', 'consent_at', 'iys_reference', 'iys_confirmed_at',
        'unsubscribed_at', 'unsubscribe_token',
    ];

    protected $hidden = ['unsubscribe_token'];

    protected $casts = [
        'consent_at' => 'datetime',
        'iys_confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contact): void {
            if (!$contact->unsubscribe_token) $contact->unsubscribe_token = Str::random(64);
        });
    }

    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->whereNotNull('consent_at')
            ->whereNotNull('consent_proof')
            ->whereNotNull('iys_confirmed_at')
            ->whereNotNull('iys_reference')
            ->whereNull('unsubscribed_at');
    }

    public function eligibleToSend(): bool
    {
        return $this->status === 'approved'
            && filled($this->consent_proof)
            && filled($this->iys_reference)
            && $this->consent_at !== null
            && $this->iys_confirmed_at !== null
            && $this->unsubscribed_at === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
