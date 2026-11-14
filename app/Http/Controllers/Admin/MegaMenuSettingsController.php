<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MegaMenuSetting;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MegaMenuSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.menus.mega-menu', ['settings' => MegaMenuSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,avif', 'max:5120',
                'dimensions:min_width=500,min_height=200,max_width=4200,max_height=2800'],
            'remove_hero_image' => ['nullable', 'boolean'],
            'instagram_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'youtube_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'facebook_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'x_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'whatsapp_number' => ['nullable', 'string', 'max:25',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    $digits = preg_replace('/\D+/', '', $value);
                    if (! preg_match('/^\+?[0-9\s().-]+$/', $value) || strlen($digits) < 10 || strlen($digits) > 15) {
                        $fail('Geçerli bir WhatsApp telefon numarası girin (ör. +90 5XX XXX XX XX).');
                    }
                }],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'footer_text' => ['required', 'string', 'max:120'],
        ]);

        $settings = MegaMenuSetting::current();
        $oldImage = $settings->hero_image_path;
        $fields = collect($validated)->only([
            'instagram_url', 'youtube_url', 'facebook_url', 'x_url',
            'whatsapp_number', 'whatsapp_message', 'footer_text',
        ])->all();

        // All optional fields must also be cleared when their inputs are empty.
        foreach (['instagram_url', 'youtube_url', 'facebook_url', 'x_url', 'whatsapp_number', 'whatsapp_message'] as $field) {
            $fields[$field] = $validated[$field] ?? null;
        }

        if ($request->hasFile('hero_image')) {
            $fields['hero_image_path'] = 'storage/'.$request->file('hero_image')->store('mega-menu', 'public');
        } elseif ($request->boolean('remove_hero_image')) {
            $fields['hero_image_path'] = null;
        }

        $settings->update($fields);

        if (array_key_exists('hero_image_path', $fields) && $oldImage && $oldImage !== $fields['hero_image_path']
            && str_starts_with($oldImage, 'storage/')) {
            Storage::disk('public')->delete(substr($oldImage, 8));
        }

        return redirect()->route('admin.menus.mega-menu.edit')->with('success', 'Mega menü ayarları kaydedildi.');
    }
}
