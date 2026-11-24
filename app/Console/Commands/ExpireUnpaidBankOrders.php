<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ExpireUnpaidBankOrders extends Command
{
    protected $signature = 'orders:expire-unpaid {--limit=50 : Maximum number of orders to inspect}';
    protected $description = 'Cancel old, unverified bank-transfer orders and restore reserved stock once.';

    public function handle(): int
    {
        $hours = (int) config('newtic.bank_transfer_expiry_hours', 48);
        $limit = max(1, min(200, (int) $this->option('limit')));
        $orders = Order::query()
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'pending_payment')
            ->where('placed_at', '<=', now()->subHours($hours))
            ->whereDoesntHave('paymentNotifications', fn ($q) =>
                $q->whereIn('status', ['pending', 'verified']))
            ->whereDoesntHave('returnRequests', fn ($q) => $q->whereIn('status', \App\Models\OrderReturnRequest::ACTIVE))
            ->orderBy('id')->limit($limit)->pluck('id');

        $cancelled = 0;
        foreach ($orders as $id) {
            DB::transaction(function () use ($id, $hours, &$cancelled): void {
                $order = Order::query()->whereKey($id)->lockForUpdate()->first();
                if (!$order || $order->payment_method !== 'bank_transfer'
                    || $order->status !== 'pending_payment'
                    || $order->placed_at?->gt(now()->subHours($hours))
                    || $order->paymentNotifications()->whereIn('status', ['pending', 'verified'])->exists()
                    || $order->returnRequests()->whereIn('status', \App\Models\OrderReturnRequest::ACTIVE)->exists()) {
                    return;
                }

                $quantities = $order->items()->whereNotNull('product_id')
                    ->selectRaw('product_id, SUM(quantity) AS qty')
                    ->groupBy('product_id')->pluck('qty', 'product_id');
                $products = Product::query()->whereIn('id', $quantities->keys())
                    ->where('tracks_stock', true)->orderBy('id')->lockForUpdate()->get();
                foreach ($products as $product) {
                    $product->increment('stock_quantity', (int) ($quantities[$product->id] ?? 0));
                }
                $order->update(['status' => 'cancelled']);
                OrderStatusChange::create([
                    'order_id' => $order->id, 'user_id' => null,
                    'from_status' => 'pending_payment', 'to_status' => 'cancelled',
                    'source' => 'automatic_bank_expiry',
                    'reason' => 'Havale/EFT ödemesi '. $hours .' saat içinde onaylanmadığı için otomatik iptal.',
                    'created_at' => now(),
                ]);
                $cancelled++;
            }, 3);
        }
        $this->info('Expired unpaid bank orders: '.$cancelled);
        return self::SUCCESS;
    }
}