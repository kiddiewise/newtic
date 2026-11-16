<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'newtic-admin-recovery-'.Str::random(20);
        config()->set('newtic.admin_recovery_private_directory', $this->temporaryPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryPath);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.test',
            'password' => Hash::make('OriginalAdminPassword!123'),
        ]);
    }

    private function code(): string
    {
        $contents = File::get($this->temporaryPath.'/private-admin-recovery.txt');
        preg_match('/Tek kullanımlık kod: ([a-zA-Z0-9]+)/', $contents, $matches);

        return $matches[1] ?? '';
    }

    private function payload(string $token): array
    {
        return [
            'recovery_code' => $token,
            'password' => 'NewSecurePassword!2026',
            'password_confirmation' => 'NewSecurePassword!2026',
        ];
    }

    public function test_admin_recovery_is_not_accessible_before_private_cli_authorization(): void
    {
        $this->admin();
        $this->get('/admin/kurtarma')->assertNotFound();
        $this->post('/admin/kurtarma', $this->payload(str_repeat('a', 40)))->assertNotFound();
    }

    public function test_cli_does_not_print_password_or_code_and_creates_private_record(): void
    {
        $this->admin();
        $this->artisan('newtic:admin-recovery')->assertExitCode(1);
        $this->artisan('newtic:admin-recovery', ['--private-file' => true])
            ->assertSuccessful();

        $secret = $this->code();
        $this->assertGreaterThanOrEqual(40, strlen($secret));
        $this->assertStringContainsString('admin@example.test', File::get($this->temporaryPath.'/private-admin-recovery.txt'));
        $this->assertStringNotContainsString($secret, File::get($this->temporaryPath.'/access.json'));
        $this->assertFileExists($this->temporaryPath.'/access.json');
        $this->get('/admin/kurtarma')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Yönetici şifrenizi yenileyin.');
    }

    public function test_authorized_reset_only_changes_existing_admin_password_and_revokes_code(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'ordinary@example.test', 'password' => Hash::make('CustomerPassword!123')]);
        $this->artisan('newtic:admin-recovery', ['--private-file' => true])->assertSuccessful();
        $code = $this->code();

        $this->post('/admin/kurtarma', $this->payload(str_repeat('x', strlen($code))))
            ->assertSessionHasErrors('recovery_code');
        $this->assertTrue(Hash::check('OriginalAdminPassword!123', $admin->fresh()->password));

        $this->post('/admin/kurtarma', $this->payload($code))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewSecurePassword!2026', $admin->fresh()->password));
        $this->assertFalse(Hash::check('OriginalAdminPassword!123', $admin->fresh()->password));
        $this->assertDatabaseCount('users', 2);
        $this->assertTrue(Hash::check('CustomerPassword!123', User::where('email', 'ordinary@example.test')->first()->password));
        $this->assertFileDoesNotExist($this->temporaryPath.'/access.json');
        $this->assertFileDoesNotExist($this->temporaryPath.'/private-admin-recovery.txt');
        $this->get('/admin/kurtarma')->assertNotFound();
        $this->post('/admin/kurtarma', $this->payload($code))->assertNotFound();
        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'NewSecurePassword!2026',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_expired_code_cannot_reset_admin_and_original_password_stays_valid(): void
    {
        $admin = $this->admin();
        $this->artisan('newtic:admin-recovery', ['--private-file' => true])->assertSuccessful();
        $code = $this->code();
        $this->travel(31)->minutes();

        $this->get('/admin/kurtarma')->assertNotFound();
        $this->post('/admin/kurtarma', $this->payload($code))->assertNotFound();
        $this->assertTrue(Hash::check('OriginalAdminPassword!123', $admin->fresh()->password));
    }

    public function test_multiple_admins_refuse_automatic_recovery(): void
    {
        $this->admin();
        User::factory()->create(['is_admin' => true, 'email' => 'second@example.test']);
        $this->artisan('newtic:admin-recovery', ['--private-file' => true])->assertExitCode(1);
        $this->assertFileDoesNotExist($this->temporaryPath.'/private-admin-recovery.txt');
    }

    public function test_code_revoked_by_broker_rotation_cannot_reset_admin(): void
    {
        $admin = $this->admin();
        $this->artisan('newtic:admin-recovery', ['--private-file' => true])->assertSuccessful();
        $code = $this->code();
        Password::broker('users')->createToken($admin);

        $this->post('/admin/kurtarma', $this->payload($code))->assertSessionHasErrors('recovery_code');
        $this->assertTrue(Hash::check('OriginalAdminPassword!123', $admin->fresh()->password));
    }

    public function test_weak_password_is_rejected_without_changing_account(): void
    {
        $admin = $this->admin();
        $this->artisan('newtic:admin-recovery', ['--private-file' => true])->assertSuccessful();
        $request = $this->payload($this->code());
        $request['password'] = 'weak';
        $request['password_confirmation'] = 'weak';
        $this->post('/admin/kurtarma', $request)->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OriginalAdminPassword!123', $admin->fresh()->password));
    }
}
