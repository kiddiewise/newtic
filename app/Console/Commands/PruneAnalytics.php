<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneAnalytics extends Command
{
    protected $signature = 'analytics:prune';
    protected $description = 'Delete old consented analytics events and inactive visitor sessions';

    public function handle(): int
    {
        // Avoid ever-growing datasets on shared hosting. No raw IPs are stored.
        $cutoff = now()->subDays(90);
        do {
            $ids = DB::table('analytics_events')->where('occurred_at', '<', $cutoff)
                ->orderBy('id')->limit(500)->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('analytics_events')->whereIn('id', $ids)->delete();
            }
        } while ($ids->isNotEmpty());

        DB::table('analytics_sessions')->where('last_seen_at', '<', now()->subDays(2))->delete();
        $this->info('Privacy analytics retention cleanup completed.');

        return self::SUCCESS;
    }
}
