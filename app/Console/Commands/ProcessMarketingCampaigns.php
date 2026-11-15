<?php

namespace App\Console\Commands;

use App\Models\EmailSetting;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignDelivery;
use App\Models\MarketingContact;
use App\Models\MarketingSetting;
use App\Services\EmailTransport;
use App\Services\MarketingCampaignMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ProcessMarketingCampaigns extends Command
{
    protected $signature = 'marketing:process {--limit=10}';
    protected $description = 'Prepare and deliver approved marketing campaigns in safe, bounded batches.';

    public function handle(MarketingCampaignMailer $composer, EmailTransport $transport): int
    {
        $smtp = EmailSetting::current();
        $settings = MarketingSetting::current();
        if (!$smtp->active() || !$settings->ready()) return self::SUCCESS;
        $preview = EmailSetting::previewEnvironment();
        if (!$preview && !config('newtic.marketing_live_enabled', false)) {
            $this->warn('Live marketing release switch is OFF.');
            return self::SUCCESS;
        }
        if ($preview && !$smtp->safeRecipient('test@example.com')) return self::SUCCESS;

        // Snapshot the intended audience incrementally. New opt-ins after
        // campaign launch never enter an older campaign.
        foreach (MarketingCampaign::query()->where('status','queuing')->orderBy('id')->limit(3)->get() as $campaign) {
            DB::transaction(function () use ($campaign, $preview) {
                $locked = MarketingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->first();
                if (!$locked || $locked->status !== 'queuing') return;

                // Preview campaigns use no more than three opted-in records,
                // and all outbound mail is redirected to the test inbox.
                if ($preview && $locked->last_contact_id > 0) {
                    $locked->update(['status'=>'sending']);
                    return;
                }
                $contacts = MarketingContact::query()->eligible()
                    ->where('id','>', $locked->last_contact_id)
                    ->where('created_at','<=',$locked->launched_at)
                    ->where('consent_at','<=',$locked->launched_at)
                    ->where('iys_confirmed_at','<=',$locked->launched_at)
                    ->orderBy('id')
                    ->limit($preview ? 3 : 200)->get(['id']);
                foreach ($contacts as $contact) {
                    MarketingCampaignDelivery::query()->firstOrCreate([
                        'marketing_campaign_id'=>$locked->id,
                        'marketing_contact_id'=>$contact->id,
                    ], ['status'=>'pending','available_at'=>now()]);
                }
                if ($contacts->isNotEmpty()) {
                    $locked->last_contact_id = $contacts->last()->id;
                }
                if ($preview || $contacts->count() < 200) $locked->status = 'sending';
                $locked->save();
            }, 3);
        }

        // This conservative default works with Gmail test accounts;
        // providers can be granted separate audited thresholds at launch.
        $dailyCap = max(1, min(2000, (int) config('newtic.marketing_daily_limit', 100)));
        $sentToday = MarketingCampaignDelivery::query()
            ->where('status', 'sent')->where('sent_at', '>=', now()->startOfDay())->count();
        $limit = min(20, max(1, (int)$this->option('limit')), max(0, $dailyCap-$sentToday));
        if ($limit < 1) return self::SUCCESS;

        $ids = MarketingCampaignDelivery::query()
            ->whereHas('campaign', fn($q)=>$q->where('status','sending'))
            ->where(fn($q)=>$q->whereIn('status',['pending','retry'])
                ->orWhere(fn($q)=>$q->where('status','processing')
                    ->where('processing_at','<',now()->subMinutes(5))))
            ->where('available_at','<=',now())->orderBy('id')->limit($limit)->pluck('id');

        foreach ($ids as $id) {
            $delivery = DB::transaction(function () use ($id) {
                $row = MarketingCampaignDelivery::query()->whereKey($id)->lockForUpdate()->first();
                if (!$row || $row->campaign?->status !== 'sending'
                    || (!in_array($row->status,['pending','retry'],true)
                        && !($row->status==='processing' && $row->processing_at?->lt(now()->subMinutes(5))))) {
                    return null;
                }
                $row->update(['status'=>'processing','processing_at'=>now(),
                    'attempts'=>min(255,$row->attempts+1)]);
                return $row;
            }, 3);
            if (!$delivery) continue;

            try {
                $campaign = $delivery->campaign()->first();
                $contact = $delivery->contact()->first();
                if (!$campaign || $campaign->status !== 'sending'
                    || !$contact?->fresh()?->eligibleToSend()
                    || !$contact->consent_at?->lte($campaign->launched_at)
                    || !$contact->iys_confirmed_at?->lte($campaign->launched_at)) {
                    $delivery->update(['status'=>'skipped','last_error'=>'NotEligibleOrStopped']);
                    continue;
                }
                $transport->send($smtp, $contact->email, $composer->payload($campaign, $contact), $contact->name);
                $delivery->update(['status'=>'sent','sent_at'=>now(),'last_error'=>null]);
            } catch (\Throwable $e) {
                $retry = $delivery->attempts < 4;
                $delivery->update([
                    'status'=>$retry ? 'retry' : 'failed',
                    'available_at'=>now()->addMinutes(min(60, 2 ** $delivery->attempts)),
                    'last_error'=>class_basename($e), // no email secrets or provider error bodies
                ]);
            }
        }

        foreach (MarketingCampaign::query()->where('status','sending')->get() as $campaign) {
            if (!$campaign->deliveries()->whereIn('status',['pending','retry','processing'])->exists()) {
                $campaign->update(['status'=>'completed','finished_at'=>now()]);
            }
        }
        return self::SUCCESS;
    }
}
