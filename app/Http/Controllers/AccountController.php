<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'next' => ['nullable','in:checkout'],
        ]);
        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return back()->withErrors(['email' => 'E-posta veya şifre hatalı.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->route(($data['next'] ?? '') === 'checkout' ? 'checkout.index' : 'account.index');
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
            'next' => ['nullable','in:checkout'],
        ]);
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route(($data['next'] ?? '') === 'checkout' ? 'checkout.index' : 'account.index');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->latest()->get();
        $orders = $user->orders()->latest()->limit(15)->get();

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
