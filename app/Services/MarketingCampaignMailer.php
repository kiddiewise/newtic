<?php

namespace App\Services;

use App\Models\EmailSetting;
use App\Models\MarketingCampaign;
use App\Models\MarketingContact;
use App\Models\MarketingSetting;
use App\Support\StoreIdentity;

final class MarketingCampaignMailer
{
    public function payload(MarketingCampaign $campaign, ?MarketingContact $contact = null): array
    {
        $settings = MarketingSetting::current();
        $unsubscribe = $contact ? route('marketing.unsubscribe.show', $contact->unsubscribe_token) : null;
        return [
            'template'=>'campaign',
            'subject'=>$campaign->title,
            'brand'=>StoreIdentity::name(),
            'title'=>$campaign->title,
            'content'=>$campaign->body,
            'banner'=>$campaign->banner_path ? asset($campaign->banner_path) : null,
            'button_text'=>$campaign->button_text,
            'button_url'=>$campaign->button_url,
            'unsubscribe_url'=>$unsubscribe,
            'business_name'=>$settings->business_name ?: StoreIdentity::name(),
            'business_identifier'=>$settings->business_identifier,
            'business_address'=>$settings->business_address,
            'contact_email'=>$settings->contact_email ?: EmailSetting::current()->reply_to,
        ];
    }
}
