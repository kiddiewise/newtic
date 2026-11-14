<?php

namespace Tests\Feature;

use App\Models\GoogleLoginSetting;
use App\Models\ShopAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class GoogleCustomerLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '1234567-customer.apps.googleusercontent.com';

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        return $admin;
    }

    private function enable(): GoogleLoginSetting
    {
        $settings = GoogleLoginSetting::current();
        $settings->update([
            'client_id' => self::CLIENT_ID,
            'client_secret' => 'not-visible-to-customers',
            'enabled' => true,
        ]);
        return $settings;
    }

    private function fakeGoogle(array $overrides = []): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['id_token' => 'signed-google-token'], 200),
            'https://oauth2.googleapis.com/tokeninfo*' => function () use ($overrides) {
                $session = session('google_customer_oauth');
                return Http::response(array_merge([
                    'aud' => self::CLIENT_ID,
                    'iss' => 'https://accounts.google.com',
                    'exp' => (string) now()->addMinutes(45)->timestamp,
                    'nonce' => $session['nonce'] ?? null,
                    'email_verified' => 'true',
                    'sub' => '11223344556677889900',
                    'email' => 'customer@example.com',
                    'name' => 'Google Customer',
                ], $overrides), 200);
            },
        ]);
    }

    public function test_google_is_inactive_by_default_and_admin_can_enable_without_leaking_secrets(): void
    {
        $this->get(route('account.login'))->assertOk()->assertDontSee('Google ile devam et');
        $this->get(route('account.register'))->assertOk()->assertDontSee('Facebook ile giriş');

        $this->get(route('account.google.start'))->assertRedirect(route('account.login'));
        $this->get(route('admin.google-login.edit'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.google-login.edit'))->assertForbidden();

        $this->actingAs($this->admin())->patch(route('admin.google-login.update'), [
            'client_id' => self::CLIENT_ID, 'client_secret' => 'secret-in-database',
            'enabled' => '1',
        ])->assertSessionHasNoErrors();

        $settings = GoogleLoginSetting::current();
        $this->assertTrue($settings->available());
        $this->assertNotSame('secret-in-database',
            DB::table('google_login_settings')->first()->client_secret);
        $this->assertArrayNotHasKey('client_secret', $settings->toArray());
        $this->assertStringNotContainsString('secret-in-database',
            $this->get(route('admin.google-login.edit'))->assertOk()->getContent());

        $this->patch(route('admin.google-login.update'), [
            'client_id' => self::CLIENT_ID, 'client_secret' => '', 'enabled' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($settings->fresh()->enabled);
        $this->assertSame('secret-in-database', $settings->fresh()->client_secret);
    }

    public function test_google_customer_registration_verifies_identity_and_preserves_wishlist_intent(): void
    {
        $this->enable();
        $this->fakeGoogle();
        $this->get(route('account.google.start', ['next' => 'wishlist']))->assertRedirect();
        $pending = session('google_customer_oauth');
        $this->assertNotEmpty($pending['nonce']);
        $this->assertNotEmpty($pending['verifier']);

        $response = $this->get(route('account.google.callback', [
            'state' => $pending['state'], 'code' => 'valid-code',
        ]))->assertRedirect(route('wishlist.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'google_sub' => '11223344556677889900',
            'name' => 'Google Customer',
            'is_admin' => false,
        ]);
        $this->assertAuthenticatedAs(User::where('google_sub', '11223344556677889900')->firstOrFail());
        $this->assertNotNull(auth()->user()->email_verified_at);
        $this->assertNull(session('google_customer_oauth'));
        Http::assertSent(function ($request) {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['client_id'] === self::CLIENT_ID
                && filled($request['code_verifier'])
                && $request['code'] === 'valid-code';
        });
    }

    public function test_repeated_google_login_uses_same_subject_without_creating_new_account(): void
    {
        $this->enable();
        $this->fakeGoogle();
        $this->get(route('account.google.start'))->assertRedirect();
        $pending = session('google_customer_oauth');
        $this->get(route('account.google.callback', [
            'state' => $pending['state'], 'code' => 'valid-code',
        ]))->assertRedirect();
        $first = auth()->id();

        $this->post(route('account.logout'))->assertRedirect();
        $this->get(route('account.google.start'))->assertRedirect();
        $pending = session('google_customer_oauth');
        $this->get(route('account.google.callback', [
            'state' => $pending['state'], 'code' => 'another-code',
        ]))->assertRedirect(route('account.index'));

        $this->assertSame($first, auth()->id());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_refuses_unlinked_existing_email_even_when_google_email_is_verified(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $this->enable();
        $this->fakeGoogle();
        $this->get(route('account.google.start'))->assertRedirect();
        $pending = session('google_customer_oauth');
        $this->get(route('account.google.callback', [
            'state' => $pending['state'], 'code' => 'valid-code',
        ]))->assertRedirect(route('account.login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_sub);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_state_is_one_time_and_rejects_expired_or_unverified_tokens(): void
    {
        $this->enable();
        Http::fake();

        $this->get(route('account.google.start'))->assertRedirect();
        $pending = session('google_customer_oauth');
        $this->get(route('account.google.callback', [
            'state' => 'forged-state', 'code' => 'fake-code',
        ]))->assertRedirect(route('account.login'))->assertSessionHasErrors('google');
        Http::assertNothingSent();

        $this->fakeGoogle(['email_verified' => 'false']);
        $this->get(route('account.google.start'))->assertRedirect();
        $pending = session('google_customer_oauth');
        $this->get(route('account.google.callback', [
            'state' => $pending['state'], 'code' => 'valid-code',
        ]))->assertRedirect(route('account.login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get(route('account.google.callback', [
            'state' => $pending['state'], 'code' => 'valid-code',
        ]))->assertSessionHasErrors('google');
    }

    public function test_disabled_google_will_not_authenticate_existing_google_user(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $user->forceFill(['google_sub' => '11223344556677889900'])->save();
        GoogleLoginSetting::current()->update(['enabled' => false]);
        $this->get(route('account.google.start'))->assertRedirect(route('account.login'));
        $this->assertGuest();
    }

    public function test_admin_can_upload_remove_and_preview_premium_auth_images(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->patch(route('admin.google-login.images'), [
            'images' => [
                UploadedFile::fake()->image('portrait-one.jpg', 1600, 2000),
                UploadedFile::fake()->image('portrait-two.jpg', 1600, 2000),
            ],
        ])->assertSessionHasNoErrors();
        $appearance = ShopAppearance::current();
        $images = $appearance->auth_image_paths;
        $this->assertCount(2, $images);
        foreach ($images as $path) Storage::disk('public')->assertExists(substr($path, 8));
        $html = $this->get(route('account.login'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-auth-slide aria-hidden='));
        $this->assertStringContainsString('data-auth-slideshow', $html);
        $this->assertStringContainsString('portrait', $this->get(route('admin.google-login.edit'))->assertOk()->getContent()) === false;

        $this->patch(route('admin.google-login.images'), ['remove' => [0]])
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing(substr($images[0], 8));
        $this->assertCount(1, ShopAppearance::current()->fresh()->auth_image_paths);
    }
}
