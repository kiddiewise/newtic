<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use App\Models\HomeSlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class HomeSlideController extends Controller
{
    public function store(Request $request, HomeSection $section): RedirectResponse
    {
        abort_unless(in_array($section->type, ['slider', 'banner'], true), 404);

        $data = $request->validate([
            'desktop_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'link_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $desktop = $request->file('desktop_image')->store('banners', 'public');
        $mobile = $request->hasFile('mobile_image')
            ? $request->file('mobile_image')->store('banners', 'public') : null;

        $section->slides()->create([
            'desktop_image_path' => 'storage/'.$desktop,
            'mobile_image_path' => $mobile ? 'storage/'.$mobile : null,
            'link_url' => $data['link_url'] ?? null,
            'sort_order' => (int) ($section->slides()->max('sort_order') ?? -1) + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Banner eklendi.');
    }

    public function update(Request $request, HomeSection $section, HomeSlide $slide): RedirectResponse
    {
        $this->assertOwnership($section, $slide);

        $data = $request->validate([
            'desktop_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'link_url' => ['nullable', 'url:http,https', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $values = [
            'link_url' => $data['link_url'] ?? null,
            'sort_order' => $data['sort_order'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
        foreach (['desktop_image', 'mobile_image'] as $key) {
            if ($request->hasFile($key)) {
                $path = $request->file($key)->store('banners', 'public');
                $values[$key.'_path'] = 'storage/'.$path;
            }
        }

        $slide->update($values);

        return back()->with('success', 'Banner güncellendi.');
    }

    public function destroy(HomeSection $section, HomeSlide $slide): RedirectResponse
    {
        $this->assertOwnership($section, $slide);
        $slide->delete();

        return back()->with('success', 'Banner silindi.');
    }

    private function assertOwnership(HomeSection $section, HomeSlide $slide): void
    {
        abort_unless($section->id === $slide->home_section_id &&
            in_array($section->type, ['slider', 'banner'], true), 404);
    }
}
