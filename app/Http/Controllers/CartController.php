<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ShoppingCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = ShoppingCart::snapshot($request);

        return view('store.cart', compact('cart'));
    }

    public function add(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $requested = [];
        foreach ($validated['items'] as $key => $quantity) {
            if (! ctype_digit((string) $key) || (int) $key <= 0) {
                throw ValidationException::withMessages(['items' => 'Geçersiz ürün seçimi.']);
            }
            if ((int) $quantity > 0) {
                $requested[(int) $key] = (int) $quantity;
            }
        }

        if ($requested === []) {
            throw ValidationException::withMessages(['items' => 'Sepete eklemek için en az bir adet seçin.']);
        }

        // Normalize stale items first; all changes are written only after full validation.
        ShoppingCart::snapshot($request);
        $existing = ShoppingCart::raw($request);
        $products = Product::query()->whereIn('id', array_keys($requested))
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get()->keyBy('id');

        foreach ($requested as $id => $qty) {
            $product = $products->get($id);
            if (! $product) {
                throw ValidationException::withMessages(['items' => 'Seçilen ürünlerden biri satışta değil.']);
            }
            if ($qty + (int) ($existing[$id] ?? 0) > min(999, $product->stock_quantity)) {
                throw ValidationException::withMessages([
                    'items' => $product->name.' için stok miktarı yeterli değil.',
                ]);
            }
        }

        foreach ($requested as $id => $qty) {
            $existing[$id] = (int) ($existing[$id] ?? 0) + $qty;
        }
        if (count($existing) > 100) {
            throw ValidationException::withMessages(['items' => 'Sepette en fazla 100 farklı ürün bulunabilir.']);
        }

        ShoppingCart::put($request, $existing);

        return redirect()->route('cart.index')->with('success', 'Ürünler sepetinize eklendi.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);
        ShoppingCart::snapshot($request);
        $items = ShoppingCart::raw($request);

        if (! array_key_exists($product->id, $items)) {
            return redirect()->route('cart.index')->with('error', 'Ürün sepetinizde bulunamadı.');
        }

        $qty = (int) $data['quantity'];
        if ($qty === 0) {
            unset($items[$product->id]);
        } else {
            if (! $product->is_active || ! $product->category?->is_active || $qty > $product->stock_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Seçilen adet stokta bulunmuyor.']);
            }
            $items[$product->id] = $qty;
        }

        ShoppingCart::put($request, $items);

        return redirect()->route('cart.index')->with('success', 'Sepet güncellendi.');
    }

    public function remove(Request $request, Product $product): RedirectResponse
    {
        $items = ShoppingCart::raw($request);
        unset($items[$product->id]);
        ShoppingCart::put($request, $items);

        return redirect()->route('cart.index')->with('success', 'Ürün sepetten çıkarıldı.');
    }

    public function clear(Request $request): RedirectResponse
    {
        ShoppingCart::clear($request);

        return redirect()->route('cart.index')->with('success', 'Sepet boşaltıldı.');
    }
}
