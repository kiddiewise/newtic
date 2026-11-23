<?php

namespace Tests\Feature;

use App\Services\HostingerRestore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

final class HostingerRestoreTest extends TestCase
{
    public function test_hostinger_archive_restores_only_allowed_media(): void
    {
        Storage::fake('public');
        $service = app(HostingerRestore::class);
        $id = bin2hex(random_bytes(16));
        $directory = $service->directory($id);
        File::ensureDirectoryExists($directory);

        try {
            $tar = new PharData($directory.'/files.tar');
            $tar->addFromString('domains/shop.test/public_html/storage/branding/logo.webp', 'example-image');
            $tar->addFromString('domains/shop.test/public_html/storage/branding/script.php', '<?php echo 1;');
            $tar->addFromString('domains/shop.test/public_html/.env', 'APP_KEY=secret');
            $tar->compress(Phar::GZ);
            unset($tar);
            unlink($directory.'/files.tar');

            $preview = $service->preview($id);
            $this->assertSame(1, $preview['images']);
            $this->assertSame(1, $service->restoreMedia($id));
            Storage::disk('public')->assertExists('branding/logo.webp');
            Storage::disk('public')->assertMissing('branding/script.php');
            Storage::disk('public')->assertMissing('.env');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_streaming_sql_parser_respects_semicolons_in_values(): void
    {
        $service = app(HostingerRestore::class);
        $path = tempnam(sys_get_temp_dir(), 'newtic_sql_');
        file_put_contents($path, gzencode("-- MySQL dump\nCREATE TABLE `sample` (`id` int);\nINSERT INTO `sample` VALUES (1, 'value; with semicolon');\n"));
        try {
            $method = new ReflectionMethod(HostingerRestore::class, 'sqlStatements');
            $statements = iterator_to_array($method->invoke($service, $path), false);

            $this->assertCount(2, $statements);
            $this->assertStringContainsString('CREATE TABLE', $statements[0]);
            $this->assertStringContainsString('value; with semicolon', $statements[1]);
        } finally {
            unlink($path);
        }
    }

    public function test_sql_database_admin_commands_are_rejected(): void
    {
        $service = app(HostingerRestore::class);
        $method = new ReflectionMethod(HostingerRestore::class, 'assertAllowedSql');
        $this->expectException(RuntimeException::class);
        $method->invoke($service, 'DROP DATABASE production');
    }
}
