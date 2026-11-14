<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        abort_unless(in_array($days, [7, 30, 90], true), 422);
        $start = now()->startOfDay()->subDays($days - 1);
        $rangeOrders = Order::query()->where('placed_at', '>=', $start);

        $totalOrders = (clone $rangeOrders)->count();
        $testOrders = (clone $rangeOrders)->where('is_test', true)->count();
        $paymentPending = (clone $rangeOrders)->where('status', 'pending_payment')->count();
        $verifiedRealOrders = (clone $rangeOrders)->where('is_test', false)
            ->whereHas('paymentNotifications', fn ($q) => $q->where('status', 'verified'));
        $paidCount = (clone $verifiedRealOrders)->count();
        $paidCents = (int) (clone $verifiedRealOrders)->sum('total_cents');
        $averageCents = $paidCount > 0 ? (int) round($paidCents / $paidCount) : 0;

        $topProducts = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('placed_at', '>=', $start)
                ->where('is_test', false)
                ->whereHas('paymentNotifications', fn ($p) => $p->where('status', 'verified')))
            ->selectRaw('product_name, SUM(quantity) as sold, SUM(line_total_cents) as revenue')
            ->groupBy('product_name')->orderByDesc('sold')->limit(10)->get();

        $statusCounts = (clone $rangeOrders)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return view('admin.reports', compact(
            'days', 'totalOrders', 'testOrders', 'paymentPending',
            'paidCount', 'paidCents', 'averageCents', 'topProducts', 'statusCounts'
        ));
    }
}
