<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Applies only trusted, versioned, complete NEWTIC release packages.
 * Never replaces .env, customer uploads, private storage or .git.
 *
 * This runner is intentionally CLI-only. Running it from an HTTP request
 * risks half-applied releases on shared-hosting request time limits.
 */
final class NewticSoftwareUpdater
{
    private const MAX_ARCHIVE_BYTES = 800 * 1024 * 1024;
    private const MAX_EXTRACTED_BYTES = 1800 * 1024 * 1024;
    private const MAX_FILES = 60000;

    public function __construct(private readonly NewticReleaseManager $releases) {}

    public function run(): bool
    {
        if (!app()->runningInConsole()) throw new RuntimeException('Updates must run from the scheduler.');

        $root = dirname($this->releases->path('state.json'));
        File::ensureDirectoryExists($root, 0700);
        $lock = fopen($root.'/update.lock', 'c+');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            return false;
        }

        $maintenance = false;
        try {
            $job = $this->releases->state();
            if (($job['status'] ?? '') !== 'queued') {
                if (($job['status'] ?? '') === 'running'
                    && strtotime((string) ($job['started_at'] ?? 'now')) < time() - 3600) {
                    // Interrupted deployments require review: we cannot promise
                    // an automatic database rollback after DDL migrations.
                    $job['status'] = 'failed';
                    $job['message'] = 'Güncelleme beklenmedik biçimde kesildi. Sunucu ve veritabanı kontrol edilmeli.';
                    $this->releases->writeState($job);
                    try { Artisan::call('up'); } catch (Throwable) {}
                }
                return false;
            }

            if (version_compare((string) ($job['version'] ?? ''), $this->releases->currentVersion(), '<=')) {
                $job['status'] = 'done';
                $job['message'] = 'Bu sürüm zaten kurulu.';
                $this->releases->writeState($job);
                return true;
            }

            $job['status'] = 'running';
            $job['started_at'] = now()->toIso8601String();
            $job['message'] = 'Sürüm indiriliyor ve doğrulanıyor.';
            $this->releases->writeState($job);
            $stage = $root.'/stage-'.preg_replace('/[^a-f0-9]/', '', (string) ($job['id'] ?? ''));
            File::ensureDirectoryExists($stage, 0700);

            try {
                $package = $this->download($job, $stage);
                $this->extractAndVerify($package, $stage.'/package');
                $this->preflight($stage.'/package');

                $job['message'] = 'Güncelleme uygulanıyor.';
                $this->releases->writeState($job);
                Artisan::call('down', ['--retry' => 60]);
                $maintenance = true;

                $this->promote($stage.'/package', $stage.'/rollback');

                // A release may contain migrations, but never seeds or a fresh install.
                Artisan::call('optimize:clear');
                $exit = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
                if ($exit !== 0) throw new RuntimeException('Database migration failed.');
                $exit = Artisan::call('view:cache');
                if ($exit !== 0) throw new RuntimeException('Blade cache compilation failed.');

                $this->releases->writeInstalledVersion((string) $job['version']);
                $job['status'] = 'done';
                $job['finished_at'] = now()->toIso8601String();
                $job['message'] = 'NEWTIC '.$job['version'].' başarıyla kuruldu.';
                $this->releases->writeState($job);
                Artisan::call('up');
                $maintenance = false;

                // Keeping a full previous release on shared hosting is expensive.
                // The hosting provider's off-site backups remain the recovery source.
                File::deleteDirectory($stage);
                return true;
            } catch (Throwable $e) {
                // If package files were partly promoted, revert exactly the files
                // touched. DDL migrations are NOT automatically reversible.
                try { $this->rollback($stage.'/rollback'); } catch (Throwable $rollbackError) {
                    Log::critical('newtic_update_code_rollback_failed', ['type' => $rollbackError::class]);
                }
                if ($maintenance) {
                    try { Artisan::call('up'); $maintenance = false; } catch (Throwable) {}
                }
                $job['status'] = 'failed';
                $job['finished_at'] = now()->toIso8601String();
                $job['message'] = 'Güncelleme tamamlanamadı. Mağaza ve son yedek kontrol edilmeli.';
                if (app()->environment('testing') && getenv('CI') === 'true') {
                    $job['ci_diagnostic'] = mb_substr($e::class.': '.$e->getMessage(), 0, 250);
                }
                $this->releases->writeState($job);
                Log::error('newtic_software_update_failed', ['exception_type' => $e::class]);
                File::deleteDirectory($stage);
                return false;
            }
        } finally {
            if ($maintenance) {
                try { Artisan::call('up'); } catch (Throwable) {}
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function download(array $job, string $stage): string
    {
        $url = (string) ($job['url'] ?? '');
        $tag = (string) ($job['tag'] ?? '');
        $expected = (string) ($job['sha256'] ?? '');

        if (!preg_match('/^v\d+\.\d+\.\d+$/D', $tag)
            || $url !== 'https://github.com/kiddiewise/newtic/releases/download/'.$tag.'/newtic-customer-install.zip'
            || !preg_match('/^[0-9a-f]{64}$/D', $expected)) {
            throw new RuntimeException('Untrusted release.');
        }

        $path = $stage.'/release.zip';
        $response = Http::withHeaders(['User-Agent' => 'NEWTIC-Merchant-Updater'])
            ->connectTimeout(10)->timeout(240)
            ->withOptions(['sink' => $path])
            ->get($url);
        if (!$response->successful() || !is_file($path)
            || filesize($path) < 1024 || filesize($path) > self::MAX_ARCHIVE_BYTES
            || !hash_equals($expected, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('Release SHA256 verification failed.');
        }

        return $path;
    }

    private function extractAndVerify(string $zipPath, string $destination): void
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP zip extension is required.');

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('Cannot open release ZIP.');
        File::ensureDirectoryExists($destination, 0700);
        $count = $zip->numFiles;
        if ($count < 100 || $count > self::MAX_FILES) throw new RuntimeException('Invalid release size.');

        $prefix = null;
        $entries = [];
        $total = 0;
        try {
            for ($i = 0; $i < $count; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat)) throw new RuntimeException('Invalid ZIP item.');
                $name = (string) $stat['name'];
                if ($name === 'artisan') $prefix = '';
                if (str_ends_with($name, '/artisan')) {
                    if ($prefix !== null && $prefix !== '' && $prefix !== substr($name, 0, -7)) {
                        throw new RuntimeException('Ambiguous archive root.');
                    }
                    if ($prefix !== '') $prefix = substr($name, 0, -7);
                }
                if (str_contains($name, "\0") || str_contains($name, '\\')
                    || str_starts_with($name, '/') || preg_match('~(^|/)\.\.?(/|$)~', $name)
                    || strlen($name) > 512) {
                    throw new RuntimeException('Unsafe ZIP path.');
                }
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX) {
                    $type = ($attributes >> 16) & 0170000;
                    if ($type !== 0 && $type !== 0100000 && $type !== 0040000) {
                        throw new RuntimeException('ZIP links are forbidden.');
                    }
                }
                $total += (int) ($stat['size'] ?? 0);
                if ($total > self::MAX_EXTRACTED_BYTES) throw new RuntimeException('Expanded release too large.');
                $entries[] = $name;
            }
            if ($prefix === null) throw new RuntimeException('Release package has no artisan.');

            foreach ($entries as $index => $name) {
                if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                    throw new RuntimeException('Unexpected ZIP root.');
                }
                $relative = substr($name, strlen($prefix));
                if ($relative === '' || str_ends_with($relative, '/')) continue;
                if (!preg_match('~^[a-zA-Z0-9_.@/+ -]+$~D', $relative)) {
                    throw new RuntimeException('Unexpected ZIP filename.');
                }

                $file = $destination.'/'.$relative;
                File::ensureDirectoryExists(dirname($file), 0700);
                $input = $zip->getStream($name);
                if ($input === false) throw new RuntimeException('Cannot read ZIP entry.');
                $output = fopen($file, 'wb');
                if ($output === false) { fclose($input); throw new RuntimeException('Cannot stage release.'); }
                try {
                    if (stream_copy_to_stream($input, $output) === false) {
                        throw new RuntimeException('Cannot stage ZIP entry.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                @chmod($file, 0600);
            }
        } finally {
            $zip->close();
        }

        $this->verifyChecksums($destination);
    }

    private function verifyChecksums(string $directory): void
    {
        $checksums = $directory.'/NEWTIC-RELEASE-SHA256SUMS.txt';
        $revision = $directory.'/.newtic-release-revision';
        $assetRevision = $directory.'/public/build/newtic-revision.txt';

        if (!is_file($checksums) || !is_file($revision) || !is_file($assetRevision)
            || !preg_match('/^[a-f0-9]{40}$/D', trim((string) file_get_contents($revision)))
            || trim((string) file_get_contents($revision)) !== trim((string) file_get_contents($assetRevision))) {
            throw new RuntimeException('Release metadata or source revision mismatch.');
        }

        $lines = file($checksums, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || count($lines) < 100) throw new RuntimeException('Missing release checksum manifest.');
        $seen = [];
        foreach ($lines as $line) {
            if (!preg_match('~^([a-f0-9]{64})  \./([^\x00-\x1f]+)$~D', $line, $m)) {
                throw new RuntimeException('Invalid checksum record.');
            }
            $relative = $m[2];
            if (isset($seen[$relative]) || str_contains($relative, '\\') || str_starts_with($relative, '/')
                || preg_match('~(^|/)\.\.?(/|$)~', $relative)) {
                throw new RuntimeException('Unsafe checksum record.');
            }
            $file = $directory.'/'.$relative;
            if (!is_file($file) || is_link($file) || !hash_equals($m[1], (string) hash_file('sha256', $file))) {
                throw new RuntimeException('Release file integrity failed.');
            }
            $seen[$relative] = true;
        }
        foreach (['artisan','composer.json','composer.lock','vendor/autoload.php','public/build/manifest.json',
            'scripts/install-production.sh'] as $mandatory) {
            if (!isset($seen[$mandatory])) throw new RuntimeException('Release missing required files.');
        }
    }

    private function preflight(string $source): void
    {
        if (!is_writable(base_path()) || !is_file(base_path('.env')) || !is_dir($source.'/vendor')
            || !extension_loaded('pdo_mysql') || !class_exists(ZipArchive::class)) {
            throw new RuntimeException('Server does not support one-click updates.');
        }
        // The staged package already occupies disk. Updating needs room for
        // unchanged customer data AND temporary copies of replaced code.
        $packageBytes = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($items as $item) {
            if ($item->isFile()) $packageBytes += $item->getSize();
        }
        $available = @disk_free_space(base_path());
        if ($available !== false && $available < ($packageBytes * 1.5) + 200 * 1024 * 1024) {
            throw new RuntimeException('Insufficient free disk space for safe staging and rollback.');
        }

        $public = json_decode((string) file_get_contents($source.'/public/build/manifest.json'), true);
        if (!is_array($public)) throw new RuntimeException('Compiled frontend manifest is invalid.');
        foreach (['resources/css/app.css','resources/js/app.js'] as $entry) {
            $name = $public[$entry]['file'] ?? null;
            if (!is_string($name) || !is_file($source.'/public/build/'.$name)) {
                throw new RuntimeException('A compiled frontend asset is missing.');
            }
        }
    }

    private function promotable(string $relative): bool
    {
        if (preg_match('~(^|/)(?:\.env|\.git|storage|node_modules)(?:/|$)~', $relative)
            || str_starts_with($relative, 'bootstrap/cache/')
            || str_starts_with($relative, 'public/storage/')
            || str_starts_with($relative, 'database/database.sqlite')) return false;

        foreach (['app/', 'bootstrap/', 'config/', 'database/migrations/', 'database/seeders/',
            'resources/', 'routes/', 'vendor/', 'public/build/', 'public/assets/', 'scripts/'] as $prefix) {
            if (str_starts_with($relative, $prefix)) return true;
        }
        return in_array($relative, [
            'artisan', 'composer.json', 'composer.lock',
            '.newtic-release-revision', 'NEWTIC-RELEASE-SHA256SUMS.txt', 'VERSION',
        ], true);
    }

    private function customerWebroot(): ?string
    {
        $media = (string) config('filesystems.disks.public.root', '');
        if ($media === '' || !str_ends_with(str_replace('\\', '/', $media), '/public_html/storage')) return null;
        $webroot = realpath(dirname($media));
        if (!$webroot || !is_file($webroot.'/index.php') || is_link($webroot.'/index.php')) return null;
        $entry = (string) file_get_contents($webroot.'/index.php');
        if (!str_contains($entry, base_path('public/index.php'))) {
            throw new RuntimeException('This public_html does not belong to this NEWTIC installation.');
        }

        return $webroot;
    }

    private function promote(string $source, string $rollback): void
    {
        $webroot = $this->customerWebroot();
        $journal = [];
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->isLink()) continue;
                $relative = substr($item->getPathname(), strlen($source) + 1);
                if (!$this->promotable($relative)) continue;

                $targets = [base_path($relative)];
                if ($webroot !== null && (str_starts_with($relative, 'public/build/')
                    || str_starts_with($relative, 'public/assets/'))) {
                    $targets[] = $webroot.'/'.substr($relative, strlen('public/'));
                }
                foreach ($targets as $target) {
                    if (is_link($target)) throw new RuntimeException('Refusing to overwrite symbolic link.');
                    $parent = dirname($target);
                    File::ensureDirectoryExists($parent);
                    $real = realpath($parent);
                    if (!$real) throw new RuntimeException('Cannot resolve destination directory.');
                    $safeRoot = str_starts_with($target, base_path().'/') ? realpath(base_path()) : $webroot;
                    if (!$safeRoot || !str_starts_with($real.'/', rtrim($safeRoot, '/').'/')) {
                        throw new RuntimeException('Release destination escapes installation.');
                    }

                    $relativeBackup = hash('sha256', $target);
                    $prior = $rollback.'/previous/'.$relativeBackup;
                    File::ensureDirectoryExists(dirname($prior), 0700);
                    $existed = is_file($target);
                    if (file_exists($target) && !$existed) throw new RuntimeException('Invalid destination file.');
                    if ($existed && !copy($target, $prior)) throw new RuntimeException('Cannot save previous file.');

                    $journal[] = ['target' => $target, 'prior' => $prior, 'existed' => $existed];
                    File::ensureDirectoryExists($rollback, 0700);
                    if (file_put_contents($rollback.'/journal.json', json_encode($journal, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                        throw new RuntimeException('Update journal cannot be saved.');
                    }
                    $temp = $target.'.newtic-pending-'.bin2hex(random_bytes(4));
                    if (!copy($item->getPathname(), $temp) || !rename($temp, $target)) {
                        @unlink($temp);
                        throw new RuntimeException('Cannot publish release file.');
                    }
                    @chmod($target, 0644);
                }
            }
        } catch (Throwable $e) {
            $this->rollback($rollback);
            throw $e;
        }
    }

    private function rollback(string $rollback): void
    {
        $path = $rollback.'/journal.json';
        if (!is_file($path)) return;
        $journal = json_decode((string) file_get_contents($path), true);
        if (!is_array($journal)) return;

        foreach (array_reverse($journal) as $entry) {
            $target = $entry['target'];
            if (($entry['existed'] ?? false) && is_file($entry['prior'])) {
                copy($entry['prior'], $target);
            } elseif (!($entry['existed'] ?? false) && is_file($target)) {
                unlink($target);
            }
        }
    }
}
