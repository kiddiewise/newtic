<?php

namespace App\Http\Controllers;

use App\Models\GoogleLoginSetting;
use App\Models\User;
use App\Models\MerchantLegalPage;
use App\Support\LegalReadiness;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
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
            // New Google accounts must review current membership terms before
            // account creation. Existing linked accounts can sign in normally.
            if (MerchantLegalPage::published('uyelik-sozlesmesi')) {
                $request->session()->put('google_customer_registration_review', [
                    'identity' => $identity,
                    'intent' => $pending['intent'] ?? [],
                    'issued_at' => now()->timestamp,
                ]);

                return redirect()->route('account.google.review');
            }
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

    public function review(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('google_customer_registration_review');
        $page = MerchantLegalPage::published('uyelik-sozlesmesi');
        if (!is_array($pending) || !$page ||
            now()->timestamp - (int) ($pending['issued_at'] ?? 0) > 600) {
            return redirect()->route('account.login')
                ->withErrors(['google' => 'Google üyelik oturumu geçersiz veya süresi dolmuş. Yeniden deneyin.']);
        }

        return view('store.google-membership-review', ['page' => $page]);
    }

    public function confirmRegistration(Request $request, AccountController $accounts): RedirectResponse
    {
        $pending = $request->session()->get('google_customer_registration_review');
        $page = MerchantLegalPage::published('uyelik-sozlesmesi');
        if (!is_array($pending) || !$page ||
            now()->timestamp - (int) ($pending['issued_at'] ?? 0) > 600
            || !is_array($pending['identity'] ?? null)) {
            return redirect()->route('account.login')
                ->withErrors(['google' => 'Google üyelik oturumu geçersiz veya süresi dolmuş. Yeniden deneyin.']);
        }

        $validated = $request->validate([
            'membership_accepted' => ['required', 'accepted'],
            'membership_revision' => ['required', 'string', 'size:64'],
        ]);
        if (!hash_equals($page->revisionHash(), $validated['membership_revision'])) {
            throw ValidationException::withMessages([
                'membership_accepted' => 'Üyelik koşulları değişti. Lütfen yeni metni inceleyin.',
            ]);
        }

        $identity = $pending['identity'];
        if (!is_string($identity['sub'] ?? null) || !is_string($identity['email'] ?? null)
            || !is_string($identity['name'] ?? null)
            || User::query()->where('google_sub', $identity['sub'])->exists()
            || User::query()->whereRaw('LOWER(email) = ?', [$identity['email']])->exists()) {
            return redirect()->route('account.login')
                ->withErrors(['google' => 'Bu hesap zaten var veya Google kimlik bilgileri geçersiz. Yeniden giriş yapın.']);
        }

        try {
            $user = DB::transaction(function () use ($identity) {
                return User::query()->create([
                    'name' => $identity['name'],
                    'email' => $identity['email'],
                    'password' => Str::random(80),
                    'google_sub' => $identity['sub'],
                    'email_verified_at' => now(),
                    'legal_notice' => LegalReadiness::membershipNotice(),
                ]);
            });
        } catch (QueryException) {
            return redirect()->route('account.login')
                ->withErrors(['google' => 'Üyelik tamamlanamadı. Yeniden giriş yapın.']);
        }

        $request->session()->forget('google_customer_registration_review');
        Auth::login($user);
        $request->session()->regenerate();

        return $accounts->afterAuthentication($request, (array) ($pending['intent'] ?? []));
    }

}