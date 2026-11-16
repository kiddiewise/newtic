<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantFooterMark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class FooterMarkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(MerchantFooterMark::TYPES))],
            'title' => ['required', 'string', 'max:100'],
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048',
                'dimensions:max_width=2000,max_height=1200'],
            'verification_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'authorization_confirmed' => ['required', 'accepted'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ]);

        $type = $validated['type'];
        if (in_array($type, ['trust', 'app'], true) && blank($validated['verification_url'] ?? null)) {
            return back()->withErrors([
                'verification_url' => 'Güven işareti veya uygulama için doğrulanabilir resmi HTTPS bağlantısı zorunludur.',
            ])->withInput();
        }

        if (MerchantFooterMark::query()->count() >= 30) {
            return back()->withErrors(['image' => 'En fazla 30 footer görseli oluşturulabilir.']);
        }

        $path = 'storage/'.$request->file('image')->store('footer-marks', 'public');
        try {
            MerchantFooterMark::query()->create([
                'type' => $type,
                'title' => trim($validated['title']),
                'image_path' => $path,
                'verification_url' => $validated['verification_url'] ?? null,
                'use_authorized' => true,
                'is_active' => true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
            throw $exception;
        }

        return redirect()->route('admin.legal.index')->with('success', 'İzinli görsel footer alanına eklendi.');
    }

    public function destroy(MerchantFooterMark $footerMark): RedirectResponse
    {
        $path = $footerMark->image_path;
        $footerMark->delete();
        if (str_starts_with($path, 'storage/footer-marks/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }

        return redirect()->route('admin.legal.index')->with('success', 'Footer görseli kaldırıldı.');
    }
}
