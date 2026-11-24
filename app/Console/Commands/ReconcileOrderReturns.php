<?php

namespace App\Console\Commands;

use App\Models\OrderReturnRequest;
use App\Models\PaytrSetting;
use App\Services\OrderReturnService;
use App\Services\PaytrRefundClient;
use Illuminate\Console\Command;

final class ReconcileOrderReturns extends Command
{
    protected $signature = 'returns:reconcile {--limit=10}';
    protected $description = 'Read-only PayTR refund reconciliation; never repeat a financial request.';

    public function handle(PaytrRefundClient $client, OrderReturnService $service): int
    {
        if (!PaytrSetting::query()->find(1)?->hasCredentials()) return self::SUCCESS;
        $limit = max(1,min(20,(int)$this->option('limit')));
        $cases = OrderReturnRequest::query()->where('status','refund_review')
            ->whereHas('order',fn($q)=>$q->where('payment_method','paytr'))
            ->where(fn($q)=>$q->whereIn('refund_status',['submitted','uncertain'])
                ->orWhere(fn($r)=>$r->where('refund_status','processing')->where('updated_at','<=',now()->subMinutes(10))))
            ->where(fn($q)=>$q->whereNull('refund_checked_at')->orWhere('refund_checked_at','<=',now()->subMinutes(10)))
            ->orderByRaw('CASE WHEN refund_checked_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('refund_checked_at')->orderBy('id')->limit($limit)->get();
        $verified = $errors = 0;
        foreach ($cases as $case) {
            // Fairness: record every attempt, even if the provider is unavailable.
            OrderReturnRequest::query()->whereKey($case->id)->whereIn('refund_status',['processing','submitted','uncertain'])
                ->toBase()->update(['refund_checked_at'=>now()]);
            try {
                $state = $client->reconcile($case);
                if ($state === 'verified') {
                    $service->refundResult($case,'verified',null);
                    $verified++;
                } elseif ($state === 'submitted') {
                    $service->refundResult($case,'submitted',null);
                }
                // Absence is not failure; never release or repeat an uncertain refund.
            } catch (\Throwable $e) {
                $errors++;
            }
        }
        $this->info('Refund status checks: '.$cases->count().'; verified: '.$verified.'; errors: '.$errors);
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
