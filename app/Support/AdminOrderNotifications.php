<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

final class AdminOrderNotifications
{
    public static function count(User $user): int
    {
        if (!$user->is_admin) return 0;

        // The inbox contains new actionable orders, not completed/cancelled
        // historical purchases. Each admin owns a separate durable cursor.
        return Order::query()->where('id', '>', (int)$user->admin_orders_seen_through)
            ->whereIn('status', ['pending_payment','preparing'])->count();
    }

    public static function markInboxSeen(User $user): void
    {
        if (!$user->is_admin) return;
        $through = (int)Order::query()->max('id');
        // Monotonic update protects against concurrent tabs moving backwards.
        User::query()->whereKey($user->id)->where('admin_orders_seen_through','<',$through)
            ->update(['admin_orders_seen_through'=>$through]);
        $user->refresh();
    }
}
