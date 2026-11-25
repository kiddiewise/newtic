<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductBadge;
use App\Support\CategoryHierarchy;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);

        $status = (string) $request->query('status', 'all');
        abort_unless(in_array($status, ['all', 'active', 'inactive', 'low_stock'], true), 422);

        $category = $request->query('category', '');
        abort_unless(is_scalar($category) && ($category === '' || (ctype_digit((string)$category) && (int)$category > 0)), 422);
        $categoryId = $category === '' ? null : (int)$category;
        $sort = (string)$request->query('sort', 'newest');
        abort_unless(in_array($sort, ['newest','oldest','name','price_asc','price_desc','stock_asc','views_desc'], true), 422);
        $products = Product::query()->with('category')->withCount('images')
            ->when($categoryId !== null, fn ($query) => $query->where('primary_category_id',$categoryId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($status === 'low_stock', fn ($query) => $query->where('is_active', true)
                ->where('tracks_stock', true)->where('stock_quantity', '<=', 10))
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $safe = addcslashes($term, '%_\\');
                $query->where('name', 'like', '%'.$safe.'%')->orWhere('sku', 'like', '%'.$safe.'%');
            }))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'price_asc', fn ($query) => $query->orderBy('price'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('price'))
            ->when($sort === 'stock_asc', fn ($query) => $query->orderByDesc('tracks_stock')->orderBy('stock_quantity'))
            ->when($sort === 'views_desc', fn ($query) => $query->orderByDesc('view_count'))
            ->orderBy('id',$sort === 'oldest' ? 'asc' : 'desc')->paginate(20)->withQueryString();

        $categories = collect(CategoryHierarchy::choices());

        return view('admin.products.index', compact('products', 'term', 'status', 'categories', 'categoryId', 'sort'));
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product,
            'categories' => collect(CategoryHierarchy::choices()),
            'badges' => ProductBadge::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'options', 'importSource', 'badges']);

        return view('admin.products.form', [
            'product' => $product,
            'categories' => collect(CategoryHierarchy::choices()),
            'badges' => ProductBadge::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $values = $this->optionValues($data);
        $badgeIds = $this->badgeIds($data);
        $data = $this->toProductAttributes($data, $request);
        $data['slug'] = UniqueSlug::make(Product::class, $data['name']);
        $product = DB::transaction(function () use ($data, $values, $badgeIds) {
            $product = Product::create($data);
            $this->syncOptions($product, $values);
            $product->badges()->sync($badgeIds);

            return $product;
        });
        $this->addPhotos($product, $request);

        return redirect()->route('admin.products.edit', $product)->with('success', 'Ürün oluşturuldu.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $values = $this->optionValues($data);
        $badgeIds = $this->badgeIds($data);
        $data = $this->toProductAttributes($data, $request);
        DB::transaction(function () use ($product, $data, $values, $badgeIds) {
            $product->update($data);
            $this->syncOptions($product, $values);
            $product->badges()->sync($badgeIds);
        });
        $this->addPhotos($product, $request);

        return back()->with('success', 'Ürün güncellendi.');
    }


    /**
     * Save exactly one quick-edit field at a time. This intentionally bypasses
     * the full product form (photos, badges, stock tracking, variants, SEO)
     * so autosave never overwrites unrelated product data.
     */
    public function quickUpdate(Request $request, Product $product): JsonResponse
    {
        $field = $request->validate([
            'field' => ['required', Rule::in([
                'name', 'primary_category_id', 'regular_price', 'sale_price', 'stock_quantity',
            ])],
        ])['field'];

        $rules = match ($field) {
            'name' => ['required', 'string', 'max:190'],
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
            'regular_price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
        };
        $value = $request->validate(['value' => $rules])['value'] ?? null;

        $product = DB::transaction(function () use ($product, $field, $value): Product {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($field === 'name') {
                $locked->name = trim((string) $value);
            } elseif ($field === 'primary_category_id') {
                $locked->primary_category_id = (int) $value;
            } elseif ($field === 'stock_quantity') {
                // Do not implicitly enable/disable stock tracking.
                $locked->stock_quantity = (int) $value;
            } else {
                // The normal selling price is compared to the optional sale
                // price, using the same representation as the full editor.
                $regular = (string) ($locked->compare_at_price ?: $locked->price);
                $sale = $locked->compare_at_price ? (string) $locked->price : null;
                if ($field === 'regular_price') {
                    $regular = (string) $value;
                } else {
                    $sale = $value === null || $value === '' ? null : (string) $value;
                }

                $regularCents = (int) round((float) $regular * 100);
                $saleCents = $sale !== null ? (int) round((float) $sale * 100) : null;
                if ($saleCents !== null && $saleCents >= $regularCents) {
                    throw ValidationException::withMessages([
                        'value' => 'İndirimli fiyat normal fiyattan düşük olmalıdır. Önce indirimi kaldırabilir veya normal fiyatı yükseltebilirsiniz.',
                    ]);
                }

                $locked->price = number_format((float) ($sale ?? $regular), 2, '.', '');
                $locked->compare_at_price = $sale !== null
                    ? number_format((float) $regular, 2, '.', '')
                    : null;
            }

            $locked->save();

            return $locked->load('category');
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Değişiklik kaydedildi.',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'primary_category_id' => $product->primary_category_id,
                'category_name' => $product->category?->name ?? 'Kategori yok',
                'regular_price' => (string) ($product->compare_at_price ?: $product->price),
                'sale_price' => $product->compare_at_price ? (string) $product->price : null,
                'price' => (string) $product->price,
                'discount_percent' => $product->discount_percent,
                'stock_quantity' => $product->stock_quantity,
                'tracks_stock' => $product->tracks_stock,
            ],
        ]);
    }

    /**
     * Remove an unsold SKU without changing existing order records. Sold
     * products must instead be unpublished to preserve purchase history.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $paths = DB::transaction(function () use ($product): array {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if (DB::table('order_items')->where('product_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'Sipariş geçmişinde bulunan ürün silinemez. Bunun yerine ürünü pasife alın.',
                ]);
            }

            $paths = $locked->images()->pluck('path')->push($locked->image_path)
                ->filter(fn ($path) => is_string($path) && str_starts_with($path, 'storage/products/'))
                ->unique()->values()->all();
            $locked->delete();

            return $paths;
        }, 3);

        foreach ($paths as $path) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }

        return redirect()->route('admin.products.index')->with('success', 'Ürün silindi.');
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $path = $image->path;
        $image->delete();

        $next = $product->images()->first();
        if ($product->image_path === $path) {
            $product->update(['image_path' => $next?->path]);
        }
        if (str_starts_with($path, 'storage/')) {
            Storage::disk('public')->delete(substr($path, 8));
        }

        return back()->with('success', 'Ürün fotoğrafı silindi.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        if (! $request->has('tracks_stock')) {
            $request->merge(['tracks_stock' => true]);
        }
        $skuRule = Rule::unique('products', 'sku');
        if ($product && $product->exists) {
            $skuRule->ignore($product->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['required', 'string', 'max:100', $skuRule],
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'short_description' => ['nullable', 'string', 'max:10000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,3'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:2000'],
            'seo_focus_keyword' => ['nullable', 'string', 'max:255'],
            'seo_noindex' => ['sometimes', 'boolean'],
            'google_brand' => ['nullable', 'string', 'max:120'],
            'gtin' => ['nullable', 'string', 'regex:/^[0-9]{8,14}$/D'],
            'mpn' => ['nullable', 'string', 'max:70'],
            'regular_price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'lt:regular_price'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'tracks_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'option_label' => ['nullable', 'string', 'max:80'],
            'options_text' => ['nullable', 'string', 'max:3000'],
            'requires_customer_photos' => ['sometimes', 'boolean'],
            'badge_ids' => ['sometimes', 'array', 'max:31'],
            'badge_ids.*' => ['nullable', 'integer', 'distinct', Rule::exists('product_badges', 'id')],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);
    }

    private function toProductAttributes(array $data, Request $request): array
    {
        $regular = number_format((float) $data['regular_price'], 2, '.', '');
        $sale = ! empty($data['sale_price'])
            ? number_format((float) $data['sale_price'], 2, '.', '')
            : null;

        unset($data['images'], $data['regular_price'], $data['sale_price'], $data['options_text'], $data['badge_ids']);
        $data['price'] = $sale ?? $regular;
        $data['compare_at_price'] = $sale ? $regular : null;
        $data['is_active'] = $request->boolean('is_active');
        $data['tracks_stock'] = $request->boolean('tracks_stock');
        if (! $data['tracks_stock']) {
            $data['stock_quantity'] = max(999, (int) $data['stock_quantity']);
        }
        $data['is_demo'] = false;
        $data['requires_customer_photos'] = $request->boolean('requires_customer_photos');
        $data['seo_noindex'] = $request->boolean('seo_noindex');

        return $data;
    }

    /**
     * One optional choice group per independent SKU. Values are entered one per
     * line so comma decimals such as "0,25" are never split incorrectly.
     */
    private function optionValues(array &$data): array
    {
        $values = collect(preg_split('/\r\n|\n|\r/', (string) ($data['options_text'] ?? '')))
            ->map(fn ($value) => trim($value))->filter(fn ($value) => $value !== '')->values();
        if ($values->count() > 30 || $values->contains(fn ($value) => mb_strlen($value) > 60)) {
            throw ValidationException::withMessages([
                'options_text' => 'En fazla 30 seçenek tanımlayın; her seçenek 60 karakteri geçemez.',
            ]);
        }
        if ($values->map(fn ($value) => mb_strtolower($value))->unique()->count() !== $values->count()) {
            throw ValidationException::withMessages(['options_text' => 'Aynı seçenek birden fazla yazılamaz.']);
        }
        if ($values->isNotEmpty() && empty(trim((string) ($data['option_label'] ?? '')))) {
            throw ValidationException::withMessages(['option_label' => 'Seçenek başlığı girin (örneğin İğne kalınlığı).']);
        }
        $data['option_label'] = $values->isNotEmpty() ? trim($data['option_label']) : null;

        return $values->all();
    }

    private function badgeIds(array $data): array
    {
        return collect($data['badge_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function syncOptions(Product $product, array $values): void
    {
        // Preserve IDs for unchanged values: carts and selections refer to them.
        foreach ($values as $value) {
            $product->options()->firstOrCreate(['value' => $value]);
        }
        $product->options()->whereNotIn('value', $values)->delete();
    }

    private function addPhotos(Product $product, Request $request): void
    {
        $images = $request->file('images', []);
        if (! $images) {
            return;
        }

        $currentCount = $product->images()->count();
        if ($currentCount + count($images) > 20) {
            abort(422, 'Bir ürüne en fazla 20 fotoğraf ekleyebilirsiniz.');
        }

        $order = ((int) $product->images()->max('sort_order')) + 1;
        foreach ($images as $image) {
            $path = 'storage/'.$image->store('products', 'public');
            $product->images()->create(['path' => $path, 'sort_order' => $order++]);
            if (! $product->image_path) {
                $product->update(['image_path' => $path]);
            }
        }
    }
}
