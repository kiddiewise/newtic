<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopAppearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class AuthVisualController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'images' => ['sometimes', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif',
                'max:4096', 'dimensions:max_width=4000,max_height=5000'],
            'remove' => ['sometimes', 'array', 'max:5'],
            'remove.*' => ['required', 'integer', 'min:0', 'max:4', 'distinct'],
        ]);

        $appearance = ShopAppearance::current();
        $old = array_values((array) ($appearance->auth_image_paths ?? []));
        $remove = array_map('intval', $data['remove'] ?? []);
        $keep = array_values(array_filter($old, fn ($path, $index) =>
            !in_array($index, $remove, true), ARRAY_FILTER_USE_BOTH));
        $uploads = $request->file('images', []);
        if (count($keep) + count($uploads) > 5) {
            throw ValidationException::withMessages([
                'images' => 'Giriş/kayıt sayfasında toplam en fazla 5 görsel kullanılabilir.',
            ]);
        }
        if (!$uploads && !$remove) {
            return back()->with('success', 'Görsellerde değişiklik yapılmadı.');
        }

        $new = [];
        try {
            foreach ($uploads as $upload) {
                $new[] = 'storage/'.$upload->store('auth', 'public');
            }
            $appearance->update(['auth_image_paths' => array_merge($keep, $new)]);
        } catch (\Throwable $exception) {
            foreach ($new as $path) Storage::disk('public')->delete(substr($path, 8));
            throw $exception;
        }
        foreach ($remove as $index) {
            $path = $old[$index] ?? null;
            if ($path && str_starts_with($path, 'storage/auth/')) {
                Storage::disk('public')->delete(substr($path, 8));
            }
        }

        return back()->with('success', 'Giriş/kayıt görselleri kaydedildi. Yeni görseller otomatik geçişte kullanılır.');
    }
}