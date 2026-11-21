<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AnalyticsCenterController extends Controller
{
    public function index(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        abort_unless(in_array($days, [7, 30, 90], true), 422);

        return view('admin.analytics', $this->buildReport($days));
    }

    public function live(): JsonResponse
    {
        return response()->json($this->liveSnapshot())
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function paidOrders(): \Illuminate\Database\Eloquent\Builder
    {
        // Never treat a bank notification as paid unless an admin verifies it.
        // PAYTR must likewise have a verified successful callback; test orders
        // and cancelled orders are ALWAYS excluded from revenue.
        return Order::query()->where('is_test', false)
            ->whereNotIn('status', ['cancelled', 'pending_payment'])
            ->where(function ($query) {
                $query->whereHas('paymentNotifications', fn ($q) => $q->where('status', 'verified'))
                    ->orWhereHas('paytrAttempts', fn ($q) => $q->where('status', 'success')->where('is_test', false));
            });
    }

    private function liveSnapshot(): array
    {
        $active = DB::table('analytics_sessions')
            ->where('last_seen_at', '>=', now()->subMinutes(5));
        $visitors = (clone $active)->orderByDesc('last_seen_at')->limit(35)
            ->get(['current_path', 'page_type', 'device', 'city', 'country_code', 'last_seen_at'])
            ->map(fn ($item) => [
                'page' => $item->current_path,
                'kind' => $item->page_type,
                'device' => $item->device,
                'location' => $item->city ?: ($item->country_code ?: 'Bilinmiyor'),
                'seconds_ago' => (int) abs(now()->diffInSeconds(\Carbon\Carbon::parse($item->last_seen_at))),
            ])->all();

        return [
            'online' => (clone $active)->count(),
            'pages' => (clone $active)->select('current_path', DB::raw('COUNT(*) as visitors'))
                ->groupBy('current_path')->orderByDesc('visitors')->limit(5)->get(),
            'visitors' => $visitors,
            'updated_at' => now()->format('H:i:s'),
            'window_minutes' => 5,
        ];
    }

    private function buildReport(int $days): array
    {
        $start = now()->startOfDay()->subDays($days - 1);
        $end = now()->endOfDay();
        $events = DB::table('analytics_events')->whereBetween('occurred_at', [$start, $end]);
        $paidOrders = $this->paidOrders()->whereBetween('placed_at', [$start, $end]);

        $visitors = (clone $events)->distinct('session_key')->count('session_key');
        $pageViews = (clone $events)->where('event_name', 'page_view')->count();
        $productViews = (clone $events)->where('event_name', 'view_item')->count();
        $cartAdds = (clone $events)->where('event_name', 'add_to_cart')->count();
        $checkoutStarts = (clone $events)->where('event_name', 'begin_checkout')->count();
        $orderCount = (clone $paidOrders)->count();
        $revenueCents = (int) (clone $paidOrders)->sum('total_cents');
        $averageCents = $orderCount ? (int) round($revenueCents / $orderCount) : 0;

        $sessionsByEvent = (clone $events)
            ->select('event_name', DB::raw('COUNT(DISTINCT session_key) as total'))
            ->whereIn('event_name', ['page_view', 'view_item', 'add_to_cart', 'begin_checkout'])
            ->groupBy('event_name')->pluck('total', 'event_name');
        $funnel = [
            ['label' => 'Ziyaret', 'total' => $visitors],
            ['label' => 'Ürün inceleyen', 'total' => (int) ($sessionsByEvent['view_item'] ?? 0)],
            ['label' => 'Sepete ekleyen', 'total' => (int) ($sessionsByEvent['add_to_cart'] ?? 0)],
            ['label' => 'Ödemeye geçen', 'total' => (int) ($sessionsByEvent['begin_checkout'] ?? 0)],
        ];

        $viewsByDay = (clone $events)->where('event_name', 'page_view')
            ->selectRaw('DATE(occurred_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(occurred_at)')->pluck('total', 'day');
        $visitorsByDay = (clone $events)->selectRaw('DATE(occurred_at) as day, COUNT(DISTINCT session_key) as total')
            ->groupByRaw('DATE(occurred_at)')->pluck('total', 'day');
        $revenueByDay = (clone $paidOrders)->selectRaw('DATE(placed_at) as day, SUM(total_cents) as total')
            ->groupByRaw('DATE(placed_at)')->pluck('total', 'day');
        $trend = collect(range(0, $days - 1))->map(function ($i) use ($start, $viewsByDay, $visitorsByDay, $revenueByDay) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();

            return [
                'date' => $date->format('d.m'),
                'label' => $date->translatedFormat('d M'),
                'views' => (int) ($viewsByDay[$key] ?? 0),
                'visitors' => (int) ($visitorsByDay[$key] ?? 0),
                'revenue' => round(((int) ($revenueByDay[$key] ?? 0)) / 100, 2),
            ];
        })->values()->all();

        $productEvents = (clone $events)->whereNotNull('product_id')
            ->whereIn('event_name', ['view_item', 'add_to_cart'])
            ->selectRaw('product_id, SUM(CASE WHEN event_name = \'view_item\' THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN event_name = \'add_to_cart\' THEN 1 ELSE 0 END) as cart_adds')
            ->groupBy('product_id')->orderByDesc('views')->limit(30)->get()->keyBy('product_id');

        $soldProducts = OrderItem::query()->whereHas('order', function ($query) use ($start, $end) {
            $query->whereBetween('placed_at', [$start, $end])
                ->where('is_test', false)
                ->whereNotIn('status', ['cancelled', 'pending_payment'])
                ->where(fn ($sub) => $sub->whereHas('paymentNotifications', fn ($p) => $p->where('status', 'verified'))
                    ->orWhereHas('paytrAttempts', fn ($p) => $p->where('status', 'success')->where('is_test', false)));
        })->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(quantity) as units, SUM(line_total_cents) as revenue_cents')
            ->groupBy('product_id')->orderByDesc('units')->limit(30)->get()->keyBy('product_id');

        $ids = $productEvents->keys()->merge($soldProducts->keys())->unique()->values();
        $names = Product::query()->whereIn('id', $ids)->pluck('name', 'id');
        $products = $ids->map(fn ($id) => [
            'id' => (int) $id,
            'name' => $names[$id] ?? ('Ürün #'.$id),
            'views' => (int) ($productEvents[$id]->views ?? 0),
            'cart_adds' => (int) ($productEvents[$id]->cart_adds ?? 0),
            'sold' => (int) ($soldProducts[$id]->units ?? 0),
            'revenue_cents' => (int) ($soldProducts[$id]->revenue_cents ?? 0),
        ])->sortByDesc('views')->take(15)->values();

        $pages = (clone $events)->where('event_name', 'page_view')
            ->select('page_path', DB::raw('COUNT(*) as views'))
            ->groupBy('page_path')->orderByDesc('views')->limit(12)->get();

        $searches = (clone $events)->where('event_name', 'search')->whereNotNull('search_term')
            ->select('search_term', DB::raw('COUNT(*) as searches'),
                DB::raw('SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as empty_results'))
            ->groupBy('search_term')->orderByDesc('searches')->limit(12)->get();

        $sources = (clone $events)->where('event_name', 'page_view')
            ->selectRaw("COALESCE(source, 'Doğrudan / bilinmiyor') as source, COUNT(*) as total")
            ->groupBy('source')->orderByDesc('total')->limit(8)->get();

        $devices = (clone $events)->where('event_name', 'page_view')
            ->selectRaw('device, COUNT(*) as total')->groupBy('device')->pluck('total', 'device');
        $live = $this->liveSnapshot();

        return compact(
            'days', 'visitors', 'pageViews', 'productViews', 'cartAdds',
            'checkoutStarts', 'orderCount', 'revenueCents', 'averageCents',
            'trend', 'funnel', 'products', 'pages', 'searches', 'sources',
            'devices', 'live'
        );
    }
}
