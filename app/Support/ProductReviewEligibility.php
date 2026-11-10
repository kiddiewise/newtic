<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;

final class ProductReviewEligibility
{
    /**
     * Require an actual completed order owned by the signed-in account.
     * Email-only or guest checkouts never grant review permission.
     */
    public static function order(User $user, Product $product): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->orderBy('is_test')
            ->latest('id')
            ->first();
    }
}
