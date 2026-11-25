<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class AdminOrderNotifications
{
    public static function unread(User $user): Builder
    {
        // Preserve previously acknowledged orders during migration from the inbox cursor.
        $query = Order::query()->where('orders.id', '>', (int)$user->admin_orders_seen_through)
            ->whereIn('orders.status', ['pending_payment','preparing'])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('admin_order_reads')
                ->whereColumn('admin_order_reads.order_id','orders.id')
                ->where('admin_order_reads.user_id',$user->id));
        return $user->is_admin ? $query : $query->whereRaw('1 = 0');
    }

    public static function count(User $user): int
    {
        return self::unread($user)->count();
    }

    public static function snapshot(User $user): array
    {
        $row = self::unread($user)->selectRaw('COUNT(*) AS unread_count, MAX(orders.id) AS latest_id')->first();
        return ['count'=>(int)$row->unread_count,'latest_id'=>(int)$row->latest_id];
    }

    public static function markOrderSeen(User $user, Order $order): void
    {
        if (!$user->is_admin || $order->id <= (int)$user->admin_orders_seen_through) return;
        DB::table('admin_order_reads')->insertOrIgnore([
            'user_id'=>$user->id,'order_id'=>$order->id,'read_at'=>now(),
        ]);
    }
}
