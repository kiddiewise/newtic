<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * A PAYTR order is a fixed-price, stock-reserved purchase, not an editable cart.
 *
 * The original cart is saved separately for a safe retry if PAYTR reports a
 * verified failure. New shopping must use a fresh cart; otherwise the same
 * stock would be reserved twice when a second checkout is placed.
 *
 * Also migrates sessions created before this rule was introduced, where the
 * original pending cart was still left inside "shopping_cart".
 */
final class PendingPaytrCart
{
    public static function prepare(Request $request): ?Order
    {
        $session = $request->session();
        $id = (int) $session->get('checkout_last_order', 0);
        $savedCart = $session->get('paytr_pending_cart');

        if ($id < 1 || !is_array($savedCart)
            || !in_array($id, array_map('intval', (array) $session->get('confirmed_order_ids', [])), true)) {
            return null;
        }

        $order = Order::query()->find($id);
        if (!$order || $order->payment_method !== 'paytr') {
            return null;
        }

        $currentCart = ShoppingCart::raw($request);
        // A detached cart can legitimately contain exactly the same items
        // again. Equality with the saved order is NOT proof of ownership.
        $isDetached = (int) $session->get('paytr_detached_order_id', 0) === $id;

        if ($order->status === 'pending_payment') {
            // Migrate legacy sessions only once, before stock normalization.
            if (!$isDetached && $savedCart !== [] && $currentCart === $savedCart) {
                ShoppingCart::clear($request);
                $session->put('paytr_detached_order_id', $id);
            }

            return $order;
        }

        if ($order->status === 'cancelled'
            && $order->paytrAttempts()->where('status', 'failed')->exists()) {
            // A signed PAYTR failure releases the reserved stock. Restore the
            // saved items only if the customer did not already start a new cart.
            // snapshot() will validate current stock and options afterward.
            if ($currentCart === [] && $savedCart !== []) {
                ShoppingCart::put($request, $savedCart);
                $session->forget(['paytr_pending_cart', 'paytr_detached_order_id']);
            }

            return null;
        }

        if (in_array($order->status, ['preparing', 'shipped', 'out_for_delivery', 'completed'], true)) {
            // The paid order owns only its persisted order lines, never a cart
            // opened later, even if its items are coincidentally identical.
            if (!$isDetached && $savedCart !== [] && $currentCart === $savedCart) {
                ShoppingCart::clear($request);
            }
            $session->forget(['paytr_pending_cart', 'paytr_detached_order_id']);
        }

        return null;
    }
}
