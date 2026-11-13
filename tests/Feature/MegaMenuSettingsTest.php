<?php

namespace Tests\Feature;

use App\Models\MegaMenuSetting;
use App\Models\ShopAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MegaMenuSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_hero_copy_quick_buttons_and_eyebrows_are_removed(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('Avın her anına')
            ->assertDontSee('mega-quick-links')
            ->assertDontSee('mega-hero-art')
            ->assertDontSee('25 ana kategori')
            ->assertDontSee('POLARIS DÜNYASI')
            ->assertSee('Kategorileri keşfet');
    }

    public function test_admin_routes_are_authorized_and_nested_under_menus(): void
    {
        $this->get(route('admin.menus.mega-menu.edit'))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.menus.mega-menu.edit'))->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.menus.mega-menu.edit'))->assertOk()
            ->assertSee('Menüler')
            ->assertSee('Mega menü ayarları');
    }

    public function test_admin_can_manage_links_whatsapp_message_and_footer(): void
    {
        $this->actingAs($this->admin())->patch(route('admin.menus.mega-menu.update'), [
            'instagram_url' => 'https://instagram.com/yeni_polaris',
            'youtube_url' => '',
            'facebook_url' => '',
            'x_url' => 'https://x.com/polariskursun',
            'whatsapp_number' => '0546 262 90 02',
            'whatsapp_message' => 'Merhaba, bilgi alabilir miyim?',
            'footer_text' => 'POLARIS • TÜRKİYE',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('https://instagram.com/yeni_polaris', $html);
        $this->assertStringNotContainsString('https://youtube.com/@polariskursun', $html);
        $this->assertStringNotContainsString('https://facebook.com/polariskursun', $html);
        $this->assertStringContainsString('https://x.com/polariskursun', $html);
        $this->assertStringContainsString('https://wa.me/905462629002?text=Merhaba%2C%20bilgi%20alabilir%20miyim%3F', $html);
        $this->assertStringContainsString('POLARIS • TÜRKİYE', $html);

        $this->patch(route('admin.menus.mega-menu.update'), ['footer_text' => 'POLARIS'])
            ->assertSessionHasNoErrors();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('mega-social-grid', $html);
        $this->assertStringNotContainsString('class="mega-whatsapp"', $html);
    }

    public function test_admin_can_upload_replace_and_remove_hero_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $this->patch(route('admin.menus.mega-menu.update'), [
            'hero_image' => UploadedFile::fake()->image('menu.png', 1200, 750),
            'footer_text' => 'Polaris',
        ])->assertSessionHasNoErrors();

        $first = MegaMenuSetting::current()->hero_image_path;
        Storage::disk('public')->assertExists(substr($first, 8));
        $this->get('/')->assertSee('mega-menu-banner')->assertSee(asset($first));

        $this->patch(route('admin.menus.mega-menu.update'), [
            'hero_image' => UploadedFile::fake()->image('second.png', 1200, 750),
            'footer_text' => 'Polaris',
        ])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing(substr($first, 8));

        $this->patch(route('admin.menus.mega-menu.update'), [
            'remove_hero_image' => 1,
            'footer_text' => 'Polaris',
        ])->assertSessionHasNoErrors();
        $this->assertNull(MegaMenuSetting::current()->hero_image_path);
        $this->get('/')->assertDontSee('mega-menu-banner');
    }

    public function test_unsafe_hero_upload_and_non_https_protocol_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->patch(route('admin.menus.mega-menu.update'), [
            'hero_image' => UploadedFile::fake()->create('x.svg', 3, 'image/svg+xml'),
            'instagram_url' => 'javascript:alert(1)',
            'footer_text' => 'Polaris',
        ])->assertSessionHasErrors(['hero_image', 'instagram_url']);
    }

    public function test_mega_menu_uses_the_same_mobile_logo_as_the_header(): void
    {
        ShopAppearance::current()->update([
            'desktop_logo_path' => 'storage/branding/desktop.png',
            'mobile_logo_path' => 'storage/branding/mobile.png',
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('store-logo-mega mega-brand-logo', $html);
        $this->assertStringContainsString('storage/branding/mobile.png', $html);
    }
}
