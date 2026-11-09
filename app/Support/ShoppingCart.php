<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\Request;

class ShoppingCart
{
    private const SESSION_KEY = 'shopping_cart';
    private const MAX_LINES = 100;
    private const MAX_QUANTITY = 999;

    /**
     * Read and sanitize the persisted session cart. Prices and stock are NEVER
     * read from request payloads or from stored sessions.
     */
    public static function snapshot(Request $request): array
    {
        $raw = $request->session()->get(self::SESSION_KEY, []);
        $raw = is_array($raw) ? array_slice($raw, 0, self::MAX_LINES, true) : [];
        $ids = array_values(array_filter(array_keys($raw), fn ($id) =>
            ctype_digit((string) $id) && (int) $id > 0
        ));

        $products = Product::query()->with('category')->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get()->keyBy('id');

        $normalized = [];
        $lines = [];
        $subtotalCents = 0;
        $quantityCount = 0;
        foreach ($raw as $id => $rawQuantity) {
            $product = ctype_digit((string) $id) ? $products->get((int) $id) : null;
            if (! $product || ! is_numeric($rawQuantity) || (int) $rawQuantity < 1) {
                continue;
            }
            $quantity = min((int) $rawQuantity, (int) $product->stock_quantity, self::MAX_QUANTITY);
            if ($quantity < 1) {
                continue;
            }

            $unitCents = (int) round((float) $product->price * 100);
            $lineCents = $unitCents * $quantity;
            $subtotalCents += $lineCents;
            $quantityCount += $quantity;
            $normalized[$product->id] = $quantity;
            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_cents' => $unitCents,
                'line_cents' => $lineCents,
            ];
        }

        if ($raw !== $normalized) {
            $request->session()->put(self::SESSION_KEY, $normalized);
        }

        $settings = StoreSetting::current();
        $thresholdCents = (int) round((float) $settings->free_shipping_threshold * 100);
        $feeCents = (int) round((float) $settings->shipping_fee * 100);
        $remainingCents = max(0, $thresholdCents - $subtotalCents);
        $shippingCents = $subtotalCents > 0 && $remainingCents > 0 ? $feeCents : 0;

        return [
            'lines' => $lines,
            'count' => $quantityCount,
            'subtotal_cents' => $subtotalCents,
            'shipping_cents' => $shippingCents,
            'total_cents' => $subtotalCents + $shippingCents,
            'threshold_cents' => $thresholdCents,
            'remaining_cents' => $remainingCents,
            'shipping_fee_cents' => $feeCents,
            'free_shipping' => $subtotalCents > 0 && $remainingCents === 0,
        ];
    }

    public static function raw(Request $request): array
    {
        $items = $request->session()->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    public static function put(Request $request, array $items): void
    {
        $request->session()->put(self::SESSION_KEY, $items);
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function itemCount(): int
    {
        $items = session(self::SESSION_KEY, []);

        if (! is_array($items)) {
            return 0;
        }

        return array_sum(array_map(
            fn ($quantity) => is_numeric($quantity)
                ? min(max(0, (int) $quantity), self::MAX_QUANTITY) : 0,
            array_slice($items, 0, self::MAX_LINES),
        ));
    }
}
