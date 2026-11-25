<?php

namespace Tests\Feature;

use App\Models\SystemHealthMonitor;
use App\Models\User;
use App\Support\SystemHealthRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SystemHealthPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    public function test_health_dashboard_requires_an_administrator_and_disables_browser_caching(): void
    {
        $this->get('/admin/sistem-sagligi')->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.security'))->assertForbidden();
        $this->get('/admin/sistem-sagligi')->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('admin.system-health'))->assertOk()
            ->assertHeader('Cache-Control')
            ->assertSee('Sistem Sağlığı')
            ->assertSee('Son başarılı yedek')
            ->assertSee('Doğrulanmış yedekleme kaydı yok')
            ->assertSee('PAYTR bildirimleri')
            ->assertSee('Kargo senkronizasyonu')
            ->assertSee('Disk kullanımı')
            ->assertSee('Cron görevleri')
            ->assertSee('Güvenlik Kontrolü')
            ->assertSee('Uygulama sürümü');
    }

    public function test_menu_groups_preserve_existing_administration_links_and_have_a_logout(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Genel Bakış')
            ->assertSee('Ürün &amp; Sipariş Yönetimi', false)
            ->assertSee('Sistem Ayarları')
            ->assertDontSee('MAĞAZA GÖRÜNÜMÜ')
            ->assertDontSee('ENTEGRASYONLAR')
            ->assertDontSee('SİSTEM YÖNETİMİ')
            ->assertSee(route('admin.bank-accounts.index'))
            ->assertSee(route('admin.pwa.index'))
            ->assertSee(route('admin.analytics'))
            ->assertSee(route('admin.reports'))
            ->assertSee(route('admin.email-center.index'))
            ->assertSee(route('admin.marketing.index'))
            ->assertSee(route('admin.seo.edit'))
            ->assertSee(route('admin.redirects.index'))
            ->assertSee(route('admin.announcements.index'))
            ->assertSee(route('admin.appearance.edit'))
            ->assertSee(route('admin.system-health'))
            ->assertSee(route('admin.security'))
            ->assertSee(route('admin.index'))
            ->assertSee(route('admin.blog.posts.index'))
            ->assertSee(route('admin.menus.mega-menu.edit'))
            ->assertSee(route('admin.google-merchant.edit'))
            ->assertSee(route('admin.paytr-settings.edit'))
            ->assertSee(route('admin.legal.index'))
            ->assertSee(route('admin.logout'))
            ->assertSee('name="admin-menu-groups"', false);
        $this->get(route('admin.security'))->assertOk()->assertSee('Güvenlik Kontrolü');
    }

    public function test_scheduler_heartbeat_is_real_and_stale_health_is_not_shown_as_success(): void
    {
        $this->artisan('health:heartbeat')->assertExitCode(0);
        $this->assertDatabaseHas('system_health_monitors', [
            'key' => 'scheduler', 'status' => 'ok', 'detail' => 'schedule_run',
        ]);

        $this->actingAs($this->admin())->get(route('admin.system-health'))
            ->assertOk()->assertSee('Laravel zamanlayıcısı çalışıyor');

        SystemHealthMonitor::query()->where('key', 'scheduler')
            ->update(['last_checked_at' => now()->subMinutes(20)]);

        $this->get(route('admin.system-health'))->assertOk()
            ->assertSee('Zamanlayıcı beklenen aralıkta çalışmıyor');
    }

    public function test_external_backup_is_unknown_until_verified_completion_report(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.system-health'))->assertSee('Doğrulanmış yedekleme kaydı yok');

        $this->artisan('health:backup-report', [
            '--result' => 'success', '--provider' => 'remote',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('system_health_monitors', [
            'key' => 'backup', 'status' => 'ok', 'detail' => 'backup_remote',
        ]);
        $this->get(route('admin.system-health'))->assertOk()
            ->assertSee('Yedekleme tamamlandı olarak bildirildi');

        $lastSuccess = SystemHealthMonitor::query()->where('key', 'backup')
            ->firstOrFail()->last_success_at;
        $this->artisan('health:backup-report', [
            '--result' => 'failed', '--provider' => 'remote',
        ])->assertExitCode(0);

        $record = SystemHealthMonitor::query()->where('key', 'backup')->firstOrFail();
        $this->assertSame('error', $record->status);
        $this->assertEquals($lastSuccess, $record->last_success_at);
        $this->get(route('admin.system-health'))
            ->assertOk()->assertSee('Son yedekleme başarısız bildirildi');
    }

    public function test_backup_report_rejects_untrusted_provider_strings(): void
    {
        $this->artisan('health:backup-report', [
            '--result' => 'success', '--provider' => '../../other',
        ])->assertExitCode(2);
        $this->assertDatabaseCount('system_health_monitors', 0);
    }

    public function test_recording_unknown_monitor_or_sensitive_detail_is_safe(): void
    {
        SystemHealthRecorder::record('arbitrary_monitor', true, 'secret');
        $this->assertDatabaseCount('system_health_monitors', 0);
        SystemHealthRecorder::record('paytr_callback', true, 'user@example.com private');
        $this->assertDatabaseHas('system_health_monitors', [
            'key' => 'paytr_callback', 'status' => 'ok', 'detail' => 'unspecified',
        ]);
    }
}
