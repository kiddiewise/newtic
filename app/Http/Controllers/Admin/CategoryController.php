<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category()]);
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $image = $request->file('image');
        if ($image) {
            $data['image_path'] = 'storage/'.$image->store('categories', 'public');
        }
        unset($data['image']);
        $data['slug'] = UniqueSlug::make(Category::class, $data['name']);
        $data['is_active'] = $request->boolean('is_active');

        $category = Category::create($data);

        return redirect()->route('admin.categories.edit', $category)->with('success', 'Kategori oluşturuldu.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request);
        $oldImage = $category->image_path;
        if ($request->hasFile('image')) {
            $data['image_path'] = 'storage/'.$request->file('image')->store('categories', 'public');
        }
        unset($data['image']);
        $data['is_active'] = $request->boolean('is_active');
        $category->update($data);

        if ($oldImage && $oldImage !== $category->image_path && str_starts_with($oldImage, 'storage/')) {
            Storage::disk('public')->delete(substr($oldImage, 8));
        }

        return back()->with('success', 'Kategori güncellendi.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096', 'dimensions:max_width=4000,max_height=4000'],
        ]);
    }
}
