<?php

namespace App\Console\Commands;

use App\Services\HostingerRestore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RestorePendingBackup extends Command
{
    protected $signature = 'backup:restore-pending';

    protected $description = 'Safely process one admin-approved Hostinger restore using the existing scheduler';

    public function handle(HostingerRestore $service): int
    {
        if (!is_dir($service->root())) return self::SUCCESS;

        $handle = fopen($service->root().'/.restore.lock', 'c+');
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) fclose($handle);
            return self::SUCCESS;
        }

        try {
            $manifests = glob($service->root().'/*/manifest.json') ?: [];
            usort($manifests, fn ($a, $b) => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));

            foreach ($manifests as $path) {
                $id = basename(dirname($path));
                try {
                    $job = $service->read($id);
                } catch (Throwable) {
                    continue;
                }
                if (($job['status'] ?? '') !== 'queued') {
                    // No permanent ZIP buildup on shared hosting.
                    $status = (string) ($job['status'] ?? '');
                    $expires = $status === 'done' ? 86400 : 3 * 86400;
                    $date = $job['finished_at'] ?? $job['created_at'] ?? null;
                    if (in_array($status, ['done', 'failed', 'ready'], true)
                        && $date && strtotime((string) $date) < time() - $expires) {
                        \Illuminate\Support\Facades\File::deleteDirectory($service->directory($id));
                    }
                    continue;
                }

                $down = false;
                try {
                    // Files are checked again, AFTER queueing, before database changes.
                    $service->preview($id);
                    if (($job['database'] ?? false) && DB::getDriverName() !== 'mysql') {
                        throw new \RuntimeException('Veritabanı MySQL/MariaDB olmalıdır.');
                    }

                    $job['status'] = 'running';
                    $job['started_at'] = now()->toIso8601String();
                    $job['message'] = 'Geri yükleme devam ediyor.';
                    $service->write($job);

                    Artisan::call('down', ['--retry' => 60]);
                    $down = true;

                    try {
                        $queries = $service->restoreDatabase($id);
                        $images = $service->restoreMedia($id);
                    } finally {
                        if ($down) Artisan::call('up');
                        $down = false;
                    }

                    $job['status'] = 'done';
                    $job['finished_at'] = now()->toIso8601String();
                    $job['message'] = "Geri yükleme tamamlandı. SQL: {$queries} ifade, medya: {$images} dosya.";
                    $service->write($job);
                    $this->info($job['message']);
                } catch (Throwable $e) {
                    if ($down) {
                        try { Artisan::call('up'); } catch (Throwable) { /* allow manual recovery */ }
                    }
                    $job['status'] = 'failed';
                    $job['finished_at'] = now()->toIso8601String();
                    $job['message'] = 'Geri yükleme tamamlanamadı. Veriler kısmen değişmiş olabilir; Hostinger kurtarma yedeğini kullanın.';
                    try { $service->write($job); } catch (Throwable) {}
                    Log::error('hostinger_restore_failed', ['type' => $e::class]);
                    $this->error($job['message']);
                }

                // Limit to one restore per scheduler run.
                break;
            }

            return self::SUCCESS;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
