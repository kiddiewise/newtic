<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;

class QuickCartSheet
{
    /**
     * A single source of truth for the drawer: all prices, stock and cart
     * quantities are loaded from the server, never from browser input.
     */
    public static function response(Request $request, Product $anchor): array
    {
        $cart = ShoppingCart::snapshot($request);
        $cartQuantities = collect($cart['lines'])->mapWithKeys(
            fn (array $line) => [$line['product']->id => (int) $line['quantity']]
        )->all();

        $all = CategoryHierarchy::all();
        $ancestors = CategoryHierarchy::ancestors($anchor->category, $all);
        $root = $ancestors[0] ?? $anchor->category;
        $visibleCategoryIds = collect(CategoryHierarchy::descendantIds($root, $all))
            ->filter(function ($id) use ($all) {
                $category = $all->firstWhere('id', $id);
                return $category && CategoryHierarchy::visible($category, $all);
            })->all();

        // Keep the drawer fast even with large catalogues; always include the
        // clicked item and then offer up to 32 related variants from the family.
        $related = Product::query()
            ->with('category')
            ->whereIn('primary_category_id', $visibleCategoryIds)
            ->where('is_active', true)
            ->where('id', '!=', $anchor->id)
            ->orderBy('name')
            ->limit(32)
            ->get();
        $products = collect([$anchor->loadMissing('category')])->concat($related);

        return [
            'ok' => true,
            'count' => $cart['count'],
            'subtotal_cents' => $cart['subtotal_cents'],
            'shipping_cents' => $cart['shipping_cents'],
            'remaining_cents' => $cart['remaining_cents'],
            'free_shipping' => $cart['free_shipping'],
            'threshold_cents' => $cart['threshold_cents'],
            'subtotal_label' => self::money($cart['subtotal_cents']),
            'remaining_label' => self::money($cart['remaining_cents']),
            'cart_url' => route('cart.index'),
            'html' => view('store.partials.quick-cart-products', [
                'anchor' => $anchor,
                'products' => $products,
                'cartQuantities' => $cartQuantities,
                'cart' => $cart,
                'root' => $root,
            ])->render(),
        ];
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' ₺';
    }
}
