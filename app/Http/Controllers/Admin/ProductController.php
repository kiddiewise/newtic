<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\CategoryHierarchy;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);

        $status = (string) $request->query('status', 'all');
        abort_unless(in_array($status, ['all', 'active', 'inactive', 'low_stock'], true), 422);

        $products = Product::query()->with('category')->withCount('images')
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($status === 'low_stock', fn ($query) => $query->where('is_active', true)->where('stock_quantity', '<=', 10))
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $safe = addcslashes($term, '%_\\');
                $query->where('name', 'like', '%'.$safe.'%')->orWhere('sku', 'like', '%'.$safe.'%');
            }))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.products.index', compact('products', 'term', 'status'));
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(),
            'categories' => collect(CategoryHierarchy::choices()),
        ]);
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'options', 'importSource']);

        return view('admin.products.form', [
            'product' => $product,
            'categories' => collect(CategoryHierarchy::choices()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $values = $this->optionValues($data);
        $data = $this->toProductAttributes($data, $request);
        $data['slug'] = UniqueSlug::make(Product::class, $data['name']);
        $product = DB::transaction(function () use ($data, $values) {
            $product = Product::create($data);
            $this->syncOptions($product, $values);

            return $product;
        });
        $this->addPhotos($product, $request);

        return redirect()->route('admin.products.edit', $product)->with('success', 'Ürün oluşturuldu.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $values = $this->optionValues($data);
        $data = $this->toProductAttributes($data, $request);
        DB::transaction(function () use ($product, $data, $values) {
            $product->update($data);
            $this->syncOptions($product, $values);
        });
        $this->addPhotos($product, $request);

        return back()->with('success', 'Ürün güncellendi.');
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
        $skuRule = Rule::unique('products', 'sku');
        if ($product && $product->exists) {
            $skuRule->ignore($product->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['required', 'string', 'max:100', $skuRule],
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'short_description' => ['nullable','string','max:10000'],
            'weight_kg' => ['nullable','numeric','min:0','max:9999999','decimal:0,3'],
            'seo_title' => ['nullable','string','max:255'],
            'seo_description' => ['nullable','string','max:2000'],
            'regular_price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'lt:regular_price'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['sometimes', 'boolean'],
            'option_label' => ['nullable', 'string', 'max:80'],
            'options_text' => ['nullable', 'string', 'max:3000'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);
    }

    private function toProductAttributes(array $data, Request $request): array
    {
        $regular = number_format((float) $data['regular_price'], 2, '.', '');
        $sale = !empty($data['sale_price'])
            ? number_format((float) $data['sale_price'], 2, '.', '')
            : null;

        unset($data['images'], $data['regular_price'], $data['sale_price'], $data['options_text']);
        $data['price'] = $sale ?? $regular;
        $data['compare_at_price'] = $sale ? $regular : null;
        $data['is_active'] = $request->boolean('is_active');
        $data['is_demo'] = false;

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
        if (!$images) {
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
            if (!$product->image_path) {
                $product->update(['image_path' => $path]);
            }
        }
    }
}
