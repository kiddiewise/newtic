<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NewticReleaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NewticUpdateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_release_shows_dashboard_notification_and_is_not_installed_without_explicit_admin_confirmation(): void
    {
        $releases = app(NewticReleaseManager::class);
        $latestPath = $releases->path('latest.json');
        $statePath = $releases->path('state.json');
        $oldLatest = is_file($latestPath) ? file_get_contents($latestPath) : null;
        $oldState = is_file($statePath) ? file_get_contents($statePath) : null;
        $api = [
            'tag_name' => 'v1.2.0',
            'draft' => false,
            'prerelease' => false,
            'body' => 'New admin features and safer updates.',
            'published_at' => '2026-11-23T16:00:00Z',
            'assets' => [[
                'name' => 'newtic-customer-install.zip',
                'digest' => 'sha256:'.str_repeat('a', 64),
                'browser_download_url' => 'https://github.com/kiddiewise/newtic/releases/download/v1.2.0/newtic-customer-install.zip',
            ]],
        ];

        try {
            Http::fake([
                'https://api.github.com/repos/kiddiewise/newtic/releases/latest' => Http::response($api, 200),
            ]);
            $admin = User::factory()->create(['is_admin' => true]);
            $customer = User::factory()->create(['is_admin' => false]);

            $this->actingAs($customer)
                ->get(route('admin.software-updates.index'))
                ->assertForbidden();

            $this->actingAs($admin)
                ->post(route('admin.software-updates.check'))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertTrue($releases->hasUpdate());
            $this->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee('NEWTIC 1.2.0 güncellemesi hazır');

            $this->get(route('admin.software-updates.index'))
                ->assertOk()
                ->assertSee('1.2.0')
                ->assertSee('Güncellemeyi yükle');

            $this->post(route('admin.software-updates.install'), [
                'confirmation' => 'GUNCELLE',
            ])->assertSessionHasErrors('backup_confirmed');

            $this->assertNotSame('queued', $releases->state()['status'] ?? 'idle');

            $this->post(route('admin.software-updates.install'), [
                'backup_confirmed' => '1',
                'confirmation' => 'GUNCELLE',
            ])->assertRedirect(route('admin.software-updates.index'))
                ->assertSessionHasNoErrors();

            $this->assertSame('queued', $releases->state()['status']);
            $this->assertSame('1.2.0', $releases->state()['version']);
            $this->assertSame($admin->id, $releases->state()['requested_by']);
            Http::assertSentCount(2);
        } finally {
            if ($oldLatest !== null) file_put_contents($latestPath, $oldLatest);
            else @unlink($latestPath);
            if ($oldState !== null) file_put_contents($statePath, $oldState);
            else @unlink($statePath);
        }
    }
}
