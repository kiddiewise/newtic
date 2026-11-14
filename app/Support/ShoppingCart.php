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
     * Legacy carts keep numeric product IDs. Optional selections use product:option
     * so one gram/SKU may be bought with several different hook thicknesses.
     */
    public static function key(int $productId, ?int $optionId = null): string
    {
        return $optionId ? $productId.':'.$optionId : (string) $productId;
    }

    public static function parseKey(int|string $key): ?array
    {
        if (preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/D', (string) $key, $match)) {
            return [(int) $match[1], (int) $match[2]];
        }
        if (preg_match('/^[1-9][0-9]*$/D', (string) $key)) {
            return [(int) $key, null];
        }

        return null;
    }

    /** A server-authoritative cart snapshot: user input never controls prices. */
    public static function snapshot(Request $request): array
    {
        $raw = $request->session()->get(self::SESSION_KEY, []);
        $raw = is_array($raw) ? array_slice($raw, 0, self::MAX_LINES, true) : [];
        $ids = collect(array_keys($raw))
            ->map(fn ($key) => self::parseKey($key))
            ->filter()->map(fn ($parts) => $parts[0])->unique()->values()->all();

        $products = Product::query()->with(['category', 'options'])->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get()->keyBy('id');

        $categories = CategoryHierarchy::all();
        $normalized = [];
        $lines = [];
        $subtotalCents = 0;
        $quantityCount = 0;
        $savingsCents = 0;
        $usedStock = [];

        foreach ($raw as $rawKey => $rawQuantity) {
            $parsed = self::parseKey($rawKey);
            $product = $parsed ? $products->get($parsed[0]) : null;
            if (! $product || ! CategoryHierarchy::visible($product->category, $categories)
                || ! is_numeric($rawQuantity)
                || (int) $rawQuantity != $rawQuantity || (int) $rawQuantity < 1) {
                continue;
            }

            $optionId = $parsed[1];
            $option = $optionId ? $product->options->firstWhere('id', $optionId) : null;
            if (($product->options->isNotEmpty() && ! $option)
                || ($product->options->isEmpty() && $optionId !== null)) {
                continue;
            }

            $taken = (int) ($usedStock[$product->id] ?? 0);
            $quantity = min(
                (int) $rawQuantity,
                max(0, (int) $product->stock_quantity - $taken),
                self::MAX_QUANTITY - $taken
            );
            if ($quantity < 1) {
                continue;
            }

            $usedStock[$product->id] = $taken + $quantity;
            $unitCents = (int) round((float) $product->price * 100);
            $regularCents = max($unitCents, (int) round((float) ($product->compare_at_price ?? $product->price) * 100));
            $lineCents = $unitCents * $quantity;
            $lineSavings = ($regularCents - $unitCents) * $quantity;
            $key = self::key($product->id, $option?->id);
            $normalized[$key] = $quantity;

            $lines[] = [
                'key' => $key,
                'product' => $product,
                'option_id' => $option?->id,
                'option_label' => $option ? $product->option_label : null,
                'option_value' => $option?->value,
                'quantity' => $quantity,
                'unit_cents' => $unitCents,
                'line_cents' => $lineCents,
                'regular_cents' => $regularCents,
                'savings_cents' => $lineSavings,
            ];
            $subtotalCents += $lineCents;
            $savingsCents += $lineSavings;
            $quantityCount += $quantity;
        }

        foreach ($lines as &$line) {
            $product = $line['product'];
            $line['max_quantity'] = max(1, min(self::MAX_QUANTITY,
                (int) $product->stock_quantity
                - ($usedStock[$product->id] - $line['quantity'])));
        }
        unset($line);

        if ($raw !== $normalized) {
            self::put($request, $normalized);
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
            'savings_cents' => $savingsCents,
            'regular_subtotal_cents' => $subtotalCents + $savingsCents,
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
        $request->session()->put('product_picker_reset', true);
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put('product_picker_reset', true);
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
            array_slice($items, 0, self::MAX_LINES)
        ));
    }
}
