<?php

namespace Tests\Feature;

use App\Models\PaytrSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaytrSettingsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function credentials(string $id = '123456'): array
    {
        return [
            'merchant_id' => $id,
            'merchant_key' => 'private_test_key_ghK79E1',
            'merchant_salt' => 'private_test_salt_8fk2PKM',
        ];
    }

    public function test_only_authorized_administrators_can_read_or_save_paytr_settings(): void
    {
        $url = route('admin.paytr-settings.edit');
        $save = route('admin.paytr-settings.update');

        $this->get($url)->assertRedirect(route('admin.login'));
        $this->patch($save, $this->credentials())->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->get($url)->assertForbidden();
        $this->patch($save, $this->credentials())->assertForbidden();
        $this->assertDatabaseCount('paytr_settings', 0);

        $this->actingAs($this->admin())
            ->get($url)->assertOk()
            ->assertSee('PAYTR entegrasyonu')
            ->assertSee('Mağaza No')
            ->assertSee('Mağaza Parola')
            ->assertSee('Mağaza Gizli Anahtar')
            ->assertSee('action="'.route('admin.paytr-settings.update').'"', false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_credentials_are_encrypted_at_rest_and_never_reflected_in_admin_html(): void
    {
        $this->actingAs($this->admin());
        $save = $this->patch(route('admin.paytr-settings.update'), $this->credentials());
        $save->assertRedirect(route('admin.paytr-settings.edit'))->assertSessionHasNoErrors();

        $stored = PaytrSetting::findOrFail(1);
        $this->assertSame('123456', $stored->merchant_id);
        $this->assertSame('private_test_key_ghK79E1', $stored->merchant_key);
        $this->assertSame('private_test_salt_8fk2PKM', $stored->merchant_salt);
        $this->assertTrue($stored->hasCredentials());

        $raw = DB::table('paytr_settings')->where('id', 1)->first();
        $this->assertNotSame('private_test_key_ghK79E1', $raw->merchant_key);
        $this->assertNotSame('private_test_salt_8fk2PKM', $raw->merchant_salt);
        $this->assertStringNotContainsString('private_test_key_', $raw->merchant_key);
        $this->assertStringNotContainsString('private_test_salt_', $raw->merchant_salt);
        $this->assertFalse((bool) $raw->is_enabled);
        $this->assertTrue((bool) $raw->test_mode);
        $this->assertArrayNotHasKey('merchant_key', $stored->toArray());
        $this->assertArrayNotHasKey('merchant_salt', $stored->toArray());

        $body = $this->get(route('admin.paytr-settings.edit'))->assertOk()
            ->assertSee('Kayıtlı · Doğrulanmadı')
            ->assertSee('Kayıtlı ve şifreli')
            ->assertSee('type="password"', false)
            ->getContent();
        $this->assertStringNotContainsString('private_test_key_', $body);
        $this->assertStringNotContainsString('private_test_salt_', $body);
        $this->assertStringContainsString('name="merchant_id"', $body);
        $this->assertStringNotContainsString('value="private_test_key_', $body);
    }

    public function test_empty_secret_fields_preserve_existing_encrypted_values(): void
    {
        $this->actingAs($this->admin());
        $this->patch(route('admin.paytr-settings.update'), $this->credentials())
            ->assertSessionHasNoErrors();

        $before = DB::table('paytr_settings')->where('id', 1)->first();
        $this->patch(route('admin.paytr-settings.update'), [
            'merchant_id' => '123456',
            'merchant_key' => '',
            'merchant_salt' => '',
        ])->assertSessionHasNoErrors();

        $after = DB::table('paytr_settings')->where('id', 1)->first();
        $this->assertSame($before->merchant_key, $after->merchant_key);
        $this->assertSame($before->merchant_salt, $after->merchant_salt);
    }

    public function test_secret_rotation_works_and_a_different_merchant_id_requires_both_new_secrets(): void
    {
        $this->actingAs($this->admin());
        $this->patch(route('admin.paytr-settings.update'), $this->credentials())
            ->assertSessionHasNoErrors();

        $this->patch(route('admin.paytr-settings.update'), [
            'merchant_id' => '222222',
            'merchant_key' => '',
            'merchant_salt' => '',
        ])->assertSessionHasErrors(['merchant_key', 'merchant_salt']);
        $this->assertSame('123456', PaytrSetting::findOrFail(1)->merchant_id);

        $this->patch(route('admin.paytr-settings.update'), [
            'merchant_id' => '222222',
            'merchant_key' => 'rotated_private_key_99',
            'merchant_salt' => 'rotated_private_salt_77',
        ])->assertSessionHasNoErrors();
        $this->assertSame('222222', PaytrSetting::findOrFail(1)->merchant_id);
        $this->assertSame('rotated_private_key_99', PaytrSetting::findOrFail(1)->merchant_key);
        $this->assertSame('rotated_private_salt_77', PaytrSetting::findOrFail(1)->merchant_salt);
        $this->assertFalse(PaytrSetting::findOrFail(1)->is_enabled);
    }

    public function test_invalid_inputs_are_never_written_or_flashed_to_session(): void
    {
        $this->actingAs($this->admin());
        $this->patch(route('admin.paytr-settings.update'), [
            'merchant_id' => 'not valid / id',
            'merchant_key' => 'leak_should_never_be_flashed',
            'merchant_salt' => 'another_private_secret',
        ])->assertSessionHasErrors('merchant_id');
        $this->assertDatabaseCount('paytr_settings', 0);
        $old = session()->get('_old_input', []);
        $this->assertArrayNotHasKey('merchant_key', $old);
        $this->assertArrayNotHasKey('merchant_salt', $old);
        $this->assertStringNotContainsString('leak_should_never_be_flashed', json_encode(session()->all()));

        $this->patch(route('admin.paytr-settings.update'), [
            'merchant_id' => '123456',
            'merchant_key' => '',
            'merchant_salt' => '',
        ])->assertSessionHasErrors(['merchant_key', 'merchant_salt']);
        $this->assertDatabaseCount('paytr_settings', 0);
    }

    public function test_credentials_do_not_enable_card_checkout_or_process_webhooks(): void
    {
        $this->actingAs($this->admin());
        $this->patch(route('admin.paytr-settings.update'), $this->credentials())
            ->assertSessionHasNoErrors();

        $this->assertFalse(PaytrSetting::findOrFail(1)->is_enabled);
        $checkout = (string) file_get_contents(resource_path('views/store/checkout.blade.php'));
        $this->assertStringContainsString('value="bank_transfer"', $checkout);
        $this->assertStringNotContainsString('value="paytr"', $checkout);

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('checkout.paytr.callback'));
        $this->post(route('checkout.paytr.callback'), [
            'merchant_oid' => 'NTSOMEUNKNOWNORDER',
            'status' => 'success',
            'total_amount' => '1000',
            'hash' => str_repeat('a', 44),
        ])->assertStatus(503);
    }
}
