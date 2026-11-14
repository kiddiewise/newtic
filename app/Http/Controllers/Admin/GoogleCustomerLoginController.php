<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleLoginSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GoogleCustomerLoginController extends Controller
{
    public function edit(): View
    {
        return view('admin.google-customer-login', [
            'settings' => GoogleLoginSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = GoogleLoginSetting::current();
        $values = $request->validate([
            'client_id' => ['required', 'string', 'max:255',
                'regex:/^[A-Za-z0-9._-]+\.apps\.googleusercontent\.com$/D'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'enabled' => ['required', 'boolean'],
        ]);

        if (!$settings->configured() && blank($values['client_secret'] ?? null)) {
            return back()->withErrors(['client_secret' => 'İlk kurulumda Google Client Secret gereklidir.'])
                ->withInput($request->only('client_id', 'enabled'));
        }
        if ($settings->client_id && $settings->client_id !== $values['client_id']
            && blank($values['client_secret'] ?? null)) {
            return back()->withErrors(['client_secret' => 'Yeni Client ID ile yeni Client Secret da girilmelidir.'])
                ->withInput($request->only('client_id', 'enabled'));
        }

        $settings->client_id = $values['client_id'];
        if (filled($values['client_secret'] ?? null)) {
            $settings->client_secret = $values['client_secret'];
        }
        $settings->enabled = (bool) $values['enabled'];
        $settings->save();

        return back()->with('success', 'Google müşteri giriş ayarları kaydedildi. Google bağlantısını canlı olarak denemek için OAuth istemcisinin domain izinlerini de yapılandırın.');
    }
}