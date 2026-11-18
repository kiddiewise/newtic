<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthVisualPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_keep_readable_separate_active_tabs(): void
    {
        $login = $this->get(route('account.login'))->assertOk()->getContent();
        $register = $this->get(route('account.register'))->assertOk()->getContent();

        $this->assertStringContainsString('auth-experience', $login);
        $this->assertStringContainsString('auth-artwork', $login);
        $this->assertStringContainsString('mobile-auth-tabs', $login);
        $this->assertStringContainsString('aria-current="page"', $login);
        $this->assertMatchesRegularExpression(
            '/class="is-active"[^>]*aria-current="page"[^>]*>Giriş Yap<\/a>/',
            $login
        );
        $this->assertMatchesRegularExpression(
            '/class="is-active"[^>]*aria-current="page"[^>]*>Üye Ol<\/a>/',
            $register
        );
        $this->assertStringContainsString('class="auth-forgot-link"', $login);
        $this->assertStringContainsString('name="email"', $login);
        $this->assertStringContainsString('name="password"', $login);
        $this->assertStringContainsString('name="password_confirmation"', $register);
    }

    public function test_white_image_to_form_fade_is_responsive_and_preserves_admin_image_management(): void
    {
        $css = file_get_contents(resource_path('css/auth-experience.css'));
        $this->assertIsString($css);

        $this->assertStringContainsString('body.is-auth .auth-artwork::after', $css);
        $this->assertStringContainsString('linear-gradient(to right,transparent', $css);
        $this->assertStringContainsString('linear-gradient(to bottom,#ffffff00', $css);
        $this->assertStringContainsString('body.is-auth .auth-artwork-slide img', $css);
        $this->assertStringContainsString('body.is-auth .auth-artwork-shade', $css);
        $this->assertStringContainsString('@media(max-width:800px)', $css);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);

        $this->assertStringContainsString('body.is-auth .account-auth-shell .auth-experience .mobile-auth-tabs a.is-active', $css);
        $this->assertStringContainsString('background:#092037!important;color:#fff!important', $css);
        $this->assertStringContainsString('body.is-auth .auth-fields .auth-submit', $css);
        $this->assertStringContainsString('body.is-auth .auth-fields .checkout-field input', $css);

        // Old mint/teal fallback and unreadable active-tab palette are gone.
        foreach (['#bee5d3', '#8dcabd', '#51a87d', '#25574a', '#153b37', '#f3f7f6'] as $oldColor) {
            $this->assertStringNotContainsString($oldColor, $css);
        }
        $this->assertStringContainsString("@import './auth-experience.css';", file_get_contents(resource_path('css/app.css')));

        $art = file_get_contents(resource_path('views/store/partials/auth-artwork.blade.php'));
        $this->assertStringContainsString('$authAppearance->auth_image_paths', $art);
        $this->assertStringContainsString('data-auth-slideshow', $art);
        $this->assertStringContainsString('auth-artwork-fallback', $art);
    }

    public function test_password_recovery_uses_the_same_corporate_visual_shell(): void
    {
        $forgot = $this->get(route('account.password.forgot'))->assertOk()->getContent();
        $this->assertStringContainsString('auth-experience', $forgot);
        $this->assertStringContainsString('auth-artwork', $forgot);
        $this->assertStringContainsString('mobile-auth-tabs', $forgot);
        $this->assertStringContainsString('name="email"', $forgot);
    }
}
