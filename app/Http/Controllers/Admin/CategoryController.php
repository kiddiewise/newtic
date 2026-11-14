<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CategoryHierarchy;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $choices = CategoryHierarchy::choices();
        $categories = Category::query()->withCount(['products', 'children', 'banners'])->get()->keyBy('id');

        return view('admin.categories.index', compact('categories', 'choices'));
    }

    public function create(Request $request): View
    {
        $suggestedParentId = (int) $request->query('parent_id', 0);
        $choices = CategoryHierarchy::choices();

        return view('admin.categories.form', [
            'category' => new Category(),
            'choices' => $choices,
            'suggestedParentId' => $suggestedParentId,
        ]);
    }

    public function edit(Category $category): View
    {
        $category->load('banners');
        $choices = CategoryHierarchy::choices();

        return view('admin.categories.form', [
            'category' => $category,
            'choices' => $choices,
            'suggestedParentId' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = 'storage/'.$request->file('image')->store('categories', 'public');
        }
        unset($data['image']);
        $data['slug'] = UniqueSlug::make(Category::class, $data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['seo_noindex'] = $request->boolean('seo_noindex');

        $category = Category::create($data);

        return redirect()->route('admin.categories.edit', $category)->with('success', 'Kategori oluşturuldu.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $previousImage = $category->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = 'storage/'.$request->file('image')->store('categories', 'public');
        }
        unset($data['image']);
        $data['is_active'] = $request->boolean('is_active');
        $data['seo_noindex'] = $request->boolean('seo_noindex');
        $category->update($data);

        if ($previousImage && $previousImage !== $category->image_path && str_starts_with($previousImage, 'storage/')) {
            Storage::disk('public')->delete(substr($previousImage, 8));
        }

        return back()->with('success', 'Kategori güncellendi.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_noindex' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096', 'dimensions:max_width=4000,max_height=4000'],
        ]);

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        $data['parent_id'] = $parentId;

        if ($category && $parentId && in_array($parentId, CategoryHierarchy::descendantIds($category, null, false), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Bir kategori kendisine veya kendi alt kategorisine bağlanamaz.',
            ]);
        }

        return $data;
    }
}
