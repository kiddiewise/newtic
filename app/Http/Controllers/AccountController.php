<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
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
        $data = $request->validate([
            'name' => ['required','string','min:3','max:160'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','confirmed',Password::min(8)],
            'next' => ['nullable','in:checkout,wishlist,cart,review'],
            'review_product' => ['nullable','string','max:200'],
            'favorite' => ['nullable','integer','min:1'],
        ]);
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterAuthentication($request, $data);
    }

    /**
     * Keep the customer's requested destination after sign-in or sign-up.
     * Favoriting while logged out adds the requested product only after auth.
     */
    private function afterAuthentication(Request $request, array $data): RedirectResponse
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

        return view('store.account', compact('user','addresses','orders'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
