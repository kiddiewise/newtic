<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class BlogCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.categories', [
            'categories' => BlogCategory::query()->withCount('posts')
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:blog_categories,slug'],
            'description' => ['nullable', 'string', 'max:1500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $slug = $data['slug'] ?? Str::slug($data['name']);
        if ($slug === '') $slug = 'kategori';
        if (empty($data['slug'])) {
            $base = mb_substr($slug, 0, 160);
            $slug = $base;
            for ($suffix = 2; BlogCategory::where('slug', $slug)->exists(); $suffix++) {
                $slug = $base.'-'.$suffix;
            }
        }
        BlogCategory::create([
            'name' => $data['name'], 'slug' => $slug,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Blog kategorisi oluşturuldu.');
    }

    public function update(Request $request, BlogCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        $category->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Kategori güncellendi.');
    }

    public function destroy(BlogCategory $category): RedirectResponse
    {
        $category->delete(); // Posts remain as uncategorized articles.
        return back()->with('success', 'Kategori silindi; yazılar korundu.');
    }
}
