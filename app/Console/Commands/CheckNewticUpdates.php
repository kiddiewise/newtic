<?php

namespace App\Console\Commands;

use App\Services\NewticReleaseManager;
use Illuminate\Console\Command;

final class CheckNewticUpdates extends Command
{
    protected $signature = 'newtic:check-updates';
    protected $description = 'Check official NEWTIC releases without modifying a merchant store';

    public function handle(NewticReleaseManager $releases): int
    {
        $release = $releases->refresh();
        if ($release === null) {
            $this->warn('No verified NEWTIC release metadata is currently available.');
            return self::SUCCESS;
        }
        $this->info('Current: '.$releases->currentVersion().' | Latest: '.$release['version']);
        return self::SUCCESS;
    }
}
