<?php

namespace App\Console\Commands;

use App\Support\SystemHealthRecorder;
use Illuminate\Console\Command;

final class SystemHealthHeartbeat extends Command
{
    protected $signature = 'health:heartbeat';
    protected $description = 'Record a portable Laravel scheduler heartbeat (no hosting API required).';

    public function handle(): int
    {
        SystemHealthRecorder::record('scheduler', true, 'schedule_run');
        $this->info('NEWTIC scheduler heartbeat recorded.');
        return self::SUCCESS;
    }
}
