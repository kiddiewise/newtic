<?php

namespace App\Console\Commands;

use App\Models\PaytrPaymentAttempt;
use App\Services\PaytrPendingPaymentService;
use Illuminate\Console\Command;

final class ReconcilePendingPaytrPayments extends Command
{
    protected $signature = 'paytr:reconcile-pending {--limit=10}';
    protected $description = 'Reconcile pending card payments and expire provably unopened orders.';

    public function handle(PaytrPendingPaymentService $service): int
    {
        $limit = max(1,min(20,(int)$this->option('limit')));
        $ids = PaytrPaymentAttempt::query()->where('status','pending')
            ->whereHas('order',fn($q)=>$q->where('status','pending_payment')->where('payment_method','paytr'))
            ->where(fn($q)=>$q->whereNull('reconciliation_checked_at')->orWhere('reconciliation_checked_at','<=',now()->subMinutes(5)))
            ->where(fn($q)=>$q->where(fn($x)=>$x->where('issuance_tracked',true)->whereNull('token_request_started_at')->where('created_at','<=',now()->subMinutes(30)))
                ->orWhere(fn($x)=>$x->where('created_at','<=',now()->subMinutes(5))->where(fn($y)=>$y->where('issuance_tracked',false)->orWhereNotNull('token_request_started_at'))))
            ->orderByRaw('CASE WHEN reconciliation_checked_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('reconciliation_checked_at')->orderBy('id')->limit($limit)->pluck('id');
        $results = [];
        foreach ($ids as $id) {
            $result = $service->reconcile($id);
            $results[$result] = ($results[$result] ?? 0) + 1;
        }
        $this->info('Pending PayTR checks: '.json_encode($results));
        return isset($results['provider_error']) || isset($results['context_mismatch']) || isset($results['external_refund']) ? self::FAILURE : self::SUCCESS;
    }
}
