<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);
        $orders = collect();
        $products = collect();

        if (mb_strlen($term) >= 2) {
            $needle = '%'.addcslashes($term, '%_\\').'%';
            $orders = Order::query()
                ->where(function ($q) use ($needle) {
                    $q->where('order_number', 'like', $needle)
                        ->orWhere('customer_name', 'like', $needle)
                        ->orWhere('customer_email', 'like', $needle);
                })
                ->latest('id')->limit(15)->get();
            $products = Product::query()->with('category')
                ->where(fn ($q) => $q->where('name', 'like', $needle)->orWhere('sku', 'like', $needle))
                ->latest('id')->limit(15)->get();
        }

        return view('admin.search', compact('term', 'orders', 'products'));
    }
}
