<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_signup_share_mobile_focused_navigation_and_brand_close(): void
    {
        foreach (['account.login', 'account.register'] as $name) {
            $html = $this->get(route($name))->assertOk()
                ->assertSee('class="is-auth"', false)
                ->assertSee('mobile-auth-header', false)
                ->assertSee('mobile-auth-tabs', false)
                ->assertSee('Üyelik ekranından çık')
                ->assertSee('Giriş Yap')
                ->assertSee('Üye Ol')
                ->assertSee(\App\Support\StoreIdentity::name())
                ->getContent();

            $this->assertSame(1, substr_count($html, 'class="mobile-auth-header"'));
            $this->assertSame(1, substr_count($html, 'class="mobile-auth-tabs"'));
            $this->assertStringContainsString('aria-current="page"', $html);
        }

        $this->get(route('home'))->assertOk()
            ->assertDontSee('mobile-auth-header', false)
            ->assertDontSee('class="is-auth"', false);
    }

    public function test_only_google_social_login_is_visible_when_configured(): void
    {
        foreach (['account.login', 'account.register'] as $name) {
            $html = $this->get(route($name))->assertOk()->getContent();
            $this->assertStringNotContainsString('Facebook ile giriş', $html);
            $this->assertStringNotContainsString('Google ile devam et', $html);
            $this->assertStringContainsString('auth-experience', $html);
        }

        \App\Models\GoogleLoginSetting::current()->update([
            'client_id' => '123456-google.apps.googleusercontent.com',
            'client_secret' => 'oauth-secret-value',
            'enabled' => true,
        ]);
        foreach (['account.login', 'account.register'] as $name) {
            $html = $this->get(route($name))->assertOk()
                ->assertSee('Google ile devam et')
                ->assertDontSee('Facebook ile giriş')
                ->getContent();
            $this->assertSame(1, substr_count($html, 'class="auth-google-button"'));
        }

        $css = file_get_contents(resource_path('css/auth-experience.css'));
        $this->assertStringContainsString('grid-template-columns:', $css);
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $css);
        $this->assertStringContainsString('body.is-auth .mobile-bottom-nav', $css);
        $this->assertStringContainsString('body.is-auth .site-footer', $css);
    }

    public function test_switching_mobile_auth_tabs_keeps_favorite_intent(): void
    {
        $params = ['next' => 'cart', 'favorite' => 45];
        $signup = route('account.register', $params);
        $login = route('account.login', $params);

        $this->get($login)->assertOk()->assertSee('href="'.e($signup).'"', false);
        $this->get($signup)->assertOk()->assertSee('href="'.e($login).'"', false);
    }

    public function test_normal_store_and_account_navigation_are_unaffected(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('mobile-bottom-nav', false);
        $this->get(route('search'))->assertOk()->assertSee('mobile-site-header', false);
    }
}
