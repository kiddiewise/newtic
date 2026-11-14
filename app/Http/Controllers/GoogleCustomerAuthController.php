<?php

namespace App\Http\Controllers;

use App\Models\GoogleLoginSetting;
use App\Models\User;
use App\Support\GoogleCustomerOAuth;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class GoogleCustomerAuthController extends Controller
{
    public function start(Request $request, GoogleCustomerOAuth $google): RedirectResponse
    {
        if ($request->user()) return redirect()->route('account.index');
        $setting = GoogleLoginSetting::current();
        if (!$setting->available()) return redirect()->route('account.login')
            ->withErrors(['google' => 'Google ile giriş şu anda etkin değil.']);

        $intent = $request->validate([
            'next' => ['nullable', 'in:checkout,wishlist,cart,review'],
            'favorite' => ['nullable', 'integer', 'min:1'],
            'review_product' => ['nullable', 'string', 'max:200'],
        ]);

        $state = Str::random(64);
        $nonce = Str::random(64);
        $verifier = Str::random(96);
        $request->session()->put('google_customer_oauth', [
            'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier,
            'at' => now()->timestamp, 'intent' => $intent,
        ]);

        return redirect()->away($google->authorizationUrl($setting, $state, $nonce, $verifier));
    }

    public function callback(Request $request, GoogleCustomerOAuth $google, AccountController $accounts): RedirectResponse
    {
        $pending = $request->session()->pull('google_customer_oauth');
        $setting = GoogleLoginSetting::current();

        if (!$setting->available() || !is_array($pending) || empty($pending['state'])
            || empty($pending['nonce']) || empty($pending['verifier'])
            || now()->timestamp - (int) ($pending['at'] ?? 0) > 600
            || !hash_equals((string) $pending['state'], (string) $request->query('state', ''))
            || $request->filled('error') || !$request->filled('code')) {
            return redirect()->route('account.login')
                ->withErrors(['google' => 'Google oturum açma isteği iptal edildi, geçersiz veya süresi doldu.']);
        }

        try {
            $identity = $google->exchangeAndVerify(
                $setting, (string) $request->query('code'),
                (string) $pending['verifier'], (string) $pending['nonce']
            );
        } catch (\Throwable $exception) {
            return redirect()->route('account.login')
                ->withErrors(['google' => $exception->getMessage()]);
        }

        // Only Google's stable subject identifier can authenticate an existing
        // linked user. An email match is NEVER sufficient to merge accounts.
        $user = User::query()->where('google_sub', $identity['sub'])->first();
        if (!$user) {
            $existingEmail = User::query()->whereRaw('LOWER(email) = ?', [$identity['email']])->exists();
            if ($existingEmail) {
                return redirect()->route('account.login')
                    ->withErrors(['google' => 'Bu e-posta ile daha önce hesap oluşturulmuş. Güvenli hesap eşleştirme henüz açık değil. Şifrenle giriş yapabilirsin.']);
            }
            try {
                $user = DB::transaction(function () use ($identity) {
                    return User::query()->create([
                        'name' => $identity['name'],
                        'email' => $identity['email'],
                        // Unique random password prevents password-based login
                        // to a Google account until a verified recovery is built.
                        'password' => Str::random(80),
                        'google_sub' => $identity['sub'],
                        'email_verified_at' => now(),
                    ]);
                });
            } catch (QueryException $e) {
                // Concurrent callbacks may race on the email or google_sub
                // unique indices. Never guess whose record to log into.
                return redirect()->route('account.login')
                    ->withErrors(['google' => 'Hesap oluşturma tamamlanamadı. Yeniden giriş yapın veya mevcut hesabınızla devam edin.']);
            }
        }

        if ($user->is_admin) {
            return redirect()->route('account.login')
                ->withErrors(['google' => 'Yönetici hesapları için müşteri Google girişi kullanılamaz.']);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return $accounts->afterAuthentication($request, (array) ($pending['intent'] ?? []));
    }
}