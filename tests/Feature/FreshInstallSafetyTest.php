<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FreshInstallSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_read_only_gate_refuses_non_mysql_runtime(): void
    {
        $this->assertNotSame('mysql', config('database.default'));
        $this->artisan('newtic:check-fresh-db')
            ->expectsOutput('Fresh customer installations require an isolated MySQL connection.')
            ->assertExitCode(1);
    }

    public function test_first_install_checks_are_before_key_generation_and_migrations(): void
    {
        $script = (string) file_get_contents(base_path('scripts/install-production.sh'));
        $probe = strpos($script, 'php artisan newtic:check-fresh-db --no-interaction');
        $this->assertNotFalse($probe);
        $this->assertLessThan(strpos($script, 'php artisan key:generate --force'), $probe);
        $this->assertLessThan(strpos($script, 'php artisan migrate --force'), $probe);
        $this->assertStringContainsString('NEWTIC_PAYTR_WEBHOOKS_ENABLED:false', $script);
        $this->assertStringContainsString('NEWTIC_MARKETING_SEND_ENABLED:false', $script);
    }

    public function test_public_asset_verifier_checks_sensitive_application_paths(): void
    {
        $script = (string) file_get_contents(base_path('scripts/verify-public-assets.sh'));
        foreach (['.env.example', 'composer.json', 'vendor/autoload.php', 'storage/logs/laravel.log'] as $path) {
            $this->assertStringContainsString($path, $script);
        }
        $this->assertStringContainsString('PASS: sensitive application files are not publicly served.', $script);
    }
}
