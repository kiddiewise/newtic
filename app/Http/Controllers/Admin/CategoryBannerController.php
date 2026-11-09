<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CategoryBannerController extends Controller
{
    public function store(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, true);

        if ($category->banners()->count() >= 3) {
            throw ValidationException::withMessages([
                'desktop_image' => 'Her kategori için en fazla 3 banner ekleyebilirsiniz.',
            ]);
        }

        $data['desktop_image_path'] = 'storage/'.$request->file('desktop_image')->store('category-banners', 'public');
        if ($request->hasFile('mobile_image')) {
            $data['mobile_image_path'] = 'storage/'.$request->file('mobile_image')->store('category-banners', 'public');
        }
        unset($data['desktop_image'], $data['mobile_image']);
        $data['is_active'] = $request->boolean('is_active');
        $category->banners()->create($data);

        return back()->with('success', 'Kategori bannerı eklendi.');
    }

    public function update(Request $request, Category $category, CategoryBanner $banner): RedirectResponse
    {
        $this->assertOwnership($category, $banner);
        $data = $this->validated($request, false);
        $oldImages = [$banner->desktop_image_path, $banner->mobile_image_path];
        if ($request->hasFile('desktop_image')) {
            $data['desktop_image_path'] = 'storage/'.$request->file('desktop_image')->store('category-banners', 'public');
        }
        if ($request->hasFile('mobile_image')) {
            $data['mobile_image_path'] = 'storage/'.$request->file('mobile_image')->store('category-banners', 'public');
        }
        unset($data['desktop_image'], $data['mobile_image']);
        $data['is_active'] = $request->boolean('is_active');
        $banner->update($data);

        foreach ($oldImages as $old) {
            if ($old && !in_array($old, [$banner->desktop_image_path, $banner->mobile_image_path], true)) {
                $this->deleteUploadedImage($old);
            }
        }

        return back()->with('success', 'Kategori bannerı güncellendi.');
    }

    public function destroy(Category $category, CategoryBanner $banner): RedirectResponse
    {
        $this->assertOwnership($category, $banner);
        $paths = [$banner->desktop_image_path, $banner->mobile_image_path];
        $banner->delete();
        foreach ($paths as $path) {
            $this->deleteUploadedImage($path);
        }

        return back()->with('success', 'Banner silindi.');
    }

    private function assertOwnership(Category $category, CategoryBanner $banner): void
    {
        abort_unless($banner->category_id === $category->id, 404);
    }

    private function deleteUploadedImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage/category-banners/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        $linkValidator = function ($attribute, $value, $fail): void {
            if (!is_string($value) || !str_starts_with($value, '/') || str_starts_with($value, '//')
                || preg_match('/[\\\\\x00-\x1F\x7F]/', $value)) {
                $fail('Banner bağlantısı /kategori/... gibi güvenli bir site içi adres olmalıdır.');
            }
        };

        return $request->validate([
            'desktop_image' => array_merge(
                [$creating ? 'required' : 'nullable'],
                ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=5000,max_height=3500']
            ),
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=3500,max_height=3500'],
            'alt_text' => ['nullable', 'string', 'max:190'],
            'link_url' => ['nullable', 'string', 'max:255', $linkValidator],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
