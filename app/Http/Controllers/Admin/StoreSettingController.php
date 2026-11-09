<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreSettingController extends Controller
{
    public function edit(): View
    {
        $settings = StoreSetting::current();

        return view('admin.store-settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'free_shipping_threshold' => ['required', 'numeric', 'decimal:0,2', 'gte:0', 'max:9999999999.99'],
            'shipping_fee' => ['required', 'numeric', 'decimal:0,2', 'gte:0', 'max:9999999999.99'],
        ]);

        StoreSetting::current()->update($validated);

        return redirect()->route('admin.store-settings.edit')->with('success', 'Kargo ayarları kaydedildi.');
    }
}
