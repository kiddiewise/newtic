<?php

namespace Tests\Feature;

use App\Models\SeoSetting;
use App\Models\User;
use App\Services\StoreInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private string $tempSetupDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempSetupDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'newtic-setup-'.Str::random(20);
        config()->set('newtic.install_private_directory', $this->tempSetupDirectory);
        config()->set('newtic.checkout_test_mode', true);
        config()->set('newtic.paytr_card_payments_enabled', false);
        config()->set('newtic.paytr_webhooks_enabled', false);
        config()->set('newtic.shipping_dummy_enabled', false);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempSetupDirectory);
        parent::tearDown();
    }

    private function unlock(): string
    {
        $token=app(StoreInstaller::class)->issueToken();
        $this->post(route('install.unlock'),['setup_token'=>$token])
            ->assertRedirect(route('install.checks'))->assertSessionHasNoErrors();

        return $token;
    }

    private function details(): array
    {
        return [
            'store_name'=>'Sinay Design',
            'admin_name'=>'Sinay Kurucu',
            'admin_email'=>'admin@sinaydesign.example',
            'password'=>'StrongerSetupPass!2026',
            'password_confirmation'=>'StrongerSetupPass!2026',
            'accept_safe_mode'=>1,
        ];
    }

    public function test_existing_shop_cannot_start_or_access_installer(): void
    {
        User::factory()->create();
        $this->assertFalse(app(StoreInstaller::class)->pristine());
        $this->expectException(\RuntimeException::class);
        app(StoreInstaller::class)->issueToken();
    }

    public function test_clean_existing_site_hides_installer_until_secure_cli_token_created(): void
    {
        $this->get(route('install.index'))->assertNotFound();
        $this->post(route('install.unlock'), ['setup_token'=>str_repeat('a',64)])->assertNotFound();
        $this->get(route('home'))->assertOk();
    }

    public function test_setup_token_is_only_stored_hashed_and_every_other_route_is_blocked(): void
    {
        $token = app(StoreInstaller::class)->issueToken();
        $this->assertFileExists($this->tempSetupDirectory.'/pending.json');
        $this->assertStringNotContainsString($token,File::get($this->tempSetupDirectory.'/pending.json'));
        $this->get(route('home'))->assertStatus(503)->assertSee('Mağaza hazırlanıyor');
        $this->get(route('account.register'))->assertStatus(503);
        $this->post(route('account.register.submit'), [
            'name'=>'Wrong Customer','email'=>'unapproved@example.com',
            'password'=>'Test1234!','password_confirmation'=>'Test1234!',
        ])->assertStatus(503);
        $this->assertDatabaseCount('users',0);

        $this->get(route('install.index'))->assertOk()->assertSee('ilk adım');
        $this->get(route('install.checks'))->assertNotFound();
        $this->post(route('install.unlock'), ['setup_token'=>str_repeat('a',64)])
            ->assertSessionHasErrors('setup_token');
        $this->get(route('install.checks'))->assertNotFound();

        $this->post(route('install.unlock'), ['setup_token'=>$token])
            ->assertRedirect(route('install.checks'))->assertSessionHasNoErrors();
        $this->get(route('install.checks'))->assertOk()->assertSee('Sunucunuz hazır mı?');
        $this->get(route('install.details'))->assertOk()->assertSee('Mağazanızı oluşturalım.');
    }

    public function test_wizard_creates_isolated_admin_and_keeps_noindex_test_payments_off_then_locks(): void
    {
        $this->unlock();
        $this->post(route('install.finish'),$this->details())->assertRedirect(route('admin.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users',[
            'email'=>'admin@sinaydesign.example', 'is_admin'=>1,
        ]);
        $this->assertDatabaseCount('users',1);
        $this->assertDatabaseHas('newtic_installations',[
            'id'=>1,'version'=>'1.0.0','admin_user_id'=>User::first()->id,
        ]);
        $this->assertDatabaseCount('products',0);
        $this->assertDatabaseCount('orders',0);
        $this->assertSame('Sinay Design',SeoSetting::current()->site_name);
        $this->assertTrue(SeoSetting::current()->noindex);
        $this->assertTrue(config('newtic.checkout_test_mode'));
        $this->assertTrue(app(StoreInstaller::class)->installed());
        $this->assertFileExists($this->tempSetupDirectory.'/installed.lock');
        $this->assertFileDoesNotExist($this->tempSetupDirectory.'/pending.json');

        $this->get(route('install.index'))->assertNotFound();
        $this->get(route('install.details'))->assertNotFound();
        $this->post(route('install.finish'), $this->details())->assertNotFound();
        $this->assertDatabaseCount('users',1);
        $this->get(route('admin.index'))->assertOk();
    }

    public function test_password_validation_prevents_partial_installation(): void
    {
        $this->unlock();
        $data = $this->details();
        $data['password']='weakpassword';
        $data['password_confirmation']='weakpassword';
        $this->post(route('install.finish'),$data)->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users',0);
        $this->assertDatabaseCount('newtic_installations',0);
        $this->get(route('install.details'))->assertOk();
    }

    public function test_token_expiry_and_rotation_invalidate_old_tokens_and_sessions(): void
    {
        $token = app(StoreInstaller::class)->issueToken();
        $this->travel(3)->hours();
        $this->post(route('install.unlock'),['setup_token'=>$token])
            ->assertSessionHasErrors('setup_token');
        $other = app(StoreInstaller::class)->issueToken();
        $this->assertNotSame($token,$other);
        $this->post(route('install.unlock'),['setup_token'=>$token])
            ->assertSessionHasErrors('setup_token');
        $this->post(route('install.unlock'),['setup_token'=>$other])
            ->assertRedirect(route('install.checks'));
        app(StoreInstaller::class)->issueToken();
        $this->get(route('install.checks'))->assertNotFound();
    }

    public function test_private_file_token_supports_mobile_hosting_setup_without_outputting_secret(): void
    {
        $this->artisan('newtic:setup-token', ['--private-file' => true])->assertSuccessful();

        $file = $this->tempSetupDirectory.'/mobile-install-code.txt';
        $this->assertFileExists($file);
        $token = trim(File::get($file));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $token);
        $this->assertTrue(app(StoreInstaller::class)->validToken($token));
        $this->assertStringNotContainsString($token, File::get($this->tempSetupDirectory.'/pending.json'));

        $this->post(route('install.unlock'), ['setup_token' => $token])
            ->assertRedirect(route('install.checks'));
        $this->post(route('install.finish'), $this->details())
            ->assertRedirect(route('admin.index'));
        $this->assertFileDoesNotExist($file);
    }

    public function test_private_file_token_is_rotated_and_expires(): void
    {
        $this->artisan('newtic:setup-token', ['--private-file' => true])->assertSuccessful();
        $file = $this->tempSetupDirectory.'/mobile-install-code.txt';
        $first = trim(File::get($file));
        $this->artisan('newtic:setup-token', ['--private-file' => true])->assertSuccessful();
        $second = trim(File::get($file));

        $this->assertNotSame($first, $second);
        $this->assertFalse(app(StoreInstaller::class)->validToken($first));
        $this->assertTrue(app(StoreInstaller::class)->validToken($second));
        $this->travel(3)->hours();
        $this->assertFalse(app(StoreInstaller::class)->validToken($second));
        $this->get(route('home'))->assertOk();
    }

    public function test_missing_db_installation_record_is_not_confused_with_a_completed_store(): void
    {
        $installer=app(StoreInstaller::class);
        $this->unlock();
        $this->post(route('install.finish'),$this->details())->assertRedirect(route('admin.index'));
        File::delete($this->tempSetupDirectory.'/installed.lock');
        $this->assertTrue($installer->installed());
        $this->get(route('install.index'))->assertNotFound();
        $this->assertDatabaseCount('newtic_installations',1);
    }
}
