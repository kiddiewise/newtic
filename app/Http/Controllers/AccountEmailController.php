<?php

namespace App\Http\Controllers;

use App\Models\EmailSetting;
use App\Models\User;
use App\Services\EmailPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Illuminate\View\View;

final class AccountEmailController extends Controller
{
    public function forgot(): View
    {
        return view('store.account-forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required','email','max:255']]);
        $settings = EmailSetting::current();
        if (!$settings->active()) {
            return back()->withErrors(['email' => 'Şifre sıfırlama e-postası şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.']);
        }
        // Always return the same response to avoid disclosing registered emails.
        Password::sendResetLink(['email' => strtolower(trim($data['email']))]);
        return back()->with('success', 'Bu adresle kayıtlı bir müşteri hesabınız varsa şifre yenileme bağlantısı gönderilecektir.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('store.account-reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token'=>['required','string'],
            'email'=>['required','email','max:255'],
            'password'=>['required','confirmed',PasswordRules::min(8)],
        ]);
        $result = Password::reset($data, function (User $user, string $password): void {
            if ($user->is_admin) return;
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });
        // Admin-only accounts are handled by a separate internal recovery procedure.
        if ($result !== Password::PASSWORD_RESET || User::query()
            ->where('email', $data['email'])->where('is_admin', true)->exists()) {
            return back()->withErrors(['email' => 'Bağlantı geçersiz veya süresi dolmuş. Yeni şifre yenileme isteği oluşturun.']);
        }
        return redirect()->route('account.login')
            ->with('success', 'Şifreniz değiştirildi. Yeni şifrenizle giriş yapabilirsiniz.');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(false), 403);
        $user = User::query()->findOrFail($id);
        abort_unless(!$user->is_admin && hash_equals(
            sha1(strtolower($user->email)), $hash
        ), 403);
        if (!$user->email_verified_at) $user->forceFill(['email_verified_at'=>now()])->save();
        return redirect()->route('account.login')
            ->with('success', 'E-posta adresiniz doğrulandı. Hesabınıza giriş yapabilirsiniz.');
    }

    public function resend(Request $request, EmailPlanner $planner): RedirectResponse
    {
        $user = $request->user();
        if (!$user || $user->is_admin) abort(403);
        if ($user->email_verified_at) return back()->with('success', 'E-posta adresiniz zaten doğrulanmış.');
        $planner->verification($user, (string) Str::uuid());
        return back()->with('success', 'E-posta servisi etkinse doğrulama bağlantısı yeniden sıraya alındı.');
    }
}
