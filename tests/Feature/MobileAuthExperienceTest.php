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
                ->assertSee('POLARIS')
                ->getContent();

            $this->assertSame(1, substr_count($html, 'class="mobile-auth-header"'));
            $this->assertSame(1, substr_count($html, 'class="mobile-auth-tabs"'));
            $this->assertStringContainsString('aria-current="page"', $html);
        }

        $this->get(route('home'))->assertOk()
            ->assertDontSee('mobile-auth-header', false)
            ->assertDontSee('class="is-auth"', false);
    }

    public function test_social_providers_are_side_by_side_and_not_false_functional_logins(): void
    {
        foreach (['account.login', 'account.register'] as $name) {
            $html = $this->get(route($name))->assertOk()
                ->assertSee('Google')
                ->assertSee('Facebook')
                ->assertSee('Yakında')
                ->getContent();

            $this->assertSame(2, substr_count($html, 'class="social-auth-button" type="button" disabled'));
            $this->assertSame(2, substr_count($html, 'class="social-provider-name"'));
            $this->assertStringNotContainsString('href="/oauth/', $html);
        }

        $css = file_get_contents(resource_path('css/mobile-auth.css'));
        $this->assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr))', $css);
        $this->assertStringContainsString('body.is-auth .mobile-bottom-nav', $css);
        $this->assertStringContainsString('body.is-auth .site-footer', $css);
        $this->assertStringContainsString('body.is-auth .mobile-site-header', $css);
        $this->assertStringContainsString('body.is-auth .desktop-site-header', $css);
    }

    public function test_switching_mobile_auth_tabs_keeps_favorite_intent(): void
    {
        $params = ['next' => 'cart', 'favorite' => 45];
        $signup = route('account.register', $params);
        $login = route('account.login', $params);

        $this->get($login)->assertOk()->assertSee('href="'.$signup.'"', false);
        $this->get($signup)->assertOk()->assertSee('href="'.$login.'"', false);
    }

    public function test_normal_store_and_account_navigation_are_unaffected(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('mobile-bottom-nav', false);
        $this->get(route('search'))->assertOk()->assertSee('mobile-site-header', false);
    }
}
