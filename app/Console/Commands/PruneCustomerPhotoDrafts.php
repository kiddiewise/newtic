<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Prune orphaned temporary cart uploads after a grace period. A checkout
 * copies the photos to customer-photos/orders, which is NEVER touched here.
 *
 * Each draft upload is placed in a unique UUID directory. Only direct
 * files in that exact private namespace are eligible. A directory is removed
 * only when ALL of its files are older than the retention period.
 */
final class PruneCustomerPhotoDrafts extends Command
{
    protected $signature = 'customer-photos:prune-drafts
        {--days=7 : Minimum age of all draft files, in days}
        {--limit=200 : Maximum number of directories removed per run}
        {--dry-run : Count eligible draft directories without deleting files}';

    protected $description = 'Delete orphaned private cart-photo drafts; never delete order photos';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 2 || $days > 365
            || $limit === false || $limit < 1 || $limit > 1000) {
            $this->error('Invalid limits: days must be 2–365 and limit must be 1–1000.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local'); // Never use the public storage disk.
        $root = 'customer-photos/drafts';
        if (!$disk->exists($root)) {
            $this->info('Draft photo cleanup: no draft directory.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->timestamp;
        $deleted = 0;
        $eligible = 0;
        $checked = 0;

        foreach ($disk->directories($root) as $directory) {
            // Reject anything outside a direct UUID draft directory, including
            // nested paths, symlinks or unexpected legacy directory layouts.
            if (!preg_match('~^customer-photos/drafts/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$~iD', $directory)) {
                continue;
            }
            $checked++;
            if ($disk->directories($directory) !== []) {
                continue;
            }
            $files = $disk->files($directory);
            if ($files === []) {
                continue;
            }

            $old = true;
            foreach ($files as $file) {
                if (!preg_match('~^customer-photos/drafts/[a-f0-9-]{36}/[^/]+\.(?:jpe?g|png|webp|avif)$~iD', $file)
                    || !$disk->exists($file)
                    || $disk->lastModified($file) > $cutoff) {
                    $old = false;
                    break;
                }
            }

            if (!$old) {
                continue;
            }
            $eligible++;
            if ($this->option('dry-run')) {
                if ($eligible >= $limit) {
                    break;
                }
                continue;
            }

            if ($disk->deleteDirectory($directory)) {
                $deleted++;
            }
            if ($deleted >= $limit) {
                break;
            }
        }

        $this->info(sprintf(
            'Draft photo cleanup: checked=%d eligible=%d deleted=%d dry_run=%s',
            $checked, $eligible, $deleted, $this->option('dry-run') ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }
}
