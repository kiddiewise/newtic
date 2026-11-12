<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $metrics = [
            'orders' => Order::query()->count(),
            'unpaid' => Order::query()->where('status', 'pending_payment')->count(),
            'preparing' => Order::query()->where('status', 'preparing')->count(),
            'pending_notifications' => PaymentNotification::query()->where('status', 'pending')->count(),
            'products' => Product::query()->count(),
            'low_stock' => Product::query()->where('is_active', true)->where('tracks_stock', true)
                ->where('stock_quantity', '<=', 10)->count(),
            'verified_revenue_cents' => (int) Order::query()
                ->where('is_test', false)
                ->whereHas('paymentNotifications', fn ($q) => $q->where('status', 'verified'))
                ->sum('total_cents'),
        ];

        $recentOrders = Order::query()
            ->withCount(['paymentNotifications as pending_claims_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest('id')->limit(7)->get();

        $lowStockProducts = Product::query()->where('is_active', true)
            ->where('tracks_stock', true)
            ->where('stock_quantity', '<=', 10)
            ->orderBy('stock_quantity')->orderBy('id')->limit(5)->get();

        // Portable across MySQL and SQLite; only bank-verified *real* orders are revenue.
        $start = now()->startOfDay()->subDays(6);
        $revenueByDay = Order::query()->where('is_test', false)
            ->where('placed_at', '>=', $start)
            ->whereHas('paymentNotifications', fn ($q) => $q->where('status', 'verified'))
            ->selectRaw('DATE(placed_at) as day, SUM(total_cents) as revenue')
            ->groupByRaw('DATE(placed_at)')->pluck('revenue', 'day');

        $orderCountByDay = Order::query()->where('placed_at', '>=', $start)
            ->selectRaw('DATE(placed_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(placed_at)')->pluck('total', 'day');

        $trend = collect(range(0, 6))->map(function (int $index) use ($start, $revenueByDay, $orderCountByDay) {
            $date = $start->copy()->addDays($index);

            return [
                'label' => $date->translatedFormat('D'),
                'date' => $date->format('d.m'),
                'revenue' => (int) ($revenueByDay[$date->toDateString()] ?? 0),
                'orders' => (int) ($orderCountByDay[$date->toDateString()] ?? 0),
            ];
        });

        $chartMax = max(1, (int) $trend->max('revenue'));

        $statusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        $totalStatus = max(1, (int) $statusCounts->sum());

        return view('admin.dashboard', compact(
            'metrics', 'recentOrders', 'lowStockProducts', 'trend',
            'chartMax', 'statusCounts', 'totalStatus'
        ));
    }
}
