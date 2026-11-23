<?php

namespace Tests\Unit;

use App\Services\NewticReleaseManager;
use App\Services\NewticSoftwareUpdater;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class NewticReleaseValidationTest extends TestCase
{
    private function approved(): array
    {
        return [
            'tag_name' => 'v1.2.0',
            'draft' => false,
            'prerelease' => false,
            'body' => "New admin features\nMore reliable checkout",
            'published_at' => '2026-11-23T16:00:00Z',
            'assets' => [[
                'name' => 'newtic-customer-install.zip',
                'digest' => 'sha256:'.str_repeat('a', 64),
                'browser_download_url' => 'https://github.com/kiddiewise/newtic/releases/download/v1.2.0/newtic-customer-install.zip',
            ]],
        ];
    }

    public function test_only_official_github_version_and_digest_are_accepted(): void
    {
        $release = (new NewticReleaseManager())->parse($this->approved());
        $this->assertSame('1.2.0', $release['version']);
        $this->assertSame(str_repeat('a', 64), $release['sha256']);
    }

    public function test_pre_releases_are_not_offered_to_merchants(): void
    {
        $payload = $this->approved();
        $payload['prerelease'] = true;
        $this->expectException(RuntimeException::class);
        (new NewticReleaseManager())->parse($payload);
    }

    public function test_forged_download_location_is_rejected(): void
    {
        $payload = $this->approved();
        $payload['assets'][0]['browser_download_url'] = 'https://example.com/newtic-customer-install.zip';
        $this->expectException(RuntimeException::class);
        (new NewticReleaseManager())->parse($payload);
    }

    public function test_an_asset_without_sha256_digest_is_not_an_update(): void
    {
        $payload = $this->approved();
        $payload['assets'][0]['digest'] = null;
        $this->expectException(RuntimeException::class);
        (new NewticReleaseManager())->parse($payload);
    }

    public function test_updater_never_writes_merchant_config_or_uploads(): void
    {
        $service = new NewticSoftwareUpdater(new NewticReleaseManager());
        $method = new ReflectionMethod($service, 'promotable');

        foreach (['.env', 'public/storage/customer.jpg', 'storage/app/private/customer.jpg',
            'bootstrap/cache/config.php','database/database.sqlite','.git/config'] as $file) {
            $this->assertFalse($method->invoke($service, $file), $file);
        }
        foreach (['app/Http/Controllers/HomeController.php', 'vendor/autoload.php',
            'public/build/manifest.json', 'routes/web.php'] as $file) {
            $this->assertTrue($method->invoke($service, $file), $file);
        }
    }
}
