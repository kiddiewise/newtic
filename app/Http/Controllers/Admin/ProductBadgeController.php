<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductBadge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductBadgeController extends Controller
{
    public function index(): View
    {
        return view('admin.product-badges.index', [
            'badges' => ProductBadge::query()->withCount('products')
                ->orderBy('sort_order')->orderBy('id')->get(),
            'colors' => ProductBadge::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        ProductBadge::create($data);

        return redirect()->route('admin.product-badges.index')->with('success', 'Ürün rozeti oluşturuldu.');
    }

    public function update(Request $request, ProductBadge $badge): RedirectResponse
    {
        $badge->update($this->validated($request, $badge));

        return redirect()->route('admin.product-badges.index')->with('success', 'Ürün rozeti güncellendi.');
    }

    public function destroy(ProductBadge $badge): RedirectResponse
    {
        $badge->delete();

        return redirect()->route('admin.product-badges.index')->with('success', 'Rozet ve ürün eşleştirmeleri kaldırıldı.');
    }

    private function validated(Request $request, ?ProductBadge $badge = null): array
    {
        $unique = Rule::unique('product_badges', 'name');
        if ($badge) {
            $unique->ignore($badge->id);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', $unique],
            'color' => ['required', Rule::in(array_keys(ProductBadge::COLORS))],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
