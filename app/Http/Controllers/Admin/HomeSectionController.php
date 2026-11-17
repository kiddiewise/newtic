<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomeSectionController extends Controller
{
    public function index(): View
    {
        $sections = HomeSection::query()->orderBy('sort_order')->orderBy('id')
            ->withCount(['slides', 'categories', 'products'])->get();

        return view('admin.index', compact('sections'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([
                HomeSection::TYPE_SLIDER, HomeSection::TYPE_CATEGORIES,
                HomeSection::TYPE_PRODUCTS, HomeSection::TYPE_BANNER,
            ])],
        ]);
        $section = HomeSection::create([
            'type' => $data['type'],
            'title' => match ($data['type']) {
                'slider' => 'Ana slider',
                'categories' => 'Kategoriler',
                'products' => 'Popüler Ürünler',
                default => 'Kampanya bannerları',
            },
            'is_active' => true,
            'sort_order' => (HomeSection::max('sort_order') ?? 0) + 1,
            'settings' => $data['type'] === 'slider' ? ['interval_ms' => 5500] : [],
        ]);

        return redirect()->route('admin.sections.edit', $section)->with('success', 'Bölüm oluşturuldu.');
    }

    public function edit(HomeSection $section): View
    {
        $section->load(['slides', 'categories', 'products']);
        $categories = Category::query()->orderBy('name')->get();
        $products = Product::query()->orderBy('name')->get();

        return view('admin.section', compact('section', 'categories', 'products'));
    }

    public function update(Request $request, HomeSection $section): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'interval_ms' => ['nullable', 'integer', 'min:2500', 'max:15000'],
            'countdown_label' => ['nullable', 'string', 'max:100'],
            'countdown_ends_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);
        $section->update([
            'title' => $data['title'] ?? null,
            'sort_order' => $data['sort_order'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'settings' => $section->type === HomeSection::TYPE_SLIDER
                ? ['interval_ms' => $data['interval_ms'] ?? 5500]
                : ($section->type === HomeSection::TYPE_PRODUCTS
                    ? array_merge($section->settings ?? [], [
                        'countdown_label' => trim($data['countdown_label'] ?? ''),
                        'countdown_ends_at' => $data['countdown_ends_at'] ?? null,
                    ])
                    : ($section->settings ?? [])),
        ]);

        return back()->with('success', 'Bölüm ayarları kaydedildi.');
    }

    public function move(Request $request, HomeSection $section): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        // Lock and normalize the complete sequence so duplicate/manual sort values
        // never cause a move to skip a block or affect unrelated storefront data.
        $moved = DB::transaction(function () use ($section, $data): bool {
            $sections = HomeSection::query()
                ->orderBy('sort_order')->orderBy('id')
                ->lockForUpdate()->get();

            $current = $sections->search(fn (HomeSection $item) => $item->id === $section->id);
            abort_if($current === false, 404);

            $neighbor = $current + ($data['direction'] === 'up' ? -1 : 1);
            if ($neighbor < 0 || $neighbor >= $sections->count()) {
                return false;
            }

            [$sections[$current], $sections[$neighbor]] = [$sections[$neighbor], $sections[$current]];

            foreach ($sections as $position => $item) {
                if ($item->sort_order !== $position) {
                    $item->update(['sort_order' => $position]);
                }
            }

            return true;
        });

        return redirect()->route('admin.index')
            ->with('success', $moved ? 'Blok sırası otomatik kaydedildi.' : 'Blok zaten listenin sınırında.');
    }

    public function destroy(HomeSection $section): RedirectResponse
    {
        // Foreign keys cascade the block's slides and selections, not its products/categories.
        $section->delete();

        return redirect()->route('admin.index')->with('success', 'Vitrin bloğu silindi.');
    }

    public function selection(Request $request, HomeSection $section): RedirectResponse
    {
        abort_unless(in_array($section->type, ['categories', 'products'], true), 404);

        $data = $request->validate([
            'selected' => ['nullable', 'array', 'max:500'],
            'selected.*' => ['integer', 'distinct'],
            'position' => ['nullable', 'array'],
            'position.*' => ['integer', 'min:0', 'max:10000'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['selected'] ?? [])));
        $model = $section->type === 'categories' ? Category::class : Product::class;
        $allowedIds = $model::query()->where('is_active', true)->whereIn('id', $ids)->pluck('id');
        abort_if($allowedIds->count() !== count($ids), 422, 'Geçersiz veya pasif seçim.');

        $positions = $data['position'] ?? [];
        $sync = [];
        foreach ($allowedIds as $index => $id) {
            $sync[$id] = ['sort_order' => (int) ($positions[$id] ?? $index)];
        }

        if ($section->type === 'categories') {
            $section->categories()->sync($sync);
        } else {
            $section->products()->sync($sync);
        }

        return back()->with('success', 'Vitrin seçimleri güncellendi.');
    }
}
