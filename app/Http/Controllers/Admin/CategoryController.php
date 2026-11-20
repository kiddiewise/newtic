<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CategoryHierarchy;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $choices = CategoryHierarchy::choices();
        $categories = Category::query()->withCount(['products', 'children', 'banners'])->get()->keyBy('id');

        // Count additional category associations without double-counting products
        // whose primary category is also listed in category_product.
        $additionalCounts = DB::table('category_product')
            ->join('products', 'products.id', '=', 'category_product.product_id')
            ->whereColumn('products.primary_category_id', '!=', 'category_product.category_id')
            ->select('category_product.category_id')
            ->selectRaw('COUNT(*) as linked_count')
            ->groupBy('category_product.category_id')
            ->pluck('linked_count', 'category_id');

        return view('admin.categories.index', compact('categories', 'choices', 'additionalCounts'));
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


    /**
     * Keep products and the category tree intact; delete only an empty leaf.
     * Existing banner/category files are cleared after the database commit.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $paths = DB::transaction(function () use ($category): array {
            $locked = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if ($locked->children()->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'Bu kategorinin alt kategorileri var. Önce alt kategorileri taşıyın veya silin.',
                ]);
            }
            if ($locked->products()->exists()
                || DB::table('category_product')->where('category_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'Bu kategoriye bağlı ürünler var. Silmeden önce ürünleri başka kategoriye taşıyın.',
                ]);
            }

            $paths = $this->deletionPaths($locked);
            $locked->delete();

            return $paths;
        }, 3);

        $this->deleteUploadedPaths($paths);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori silindi.');
    }


    /**
     * Move primary-category products and any secondary-category memberships
     * in one transaction. Never delete products or touch their order history.
     */
    public function transfer(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'target_category_id' => ['required', 'integer', 'exists:categories,id'],
            'operation' => ['required', 'in:move,move_and_delete'],
        ]);
        $targetId = (int) $data['target_category_id'];
        $sourceId = (int) $category->id;

        if ($sourceId === $targetId) {
            throw ValidationException::withMessages([
                'target_category_id' => 'Kaynak ve hedef kategori aynı olamaz.',
            ]);
        }

        $outcome = DB::transaction(function () use ($category, $sourceId, $targetId, $data): array {
            // Lock in deterministic ID order to avoid opposite-direction deadlocks.
            $locked = Category::query()->whereIn('id', [$sourceId, $targetId])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $source = $locked->get($sourceId);
            $target = $locked->get($targetId);
            if (! $source || ! $target) {
                throw ValidationException::withMessages([
                    'target_category_id' => 'Kategori bulunamadı. Sayfayı yenileyip tekrar deneyin.',
                ]);
            }

            $removeSource = $data['operation'] === 'move_and_delete';
            if ($removeSource && $source->children()->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'Bu kategorinin alt kategorileri bulunuyor. Ürünleri taşıyabilirsiniz ancak kategoriyi silmeden önce alt kategorileri taşımalı veya silmelisiniz.',
                ]);
            }

            $primaryCount = $source->products()->count();
            $secondaryCount = DB::table('category_product')
                ->join('products', 'products.id', '=', 'category_product.product_id')
                ->where('category_product.category_id', $sourceId)
                ->where('products.primary_category_id', '!=', $sourceId)
                ->count();

            if ($primaryCount + $secondaryCount === 0) {
                throw ValidationException::withMessages([
                    'delete' => 'Bu kategoride taşınacak ürün yok. Boş kategoriyi Sil seçeneğiyle kaldırabilirsiniz.',
                ]);
            }

            // Keep extra category memberships from being lost. If destination
            // membership already exists, preserve its single unique pivot row.
            DB::table('category_product')->where('category_id', $sourceId)
                ->orderBy('product_id')->chunk(500, function ($rows) use ($targetId): void {
                    DB::table('category_product')->insertOrIgnore(
                        $rows->map(fn ($row) => [
                            'category_id' => $targetId,
                            'product_id' => $row->product_id,
                        ])->all()
                    );
                });
            DB::table('category_product')->where('category_id', $sourceId)->delete();
            $source->products()->update(['primary_category_id' => $targetId]);

            $paths = [];
            if ($removeSource) {
                $paths = $this->deletionPaths($source);
                $source->delete();
            }

            return [
                'count' => $primaryCount + $secondaryCount,
                'deleted' => $removeSource,
                'target' => $target->name,
                'paths' => $paths,
            ];
        }, 3);

        if ($outcome['deleted']) {
            $this->deleteUploadedPaths($outcome['paths']);
        }

        return redirect()->route('admin.categories.index')->with(
            'success',
            $outcome['count'].' ürün "'.$outcome['target'].'" kategorisine taşındı.'
                .($outcome['deleted'] ? ' Kaynak kategori silindi.' : ' Kaynak kategori korundu.')
        );
    }

    /** Only delete this category's own uploaded images after commit succeeds. */
    private function deletionPaths(Category $category): array
    {
        $paths = collect([$category->image_path]);
        foreach ($category->banners as $banner) {
            $paths->push($banner->desktop_image_path, $banner->mobile_image_path);
        }

        return $paths->filter(fn ($path) => is_string($path)
            && (str_starts_with($path, 'storage/categories/')
                || str_starts_with($path, 'storage/category-banners/')))
            ->unique()->values()->all();
    }

    private function deleteUploadedPaths(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }
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
