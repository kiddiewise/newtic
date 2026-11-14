<?php

namespace Tests\Feature;

use App\Models\ShopAnnouncement;
use App\Models\ShopAppearance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontBrandingAndAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_storefront_has_consistent_svg_icon_set_in_cart_and_product_cards(): void
    {
        $this->get('/sepet')->assertOk()
            ->assertSee('ui-icon-sprite')
            ->assertSee('ui-shopping-bag')
            ->assertSee('ui-shopping-cart')
            ->assertSee('ui-house')
            ->assertSee('ui-user-round')
            ->assertSee('ui-search')
            ->assertSee('ui-grid-2x2')
            ->assertSee('href="#ui-shopping-bag"', false);
    }

    public function test_admin_can_upload_different_mobile_and_desktop_brand_images(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $this->get(route('admin.appearance.edit'))->assertOk()
            ->assertSee('Masaüstü logosu')->assertSee('Mobil logosu');

        $this->patch(route('admin.appearance.update'), [
            'desktop_logo' => UploadedFile::fake()->image('desktop.png', 800, 200),
            'mobile_logo' => UploadedFile::fake()->image('mobile.png', 320, 320),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appearance = ShopAppearance::current();
        $this->assertNotNull($appearance->desktop_logo_path);
        $this->assertNotNull($appearance->mobile_logo_path);
        $this->assertNotSame($appearance->desktop_logo_path, $appearance->mobile_logo_path);
        Storage::disk('public')->assertExists(substr($appearance->desktop_logo_path, 8));
        Storage::disk('public')->assertExists(substr($appearance->mobile_logo_path, 8));

        $this->get('/')->assertOk()
            ->assertSee('store-logo-desktop')
            ->assertSee('store-logo-mobile')
            ->assertSee('store-logo-mega');
    }

    public function test_branding_can_remove_old_logo_without_affecting_another_logo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $this->patch(route('admin.appearance.update'), [
            'desktop_logo' => UploadedFile::fake()->image('desktop.png', 800, 200),
            'mobile_logo' => UploadedFile::fake()->image('mobile.png', 320, 320),
        ])->assertSessionHasNoErrors();

        $appearance = ShopAppearance::current();
        $desktop = $appearance->desktop_logo_path;
        $mobile = $appearance->mobile_logo_path;

        $this->patch(route('admin.appearance.update'), [
            'remove_mobile_logo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($appearance->fresh()->mobile_logo_path);
        $this->assertSame($desktop, $appearance->fresh()->desktop_logo_path);
        Storage::disk('public')->assertExists(substr($desktop, 8));
        Storage::disk('public')->assertMissing(substr($mobile, 8));
    }

    public function test_svg_script_and_non_admin_logo_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $this->patch(route('admin.appearance.update'), [
            'desktop_logo' => UploadedFile::fake()->image('unauthorized.jpg'),
        ])->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.appearance.update'), [
                'desktop_logo' => UploadedFile::fake()->image('unauthorized.jpg'),
            ])->assertForbidden();

        $this->actingAs($this->admin())
            ->patch(route('admin.appearance.update'), [
                'desktop_logo' => UploadedFile::fake()->create('unsafe.svg', 3, 'image/svg+xml'),
            ])->assertSessionHasErrors('desktop_logo');
    }

    public function test_multiple_announcement_lines_are_independently_created_and_ordered(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.announcements.store'), [
            'messages' => "1000 TL üzeri kargo bedava\nYeni surf kurşunları geldi\n",
            'is_active' => '1',
            'sort_order' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('shop_announcements', 2);
        $this->assertEquals([2, 3], ShopAnnouncement::orderBy('sort_order')->pluck('sort_order')->all());
        $this->get('/')->assertOk()
            ->assertSee('announcement-ticker')
            ->assertSee('1000 TL üzeri kargo bedava')
            ->assertSee('Yeni surf kurşunları geldi');

        $this->get(route('admin.announcements.index'))->assertOk()
            ->assertSee('Yönetilen duyurular');
    }

    public function test_announcement_start_end_schedule_uses_istanbul_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00', 'Europe/Istanbul')->utc());
        try {
            $this->actingAs($this->admin());
            $this->post(route('admin.announcements.store'), [
                'messages' => 'Öğle kampanyası',
                'is_active' => 1,
                'sort_order' => 0,
                'starts_at' => '2026-11-10T10:00',
                'ends_at' => '2026-11-10T12:00',
            ])->assertSessionHasNoErrors();

            $notice = ShopAnnouncement::firstOrFail();
            $this->assertSame('07:00', $notice->starts_at->format('H:i'));
            $this->assertSame('09:00', $notice->ends_at->format('H:i'));

            $this->assertStringNotContainsString('Öğle kampanyası', $this->get('/')->getContent());
            Carbon::setTestNow(Carbon::parse('2026-11-10 11:00:00', 'Europe/Istanbul')->utc());
            $this->get('/')->assertSee('Öğle kampanyası');
            Carbon::setTestNow(Carbon::parse('2026-11-10 12:01:00', 'Europe/Istanbul')->utc());
            $this->assertStringNotContainsString('Öğle kampanyası', $this->get('/')->getContent());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_announcement_invalid_date_and_oversized_message_are_rejected(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.announcements.store'), [
            'messages' => 'Yanlış kampanya',
            'sort_order' => 0,
            'is_active' => 1,
            'starts_at' => '2026-11-10T15:00',
            'ends_at' => '2026-11-10T12:00',
        ])->assertSessionHasErrors('ends_at');

        $this->post(route('admin.announcements.store'), [
            'messages' => str_repeat('Z', 241),
            'sort_order' => 0,
            'is_active' => 1,
        ])->assertSessionHasErrors('messages');

        $this->assertDatabaseCount('shop_announcements', 0);
    }

    public function test_paused_and_expired_announcements_are_hidden_and_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $notice = ShopAnnouncement::create(['message'=>'Duyuru', 'is_active'=>false, 'sort_order'=>0]);
        $this->assertStringNotContainsString('Duyuru', $this->get('/')->getContent());

        $this->patch(route('admin.announcements.update', $notice), [
            'message'=>'Yeni duyuru','is_active'=>'1','sort_order'=>0,
        ])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('Yeni duyuru');

        $this->delete(route('admin.announcements.destroy', $notice))->assertRedirect();
        $this->assertDatabaseCount('shop_announcements', 0);
    }

    public function test_social_login_shows_original_colored_logos_but_is_disabled(): void
    {
        foreach ([route('account.login'), route('account.register')] as $route) {
            $html = $this->get($route)->assertOk()
                ->assertSee('Google')
                ->assertSee('Facebook')
                ->assertSee('Yakında')
                ->getContent();

            $this->assertStringContainsString('fill="#EA4335"', $html);
            $this->assertStringContainsString('fill="#0866FF"', $html);
            $this->assertSame(2, substr_count($html, 'class="social-auth-button" type="button" disabled'));
        }
    }
}
