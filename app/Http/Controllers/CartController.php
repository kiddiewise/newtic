<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ShoppingCart;
use App\Support\QuickCartSheet;
use App\Support\CategoryHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = ShoppingCart::snapshot($request);

        $wishlistIds = WishlistController::ids($request);

        return view('store.cart', compact('cart', 'wishlistIds'));
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'integer', 'min:0', 'max:999'],
            'options' => ['sometimes', 'array', 'max:100'],
            'options.*' => ['nullable', 'integer', 'min:1'],
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
        $products = Product::query()->with('options')->whereIn('id', array_keys($requested))
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get()->keyBy('id');

        $keys = [];
        foreach ($requested as $id => $qty) {
            $product = $products->get($id);
            if (! $product || ! CategoryHierarchy::visible($product->category)) {
                throw ValidationException::withMessages(['items' => 'Seçilen ürünlerden biri satışta değil.']);
            }

            $optionId = (int) ($validated['options'][$id] ?? 0);
            $option = $optionId ? $product->options->firstWhere('id', $optionId) : null;
            if (($product->options->isNotEmpty() && ! $option)
                || ($product->options->isEmpty() && $optionId)) {
                throw ValidationException::withMessages([
                    'options.'.$id => $product->name.' için geçerli '.($product->option_label ?: 'ürün').' seçeneğini belirleyin.',
                ]);
            }

            $key = ShoppingCart::key($id, $option?->id);
            $keys[$id] = $key;
            $inCart = 0;
            foreach ($existing as $storedKey => $storedQty) {
                $parts = ShoppingCart::parseKey($storedKey);
                if ($parts && $parts[0] === $id) {
                    $inCart += (int) $storedQty;
                }
            }
            if ($qty + $inCart > min(999, $product->stock_quantity)) {
                throw ValidationException::withMessages([
                    'items' => $product->name.' için toplam stok miktarı yeterli değil.',
                ]);
            }
        }

        foreach ($requested as $id => $qty) {
            $key = $keys[$id];
            $existing[$key] = (int) ($existing[$key] ?? 0) + $qty;
        }
        if (count($existing) > 100) {
            throw ValidationException::withMessages(['items' => 'Sepette en fazla 100 farklı ürün bulunabilir.']);
        }

        ShoppingCart::put($request, $existing);

        if ($request->expectsJson()) {
            $first = $products->get(array_key_first($requested));
            return $this->quickResponse($request, $first);
        }

        // Preserve the shopping page for normal browser form submissions.
        // Requests without a same-origin Referrer (e.g. APIs/tests) fall back to cart.
        $referrer = $request->headers->get('referer');
        $origin = $request->getSchemeAndHttpHost();

        if (is_string($referrer) && (
            $referrer === $origin || str_starts_with($referrer, $origin.'/')
        )) {
            return redirect()->to($referrer)->with('success', 'Sepete eklendi.');
        }

        return redirect()->route('cart.index')->with('success', 'Sepete eklendi.');
    }

    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'option_id' => ['nullable', 'integer', 'min:1'],
        ]);
        ShoppingCart::snapshot($request);
        $items = ShoppingCart::raw($request);
        $key = ShoppingCart::key($product->id, isset($data['option_id']) ? (int) $data['option_id'] : null);

        if (! array_key_exists($key, $items)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Ürün sepetinizde bulunamadı. Sepeti yenileyin.'], 409);
            }
            return redirect()->route('cart.index')->with('error', 'Ürün sepetinizde bulunamadı.');
        }

        $qty = (int) $data['quantity'];
        if ($qty === 0) {
            unset($items[$key]);
        } else {
            $otherQty = 0;
            foreach ($items as $storedKey => $storedQty) {
                $parts = ShoppingCart::parseKey($storedKey);
                if ((string) $storedKey !== (string) $key && $parts && $parts[0] === $product->id) {
                    $otherQty += (int) $storedQty;
                }
            }
            if (! $product->is_active || ! $product->category?->is_active
                || ($qty + $otherQty) > min(999, $product->stock_quantity)) {
                throw ValidationException::withMessages(['quantity' => 'Seçilen adet stokta bulunmuyor.']);
            }
            $items[$key] = $qty;
        }

        ShoppingCart::put($request, $items);

        if ($request->expectsJson()) {
            return $this->quickResponse($request, $product);
        }

        return redirect()->route('cart.index')->with('success', 'Sepet güncellendi.');
    }

    public function remove(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['option_id' => ['nullable', 'integer', 'min:1']]);
        $items = ShoppingCart::raw($request);
        $key = ShoppingCart::key($product->id, isset($data['option_id']) ? (int) $data['option_id'] : null);
        unset($items[$key]);
        ShoppingCart::put($request, $items);

        return redirect()->route('cart.index')->with('success', 'Ürün sepetten çıkarıldı.');
    }

    public function clear(Request $request): RedirectResponse
    {
        ShoppingCart::clear($request);

        return redirect()->route('cart.index')->with('success', 'Sepet boşaltıldı.');
    }
    /**
     * The quick sheet is an opt-in JSON enhancement of the existing cart routes.
     * A submitted price, stock or total is never accepted as authoritative.
     */
    private function quickResponse(Request $request, Product $fallback): JsonResponse
    {
        $requestedAnchor = $request->input('quick_anchor_id');
        $anchor = $fallback;

        if ($requestedAnchor !== null && ctype_digit((string) $requestedAnchor)) {
            $candidate = Product::query()->with('category')->whereKey((int) $requestedAnchor)
                ->where('is_active', true)->first();
            if ($candidate && $candidate->category?->is_active
                && CategoryHierarchy::visible($candidate->category)) {
                $anchor = $candidate;
            }
        }

        return response()->json(QuickCartSheet::response($request, $anchor));
    }
}
