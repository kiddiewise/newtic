<?php

namespace Tests\Feature;

use App\Services\NewticReleaseManager;
use App\Services\NewticSoftwareUpdater;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/**
 * A simulated production upgrade which really runs the CLI updater.
 * Run ONLY inside a disposable GitHub Actions checkout (not a live shop).
 */
final class NewticUpdaterEndToEndTest extends TestCase
{
    public function test_upgrader_moves_an_isolated_store_from_1_0_to_1_2_without_losing_merchant_data(): void
    {
        if (getenv('CI') !== 'true') {
            $this->markTestSkipped('Destructive integration smoke runs only in disposable CI checkout.');
        }

        $releases = app(NewticReleaseManager::class);
        $updater = app(NewticSoftwareUpdater::class);
        $root = dirname($releases->path('state.json'));
        File::ensureDirectoryExists($root, 0700);
        $id = bin2hex(random_bytes(16));
        $zipPath = $root.'/fixture-'.$id.'.zip';
        $testMarker = base_path('app/Support/NewticReleaseSmokeMarker.php');
        $mediaMarker = storage_path('app/public/newtic-user-upload-keep.txt');
        $versionFile = base_path('.newtic-installed-version');
        $previousVersion = is_file($versionFile) ? file_get_contents($versionFile) : null;
        $oldVersion = (string) file_get_contents(base_path('VERSION'));
        $envHash = hash_file('sha256', base_path('.env'));

        File::ensureDirectoryExists(dirname($mediaMarker));
        file_put_contents($mediaMarker, 'merchant file survives');

        try {
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
            DB::statement('CREATE TABLE newtic_upgrade_probe (id INTEGER PRIMARY KEY, content TEXT NOT NULL)');
            DB::table('newtic_upgrade_probe')->insert(['id' => 1, 'content' => 'merchant order must survive']);

            // Make this disposable shop an older version without changing its .env.
            file_put_contents($versionFile, "1.0.0\n");

            $package = [
                'artisan' => file_get_contents(base_path('artisan')),
                'composer.json' => file_get_contents(base_path('composer.json')),
                'composer.lock' => file_get_contents(base_path('composer.lock')),
                'vendor/autoload.php' => file_get_contents(base_path('vendor/autoload.php')),
                'scripts/install-production.sh' => file_get_contents(base_path('scripts/install-production.sh')),
                'VERSION' => "1.2.0\n",
                'public/build/manifest.json' => json_encode([
                    'resources/css/app.css' => ['file' => 'assets/app-smoke.css'],
                    'resources/js/app.js' => ['file' => 'assets/app-smoke.js'],
                ], JSON_THROW_ON_ERROR),
                'public/build/assets/app-smoke.css' => '/* signed simulated new release */',
                'public/build/assets/app-smoke.js' => '// signed simulated new release',
                'public/build/newtic-revision.txt' => str_repeat('a', 40),
                '.newtic-release-revision' => str_repeat('a', 40),
                'app/Support/NewticReleaseSmokeMarker.php' => "<?php\n// NEWTIC 1.2.0 isolated update marker\n",
            ];
            for ($i = 0; $i < 105; $i++) {
                $package['public/assets/newtic-smoke/fixture-'.$i.'.txt'] = 'static fixture '.$i;
            }

            $hashes = '';
            foreach ($package as $path => $contents) {
                $hashes .= hash('sha256', $contents).'  ./'.$path."\n";
            }
            $package['NEWTIC-RELEASE-SHA256SUMS.txt'] = $hashes;

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
            foreach ($package as $path => $contents) {
                $this->assertTrue($zip->addFromString($path, $contents));
            }
            $this->assertTrue($zip->close());
            $digest = hash_file('sha256', $zipPath);
            $url = 'https://github.com/kiddiewise/newtic/releases/download/v1.2.0/newtic-customer-install.zip';
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $digest);

            // The HTTP request is intercepted. No actual release is published
            // and no production storefront is contacted.
            Http::fake(function ($request) use ($zipPath, $root, $id, $url) {
                $this->assertSame($url, $request->url());
                $this->assertTrue(copy($zipPath, $root.'/stage-'.$id.'/release.zip'));
                return Http::response(file_get_contents($zipPath), 200, ['Content-Type' => 'application/zip']);
            });

            $releases->writeState([
                'id' => $id,
                'version' => '1.2.0',
                'tag' => 'v1.2.0',
                'url' => $url,
                'sha256' => $digest,
                'status' => 'queued',
            ]);

            $success = $updater->run();
            $this->assertTrue($success, 'CLI updater did not apply a valid signed fixture: '.json_encode($releases->state()));
            $this->assertSame('done', $releases->state()['status']);
            $this->assertSame('1.2.0', $releases->currentVersion());
            $this->assertFileExists($testMarker);
            $this->assertSame('merchant order must survive', DB::table('newtic_upgrade_probe')->value('content'));
            $this->assertSame('merchant file survives', file_get_contents($mediaMarker));
            $this->assertSame($envHash, hash_file('sha256', base_path('.env')));
            $this->assertFalse(app()->isDownForMaintenance());
        } finally {
            @unlink($testMarker);
            @unlink($mediaMarker);
            @unlink($zipPath);
            file_put_contents(base_path('VERSION'), $oldVersion);
            if ($previousVersion !== null) file_put_contents($versionFile, $previousVersion);
            else @unlink($versionFile);
            File::deleteDirectory($root.'/stage-'.$id);
            try { Artisan::call('up'); } catch (\Throwable) {}
        }
    }
}
