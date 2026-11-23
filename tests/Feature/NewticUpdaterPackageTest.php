<?php

namespace Tests\Feature;

use App\Services\NewticReleaseManager;
use App\Services\NewticSoftwareUpdater;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

final class NewticUpdaterPackageTest extends TestCase
{
    private function directory(): string
    {
        $folder = storage_path('framework/testing/newtic-update-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($folder);
        return $folder;
    }

    private function fakeArchive(string $zipPath, bool $unsafe = false): void
    {
        $revision = str_repeat('a', 40);
        $files = [
            'artisan' => '<?php echo "artisan";',
            'composer.json' => '{}',
            'composer.lock' => '{}',
            'vendor/autoload.php' => '<?php return true;',
            'public/build/manifest.json' => '{}',
            'public/build/newtic-revision.txt' => $revision,
            '.newtic-release-revision' => $revision,
            'scripts/install-production.sh' => '#!/bin/bash',
        ];
        for ($i = 0; $i < 105; $i++) {
            $files['public/assets/fixture/file-'.$i.'.txt'] = 'fixture '.$i;
        }

        $checksums = '';
        foreach ($files as $name => $data) {
            $checksums .= hash('sha256', $data).'  ./'.$name."\n";
        }
        $files['NEWTIC-RELEASE-SHA256SUMS.txt'] = $checksums;
        if ($unsafe) $files['../.env'] = 'APP_KEY=malicious';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach ($files as $name => $data) {
            $this->assertTrue($zip->addFromString($name, $data));
        }
        $this->assertTrue($zip->close());
    }

    public function test_complete_zip_can_be_checked_without_executing_its_php(): void
    {
        $folder = $this->directory();
        try {
            $zip = $folder.'/release.zip';
            $this->fakeArchive($zip);
            $updater = new NewticSoftwareUpdater(new NewticReleaseManager());
            $method = new ReflectionMethod($updater, 'extractAndVerify');
            $method->invoke($updater, $zip, $folder.'/staged');
            $this->assertFileExists($folder.'/staged/vendor/autoload.php');
            $this->assertFileExists($folder.'/staged/public/build/manifest.json');
            $this->assertFileDoesNotExist($folder.'/staged/.env');
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_zip_path_traversal_is_rejected_before_extraction(): void
    {
        $folder = $this->directory();
        try {
            $zip = $folder.'/release.zip';
            $this->fakeArchive($zip, true);
            $updater = new NewticSoftwareUpdater(new NewticReleaseManager());
            $method = new ReflectionMethod($updater, 'extractAndVerify');

            try {
                $method->invoke($updater, $zip, $folder.'/staged');
                $this->fail('Malicious ZIP path was accepted.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Unsafe ZIP path', $e->getMessage());
            }
            $this->assertFileDoesNotExist($folder.'/.env');
        } finally {
            File::deleteDirectory($folder);
        }
    }
}
