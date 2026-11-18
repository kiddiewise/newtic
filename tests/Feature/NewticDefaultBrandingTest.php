<?php

namespace Tests\Feature;

use App\Models\ShopAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class NewticDefaultBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    public function test_bundled_default_logos_are_real_webp_images(): void
    {
        foreach ([
            ShopAppearance::DEFAULT_DESKTOP_LOGO => [800, 200],
            ShopAppearance::DEFAULT_MOBILE_LOGO => [320, 320],
        ] as $path => $dimensions) {
            $file = public_path($path);
            $this->assertFileExists($file);
            $this->assertSame('image/webp', mime_content_type($file));
            $this->assertSame($dimensions, array_slice(getimagesize($file), 0, 2));
        }
    }

    public function test_fallback_logos_appear_in_header_mobile_menu_footer_auth_and_admin(): void
    {
        $default = ShopAppearance::current();
        $this->assertSame(ShopAppearance::DEFAULT_DESKTOP_LOGO, $default->desktopLogo());
        $this->assertSame(ShopAppearance::DEFAULT_MOBILE_LOGO, $default->mobileLogo());

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('/assets/branding/newtic-logo.webp', $html);
        $this->assertStringContainsString('/assets/branding/newtic-mark.webp', $html);
        foreach (['store-logo-desktop', 'store-logo-mobile', 'store-logo-mega', 'store-logo-footer'] as $style) {
            $this->assertStringContainsString($style, $html);
        }
        $this->get(route('account.login'))->assertOk()
            ->assertSee('assets/branding/newtic-logo.webp');
        $this->get(route('admin.login'))->assertOk()
            ->assertSee('assets/branding/newtic-logo.webp');

        $this->actingAs($this->admin());
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('admin-sidebar-brand-logo')
            ->assertSee('assets/branding/newtic-mark.webp');
        $this->get(route('admin.appearance.edit'))->assertOk()
            ->assertSee('Varsayılan NEWTIC masaüstü logosu')
            ->assertSee('Varsayılan NEWTIC mobil logosu');
    }

    public function test_customer_uploaded_logos_override_defaults_and_removing_them_restores_defaults(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->patch(route('admin.appearance.update'), [
            'desktop_logo' => UploadedFile::fake()->image('custom-desktop.png', 800, 200),
            'mobile_logo' => UploadedFile::fake()->image('custom-mobile.png', 320, 320),
        ])->assertSessionHasNoErrors();
        $appearance = ShopAppearance::current()->fresh();
        $this->assertSame($appearance->desktop_logo_path, $appearance->desktopLogo());
        $this->assertSame($appearance->mobile_logo_path, $appearance->mobileLogo());
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString($appearance->desktop_logo_path, $html);
        $this->assertStringContainsString($appearance->mobile_logo_path, $html);
        $this->assertStringNotContainsString('/assets/branding/newtic-logo.webp', $html);

        $this->patch(route('admin.appearance.update'), ['remove_mobile_logo' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame($appearance->desktop_logo_path, $appearance->fresh()->mobileLogo());
        $this->patch(route('admin.appearance.update'), ['remove_desktop_logo' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ShopAppearance::DEFAULT_DESKTOP_LOGO, $appearance->fresh()->desktopLogo());
        $this->assertSame(ShopAppearance::DEFAULT_MOBILE_LOGO, $appearance->fresh()->mobileLogo());
    }

    public function test_portable_installer_preserves_brand_assets_and_matching_revision(): void
    {
        $script = file_get_contents(base_path('scripts/install-production.sh'));
        $this->assertStringContainsString('.newtic-release-revision', $script);
        $this->assertStringContainsString('public/assets/branding/', $script);
        $workflow = file_get_contents(base_path('.github/workflows/checks.yml'));
        $this->assertStringContainsString('newtic-complete-install.zip', $workflow);
        $this->assertStringContainsString('git archive --format=tar HEAD', $workflow);
    }
}
