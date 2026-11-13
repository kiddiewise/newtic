<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SeoSetting;
use App\Support\SeoMetadata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SeoSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $setting = SeoSetting::current();
        $stats = [
            'products' => Product::query()->where('is_active', true)->where('is_demo', false)->count(),
            'product_no_image' => Product::query()->where('is_active', true)->where('is_demo', false)
                ->where(fn ($q) => $q->whereNull('image_path')->orWhere('image_path', ''))->count(),
            'product_no_description' => Product::query()->where('is_active', true)->where('is_demo', false)
                ->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))->count(),
            'product_no_title' => Product::query()->where('is_active', true)->where('is_demo', false)
                ->where(fn ($q) => $q->whereNull('seo_title')->orWhere('seo_title', ''))->count(),
            'categories' => Category::query()->where('is_active', true)->count(),
            'category_no_title' => Category::query()->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('seo_title')->orWhere('seo_title', ''))->count(),
        ];

        return response()->view('admin.seo-settings', [
            'setting' => $setting,
            'stats' => $stats,
            'isStaging' => SeoMetadata::staging($request),
            'activeOrigin' => $request->getSchemeAndHttpHost(),
            'pages' => SeoSetting::OTHER_PAGES,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'site_name' => ['required', 'string', 'max:120'],
            'title_suffix' => ['required', 'string', 'max:100'],
            'home_title' => ['nullable', 'string', 'max:190'],
            'home_description' => ['nullable', 'string', 'max:500'],
            'default_description' => ['nullable', 'string', 'max:500'],
            'google_verification' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'noindex' => ['required', 'boolean'],
            'nofollow' => ['required', 'boolean'],
            'social_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096',
                'dimensions:max_width=4000,max_height=4000'],
            'remove_social_image' => ['sometimes', 'boolean'],
            'pages' => ['sometimes', 'array:'.implode(',', array_keys(SeoSetting::OTHER_PAGES))],
        ];
        foreach (SeoSetting::OTHER_PAGES as $name => $_) {
            $rules['pages.'.$name] = ['sometimes', 'array:title,description'];
            $rules['pages.'.$name.'.title'] = ['nullable', 'string', 'max:190'];
            $rules['pages.'.$name.'.description'] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);
        $setting = SeoSetting::query()->firstOrCreate(['id' => 1]);
        $pageMetadata = [];
        foreach (SeoSetting::OTHER_PAGES as $key => $_) {
            $pageMetadata[$key] = [
                'title' => trim((string) data_get($data, 'pages.'.$key.'.title', '')),
                'description' => trim((string) data_get($data, 'pages.'.$key.'.description', '')),
            ];
        }
        $values = collect($data)->only([
            'site_name', 'title_suffix', 'home_title', 'home_description',
            'default_description', 'google_verification', 'noindex', 'nofollow',
        ])->all();
        $values['page_metadata'] = $pageMetadata;

        $previousImage = $setting->social_image_path;
        $newImage = null;
        if ($request->hasFile('social_image')) {
            $newImage = 'storage/'.$request->file('social_image')->store('seo', 'public');
            $values['social_image_path'] = $newImage;
        } elseif ($request->boolean('remove_social_image')) {
            $values['social_image_path'] = null;
        }

        try {
            $setting->update($values);
        } catch (\Throwable $error) {
            if ($newImage) Storage::disk('public')->delete(substr($newImage, 8));
            throw $error;
        }
        if ($previousImage && $previousImage !== $setting->social_image_path
            && str_starts_with($previousImage, 'storage/seo/')) {
            Storage::disk('public')->delete(substr($previousImage, 8));
        }

        return redirect()->route('admin.seo.edit')->with('success',
            'SEO ayarları kaydedildi. Geçici Hostinger alan adlarında noindex/nofollow güvenlik kuralı zorunlu kalır.');
    }
}