<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MerchantLegalPage;
use App\Support\LegalReadiness;
use Illuminate\Validation\ValidationException;
use App\Models\Product;
use App\Models\MarketingContact;
use App\Services\MarketingConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function loginPage(Request $request): View|RedirectResponse
    {
        if ($request->user()) return redirect()->route('account.index');
        return view('store.account-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required','email','max:255'],
            'password' => ['required','string'],
            'next' => ['nullable','in:checkout,wishlist,cart,review'],
            'review_product' => ['nullable','string','max:200'],
            'favorite' => ['nullable','integer','min:1'],
        ]);
        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return back()->withErrors(['email' => 'E-posta veya şifre hatalı.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return $this->afterAuthentication($request, $data);
    }

    public function registerPage(Request $request): View|RedirectResponse
    {
        if ($request->user()) return redirect()->route('account.index');
        return view('store.account-register');
    }

    public function register(Request $request): RedirectResponse
    {
        $membership = MerchantLegalPage::published('uyelik-sozlesmesi');
        $data = $request->validate([
            'membership_accepted' => $membership ? ['required', 'accepted'] : ['nullable', 'boolean'],
            'membership_revision' => $membership ? ['required','string', 'size:64'] : ['nullable', 'string'],
            'name' => ['required','string','min:3','max:160'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','confirmed',Password::min(8)],
            'next' => ['nullable','in:checkout,wishlist,cart,review'],
            'review_product' => ['nullable','string','max:200'],
            'favorite' => ['nullable','integer','min:1'],
            'marketing_opt_in'=>['nullable','boolean'],
        ]);
        if ($membership && !hash_equals($membership->revisionHash(), (string) $data['membership_revision'])) {
            throw ValidationException::withMessages([
                'membership_accepted' => 'Üyelik koşulları güncellendi. Sayfayı yeniden açıp okuyun.',
            ]);
        }
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            'legal_notice' => $membership ? LegalReadiness::membershipNotice() : null,
        ]);
        if ($request->boolean('marketing_opt_in')) {
            try {
                app(MarketingConsentService::class)->request($user->email,'customer_registration',$user);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Marketing preference capture unavailable', [
                    'exception'=>class_basename($e),
                ]);
            }
        }
        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterAuthentication($request, $data);
    }

    /**
     * Keep the customer's requested destination after sign-in or sign-up.
     * Favoriting while logged out adds the requested product only after auth.
     */
    public function afterAuthentication(Request $request, array $data): RedirectResponse
    {
        $next = $data['next'] ?? '';

        if ($next === 'review' && isset($data['review_product'])) {
            $product = Product::query()->where('slug', $data['review_product'])->where('is_active', true)->first();
            if ($product) {
                return redirect()->route('product.show', $product->slug)->withFragment('urun-yorumlari');
            }
        }

        if ($next === 'cart' && isset($data['favorite'])) {
            $added = WishlistController::addForUser($request->user(), (int) $data['favorite']);

            return redirect()->route('cart.index')->with(
                $added ? 'success' : 'error',
                $added ? 'Ürün favorilere eklendi.' : 'Ürün favorilere eklenemedi.'
            );
        }

        return redirect()->route(match ($next) {
            'checkout' => 'checkout.index',
            'wishlist' => 'wishlist.index',
            'cart' => 'cart.index',
            default => 'account.index',
        });
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->latest()->get();
        $orders = $user->orders()->with('shippingShipment')->latest()->limit(15)->get();

        $marketingContact=MarketingContact::query()->where('email',mb_strtolower($user->email))->first();
        return view('store.account', compact('user','addresses','orders','marketingContact'));
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()?->is_admin) \App\Services\AdminWebPush::revokeSession($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
