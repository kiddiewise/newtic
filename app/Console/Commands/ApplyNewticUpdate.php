<?php

namespace App\Console\Commands;

use App\Services\NewticSoftwareUpdater;
use Illuminate\Console\Command;

final class ApplyNewticUpdate extends Command
{
    protected $signature = 'newtic:apply-update';
    protected $description = 'Apply one previously authorized and verified NEWTIC release';

    public function handle(NewticSoftwareUpdater $updater): int
    {
        $updated = $updater->run();
        if ($updated) $this->info('NEWTIC update applied.');
        return self::SUCCESS;
    }
}
