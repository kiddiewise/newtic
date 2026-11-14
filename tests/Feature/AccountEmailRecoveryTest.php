<?php

namespace Tests\Feature;

use App\Models\EmailDelivery;
use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AccountEmailRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function enableEmail(): void
    {
        EmailSetting::current()->update([
            'enabled'=>true, 'smtp_host'=>'smtp.example.com', 'smtp_port'=>587,
            'smtp_scheme'=>'smtp', 'smtp_username'=>'sender@example.com',
            'smtp_password'=>'hidden-test-secret', 'from_address'=>'sender@example.com',
            'from_name'=>'NEWTIC', 'test_recipient'=>'sink@example.com',
            'verified_at'=>now(), 'events'=>array_keys(EmailSetting::EVENTS),
        ]);
    }

    public function test_password_recovery_requires_active_email_and_hides_account_existence(): void
    {
        $user = User::factory()->create(['email'=>'buyer@example.com']);
        $this->get(route('account.password.forgot'))->assertOk()->assertSee('Şifreni yenile');
        $this->post(route('account.password.send'), ['email'=>$user->email])
            ->assertSessionHasErrors('email');
        $this->enableEmail();
        $this->post(route('account.password.send'), ['email'=>$user->email])
            ->assertSessionHasNoErrors();
        $delivery = EmailDelivery::where('event','customer_password_reset')->firstOrFail();
        $this->assertSame($user->id, $delivery->user_id);
        $this->assertNotSame($delivery->secret_payload, $delivery->getRawOriginal('secret_payload'));
        $this->post(route('account.password.send'), ['email'=>'notfound@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, EmailDelivery::where('event','customer_password_reset')->count());
    }

    public function test_password_reset_token_replaces_password_and_is_one_time(): void
    {
        $user = User::factory()->create(['email'=>'buyer@example.com']);
        $this->enableEmail();
        $this->post(route('account.password.send'), ['email'=>$user->email])->assertSessionHasNoErrors();
        $token = EmailDelivery::where('event','customer_password_reset')->firstOrFail()->secret_payload;
        $payload = ['token'=>$token, 'email'=>$user->email, 'password'=>'NewStrongPassword2026',
            'password_confirmation'=>'NewStrongPassword2026'];
        $this->post(route('account.password.update'), $payload)->assertRedirect(route('account.login'));
        $this->assertTrue(Auth::attempt(['email'=>$user->email,'password'=>'NewStrongPassword2026']));
        $this->post(route('account.password.update'), $payload)->assertSessionHasErrors('email');
    }

    public function test_signed_email_verification_cannot_be_forged(): void
    {
        $user = User::factory()->create(['email'=>'buyer@example.com']);
        $user->forceFill(['email_verified_at'=>null])->save();
        $bad = route('account.email.verify', ['id'=>$user->id,'hash'=>sha1($user->email)]);
        $this->get($bad)->assertForbidden();
        $url = URL::temporarySignedRoute('account.email.verify',
            now()->addMinutes(60), ['id'=>$user->id,'hash'=>sha1(strtolower($user->email))], absolute:false);
        $this->get($url)->assertRedirect(route('account.login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get($url)->assertRedirect(route('account.login'));
    }
}
