<?php

namespace App\Console\Commands;

use App\Models\EmailDelivery;
use App\Models\EmailSetting;
use App\Services\EmailComposer;
use App\Services\EmailTransport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ProcessEmailDeliveries extends Command
{
    protected $signature = 'email:process {--limit=25}';
    protected $description = 'Deliver a bounded batch of idempotent NEWTIC transaction emails.';

    public function handle(EmailComposer $composer, EmailTransport $transport): int
    {
        $settings = EmailSetting::current();
        if (!$settings->active()) {
            $this->line('Email sending is disabled or not verified.');
            return self::SUCCESS;
        }
        if (EmailSetting::previewEnvironment() && !$settings->safeRecipient('test@example.com')) {
            $this->warn('Staging recipient is not configured. No outbound emails.');
            return self::SUCCESS;
        }
        $limit = max(1, min(100, (int)$this->option('limit')));
        $ids = EmailDelivery::query()->where(function ($q) {
            $q->whereIn('status', ['pending','retry'])
                ->orWhere(fn($q) => $q->where('status', 'processing')
                    ->where('processing_at', '<=', now()->subMinutes(10)));
        })->where('available_at', '<=', now())
            ->orderBy('available_at')->orderBy('id')->limit($limit)->pluck('id');
        $sent = $skipped = $failed = 0;
        foreach ($ids as $id) {
            $item = DB::transaction(function () use ($id) {
                $item = EmailDelivery::query()->whereKey($id)->lockForUpdate()->first();
                if (!$item || (!in_array($item->status, ['pending','retry'], true)
                    && !($item->status === 'processing' && $item->processing_at?->lte(now()->subMinutes(10))))) {
                    return null;
                }
                $item->update(['status' => 'processing', 'processing_at' => now(),
                    'attempts' => min(255, $item->attempts + 1)]);
                return $item;
            });
            if (!$item) continue;
            try {
                if (!$settings->allows($item->event)) {
                    $item->update(['status'=>'skipped','last_error'=>'EventDisabled','secret_payload'=>null]);
                    $skipped++;
                    continue;
                }
                $payload = $composer->build($item->load(['order','user']));
                if (!$payload) {
                    $item->update(['status'=>'skipped','last_error'=>'EventNoLongerApplicable','secret_payload'=>null]);
                    $skipped++;
                    continue;
                }
                $transport->send($settings, $item->recipient_email, $payload, $item->recipient_name);
                $item->update(['status'=>'sent','sent_at'=>now(),'last_error'=>null,'secret_payload'=>null]);
                $sent++;
            } catch (\Throwable $error) {
                $again = $item->attempts < 5;
                $minutes = min(60, 2 ** $item->attempts);
                $item->update([
                    'status' => $again ? 'retry' : 'failed',
                    'available_at' => now()->addMinutes($minutes),
                    'last_error' => class_basename($error),
                    'secret_payload' => $again ? $item->secret_payload : null,
                ]);
                $failed++;
            }
        }
        $this->info("Emails: sent=$sent skipped=$skipped failed_or_retry=$failed");
        return self::SUCCESS;
    }
}
