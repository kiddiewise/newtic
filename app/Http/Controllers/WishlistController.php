<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public static function ids(Request $request): array
    {
        $raw = $request->session()->get('wishlist_product_ids', []);

        return array_values(array_unique(array_map(
            'intval',
            array_filter(
                is_array($raw) ? array_slice($raw, 0, 100) : [],
                fn ($id) => (is_int($id) || ctype_digit((string) $id)) && (int) $id > 0
            )
        )));
    }

    public function index(Request $request): View
    {
        $ids = self::ids($request);
        $products = Product::query()->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get();

        return view('store.wishlist', compact('products'));
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->category?->is_active, 404);

        $ids = self::ids($request);
        if (in_array($product->id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$product->id]));
            $message = 'Ürün favorilerden çıkarıldı.';
        } else {
            if (count($ids) >= 100) {
                return back()->with('error', 'En fazla 100 ürün favorilere eklenebilir.');
            }
            $ids[] = $product->id;
            $message = 'Ürün favorilere eklendi.';
        }

        $request->session()->put('wishlist_product_ids', $ids);

        return back()->with('success', $message);
    }
}
