<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopAppearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function edit(): View
    {
        return view('admin.appearance', ['appearance' => ShopAppearance::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'desktop_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,avif', 'max:5120', 'dimensions:max_width=4000,max_height=2000'],
            'mobile_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,avif', 'max:5120', 'dimensions:max_width=2500,max_height=2500'],
            'remove_desktop_logo' => ['nullable', 'boolean'],
            'remove_mobile_logo' => ['nullable', 'boolean'],
        ]);
        $appearance = ShopAppearance::current();
        $old = [];
        $new = [];
        foreach (['desktop', 'mobile'] as $device) {
            $field = $device.'_logo_path';
            $file = $device.'_logo';
            if ($request->hasFile($file)) {
                $old[] = $appearance->$field;
                $new[$field] = 'storage/'.$request->file($file)->store('branding', 'public');
            } elseif ($request->boolean('remove_'.$device.'_logo')) {
                $old[] = $appearance->$field;
                $new[$field] = null;
            }
        }
        if ($new !== []) {
            $appearance->update($new);
            foreach ($old as $path) {
                if ($path && str_starts_with($path, 'storage/')) {
                    Storage::disk('public')->delete(substr($path, 8));
                }
            }
        }

        return back()->with('success', 'Masaüstü ve mobil logo ayarları kaydedildi.');
    }
}
