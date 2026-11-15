<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketingCampaignDelivery extends Model
{
    protected $fillable = [
        'marketing_campaign_id','marketing_contact_id','status',
        'attempts','available_at','processing_at','sent_at','last_error',
    ];
    protected $casts = [
        'available_at'=>'datetime','processing_at'=>'datetime',
        'sent_at'=>'datetime','attempts'=>'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(MarketingContact::class, 'marketing_contact_id');
    }
}
