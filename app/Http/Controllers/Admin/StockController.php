<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filter = (string) $request->query('filter', 'low');
        abort_unless(in_array($filter, ['all', 'low', 'empty'], true), 422);
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);

        $products = Product::query()->with('category')
            ->when($filter === 'low', fn ($q) => $q->where('is_active', true)
                ->where('tracks_stock', true)->where('stock_quantity', '<=', 10))
            ->when($filter === 'empty', fn ($q) => $q->where('is_active', true)
                ->where('tracks_stock', true)->where('stock_quantity', 0))
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term) {
                $safe = '%'.addcslashes($term, '%_\\').'%';
                $q->where('name', 'like', $safe)->orWhere('sku', 'like', $safe);
            }))->orderBy('stock_quantity')->orderBy('id')->paginate(20)->withQueryString();

        return view('admin.stock', compact('products', 'filter', 'term'));
    }
}
