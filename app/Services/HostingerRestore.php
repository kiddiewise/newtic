<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Imports data and uploads from a Hostinger export.
 *
 * Never deploys PHP, .env, credentials or application source from an uploaded archive.
 * Application code is deployed separately from the NEWTIC Git repository.
 */
final class HostingerRestore
{
    public function root(): string
    {
        return storage_path('app/private/hostinger-restores');
    }

    public function directory(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new RuntimeException('Geçersiz geri yükleme kaydı.');
        }

        return $this->root().DIRECTORY_SEPARATOR.$id;
    }

    public function read(string $id): array
    {
        $manifest = $this->directory($id).'/manifest.json';
        if (!is_file($manifest)) {
            throw new RuntimeException('Geri yükleme kaydı bulunamadı.');
        }

        $data = json_decode((string) file_get_contents($manifest), true);
        if (!is_array($data) || ($data['id'] ?? null) !== $id) {
            throw new RuntimeException('Geri yükleme kaydı bozulmuş.');
        }

        return $data;
    }

    public function write(array $data): void
    {
        $directory = $this->directory((string) $data['id']);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Yedekleme alanı oluşturulamadı.');
        }

        $tmp = $directory.'/manifest.tmp';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $directory.'/manifest.json')) {
            throw new RuntimeException('İşlem kaydı yazılamadı.');
        }
        @chmod($directory.'/manifest.json', 0600);
    }

    public function preview(string $id): array
    {
        $directory = $this->directory($id);
        $db = is_file($directory.'/database.sql.gz');
        $media = is_file($directory.'/files.tar.gz');

        if (!$db && !$media) {
            throw new RuntimeException('En az bir yedek dosyası yüklenmeli.');
        }
        $result = ['database' => $db, 'media' => $media, 'images' => 0, 'bytes' => 0];

        if ($db) {
            $handle = gzopen($directory.'/database.sql.gz', 'rb');
            if ($handle === false) {
                throw new RuntimeException('SQL yedeği açılamadı.');
            }
            try {
                $head = gzread($handle, 65536);
                if (!is_string($head) || !preg_match('/(?:CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE|MySQL dump|phpMyAdmin)/i', $head)) {
                    throw new RuntimeException('Geçerli bir MySQL yedeği tanınamadı.');
                }
            } finally {
                gzclose($handle);
            }
        }

        if ($media) {
            try {
                $archive = new PharData($directory.'/files.tar.gz');
                foreach (new RecursiveIteratorIterator($archive) as $file) {
                    if (!$file->isFile() || $file->isLink()) continue;
                    $relative = $this->relativeMediaPath($file->getPathname());
                    if ($relative === null) continue;
                    $result['images']++;
                    $result['bytes'] += $file->getSize();
                    if ($result['images'] > 100000 || $result['bytes'] > 12 * 1024 ** 3) {
                        throw new RuntimeException('Medya arşivi güvenli boyut sınırını aşıyor.');
                    }
                }
            } catch (Throwable $e) {
                if ($e instanceof RuntimeException) throw $e;
                throw new RuntimeException('Hostinger dosya arşivi okunamadı.', 0, $e);
            }
            if ($result['images'] === 0) {
                throw new RuntimeException('Arşivde NEWTIC medya dosyaları bulunamadı. Yanlış web sitesinin yedeği olabilir.');
            }
        }

        return $result;
    }

    /** Only restore uploads; do not extract arbitrary files or follow archive links. */
    private function relativeMediaPath(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);
        $name = preg_replace('~^phar://.*?\.(?:tar\.gz|tgz)/~i', '', $name);
        if (!is_string($name) || str_contains($name, "\0")) return null;

        if (!preg_match('~(?:^|/)(?:storage/app/public|public_html/storage)/(.+)$~i', $name, $match)) {
            return null;
        }

        $relative = $match[1];
        $segments = explode('/', $relative);
        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) return null;
        if (!preg_match('/\.(?:jpe?g|png|webp|gif|avif|bmp|pdf)$/i', $relative)) return null;
        if (strlen($relative) > 512) return null;

        return $relative;
    }

    public function restoreMedia(string $id): int
    {
        $path = $this->directory($id).'/files.tar.gz';
        if (!is_file($path)) return 0;

        $disk = Storage::disk('public');
        $archive = new PharData($path);
        $restored = 0;
        $bytes = 0;

        foreach (new RecursiveIteratorIterator($archive) as $entry) {
            if (!$entry->isFile() || $entry->isLink()) continue;
            $relative = $this->relativeMediaPath($entry->getPathname());
            if ($relative === null) continue;

            $size = $entry->getSize();
            $bytes += $size;
            if (++$restored > 100000 || $bytes > 12 * 1024 ** 3 || $size > 256 * 1024 ** 2) {
                throw new RuntimeException('Medya güvenlik sınırı aşıldı.');
            }

            $stream = fopen($entry->getPathname(), 'rb');
            if ($stream === false) throw new RuntimeException('Medya dosyası açılamadı.');
            try {
                if (!$disk->put($relative, $stream)) {
                    throw new RuntimeException('Medya dosyası geri yüklenemedi.');
                }
            } finally {
                fclose($stream);
            }
        }

        return $restored;
    }

    /**
     * Stream MySQL statements instead of holding the entire SQL dump in memory.
     * Reject cross-database and server-administration statements.
     */
    private function sqlStatements(string $path): \Generator
    {
        $handle = gzopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('SQL yedeği açılamadı.');

        $buffer = '';
        $quote = null;
        $escaped = false;
        $comment = false;
        $total = 0;

        try {
            while (!gzeof($handle)) {
                $line = gzgets($handle, 2 * 1024 * 1024);
                if ($line === false) break;
                $total += strlen($line);
                if ($total > 2 * 1024 ** 3) throw new RuntimeException('SQL yedeği çok büyük.');
                if (preg_match('/^\s*DELIMITER\s+/i', $line)) {
                    throw new RuntimeException('Özel SQL DELIMITER ifadeleri desteklenmiyor.');
                }
                $length = strlen($line);

                for ($i = 0; $i < $length; $i++) {
                    $char = $line[$i];
                    $next = $i + 1 < $length ? $line[$i + 1] : '';

                    if ($comment) {
                        if ($char === '*' && $next === '/') {
                            $comment = false;
                            $i++;
                        }
                        continue;
                    }
                    if ($quote !== null) {
                        $buffer .= $char;
                        if ($escaped) { $escaped = false; continue; }
                        if ($char === '\\') { $escaped = true; continue; }
                        if ($char === $quote) {
                            if ($next === $quote && $quote !== '`') {
                                $buffer .= $next;
                                $i++;
                            } else {
                                $quote = null;
                            }
                        }
                        continue;
                    }

                    if ($char === '/' && $next === '*') { $comment = true; $i++; continue; }
                    if ($char === '#') break;
                    if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($line[$i + 2]))) break;
                    if ($char === "'" || $char === '"' || $char === '`') {
                        $quote = $char;
                        $buffer .= $char;
                        continue;
                    }
                    if ($char === ';') {
                        $statement = trim($buffer);
                        $buffer = '';
                        if ($statement !== '') yield $statement;
                    } else {
                        $buffer .= $char;
                    }
                    if (strlen($buffer) > 32 * 1024 ** 2) {
                        throw new RuntimeException('SQL ifadesi boyut sınırını aşıyor.');
                    }
                }
                $buffer .= "\n";
            }
            if (trim($buffer) !== '') {
                throw new RuntimeException('SQL yedeği tamamlanmamış bir ifadeyle bitiyor.');
            }
        } finally {
            gzclose($handle);
        }
    }

    private function assertAllowedSql(string $sql): void
    {
        if (!preg_match('/^(?:CREATE\s+TABLE|DROP\s+TABLE|ALTER\s+TABLE|INSERT\s+INTO|REPLACE\s+INTO|UPDATE\s+|DELETE\s+FROM|SET\s+|LOCK\s+TABLES|UNLOCK\s+TABLES|START\s+TRANSACTION|COMMIT\b)/i', ltrim($sql))) {
            throw new RuntimeException('Yedekte desteklenmeyen SQL işlemi var.');
        }
        if (preg_match('/\b(?:OUTFILE|DUMPFILE|LOAD_FILE|INFILE|INTO\s+OUTFILE|SET\s+GLOBAL|SET\s+PERSIST|CREATE\s+USER|GRANT|REVOKE)\b/i', $sql)) {
            throw new RuntimeException('Yedek güvenlik kontrolünden geçemedi.');
        }
    }

    public function restoreDatabase(string $id): int
    {
        $path = $this->directory($id).'/database.sql.gz';
        if (!is_file($path)) return 0;

        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Hostinger SQL geri yükleme yalnızca MySQL/MariaDB için kullanılabilir.');
        }

        // Complete validation BEFORE any table is changed.
        $validated = 0;
        foreach ($this->sqlStatements($path) as $sql) {
            $this->assertAllowedSql($sql);
            $validated++;
        }
        if ($validated === 0) throw new RuntimeException('SQL yedeği boş.');

        $connection = DB::connection();
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        $done = 0;
        try {
            foreach ($this->sqlStatements($path) as $sql) {
                $connection->unprepared($sql);
                $done++;
            }
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Artisan::call('optimize:clear');
        return $done;
    }
}
