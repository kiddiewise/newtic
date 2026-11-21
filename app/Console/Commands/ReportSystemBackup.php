<?php

namespace App\Console\Commands;

use App\Support\SystemHealthRecorder;
use Illuminate\Console\Command;

/**
 * Execute from a trusted BACKUP JOB AFTER checking the real backup command
 * exit status and verifying the resulting archive. This does not make backups.
 * No path, password, file contents or archive name is stored.
 */
final class ReportSystemBackup extends Command
{
    protected $signature = 'health:backup-report {--result=success : success or failed} {--provider=external : external, local, remote or managed}';
    protected $description = 'Record the result of a real external backup for the admin health panel.';

    public function handle(): int
    {
        $result = (string) $this->option('result');
        $provider = (string) $this->option('provider');

        if (!in_array($result, ['success', 'failed'], true)
            || !in_array($provider, ['external', 'local', 'remote', 'managed'], true)) {
            $this->error('Allowed: --result=success|failed --provider=external|local|remote|managed');
            return self::INVALID;
        }

        SystemHealthRecorder::record('backup', $result === 'success', 'backup_'.$provider);
        $this->info('Backup outcome reported without storing archive names or secrets.');
        return self::SUCCESS;
    }
}
