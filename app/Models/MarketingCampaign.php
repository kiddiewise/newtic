<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketingCampaign extends Model
{
    protected $fillable = [
        'created_by','title','body','banner_path','button_text','button_url',
        'status','launched_at','finished_at',
    ];

    protected $casts = ['launched_at'=>'datetime','finished_at'=>'datetime'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(MarketingCampaignDelivery::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
