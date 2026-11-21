<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\VerifiedOrderRevenue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Export aggregate statistics only; never visitor/session identifiers,
     * user IDs, emails, IPs, payment details or customer addresses.
     */
    public function export(Request $request): StreamedResponse
    {
        $days = (int) $request->query('days', 30);
        abort_unless(in_array($days, [7, 30, 90], true), 422);
        $report = $this->buildReport($days);

        return response()->streamDownload(function () use ($report, $days) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=;\r\n");
            $row = static function (array $values) use ($out): void {
                // Spreadsheet apps must not execute a catalog title or search
                // phrase as a formula. Prefix dangerous leading characters.
                fputcsv($out, array_map(static function ($value) {
                    $text = (string) $value;

                    return preg_match('/^\s*[=+\-@]/u', $text) ? "'".$text : $text;
                }, $values), ';');
            };

            $row(['NEWTIC — Pazarlama ve Analitik', $days.' gün']);
            $row(['Veri kapsamı', 'Anonim ziyaretler için analitik izni gerekir; satışlar doğrulanmış gerçek siparişlerdir.']);
            $row([]);
            $row(['Gösterge', 'Bu dönem', 'Önceki dönem']);
            foreach ([
                'Ziyaretçi oturumu' => ['visitors', 'visitors'],
                'Sayfa görüntüleme' => ['pageViews', 'pageViews'],
                'Doğrulanmış sipariş' => ['orderCount', 'orderCount'],
                'Doğrulanmış ciro (TL)' => ['revenueCents', 'revenueCents'],
                'Ortalama sipariş (TL)' => ['averageCents', 'averageCents'],
            ] as $label => [$field, $metric]) {
                $divisor = str_contains($label, '(TL)') ? 100 : 1;
                $row([$label, $report[$field] / $divisor, $report['comparison'][$metric]['previous'] / $divisor]);
            }

            $row([]);
            $row(['Müşteri segmentleri (yalnızca üyelikli alışverişler)', 'Alıcı sayısı']);
            $row(['Bu dönemde alışveriş yapan üyeler', $report['buyers']['all']]);
            $row(['Daha önce satın almış üyeler', $report['buyers']['returning']]);
            $row(['İlk kez satın alan üyeler', $report['buyers']['new']]);
            $row([]);
            $row(['Günlük trafik ve satış', 'Sayfa görüntüleme', 'Ziyaretçi oturumu', 'Doğrulanmış ciro (TL)']);
            foreach ($report['trend'] as $day) {
                $row([$day['label'], $day['views'], $day['visitors'], $day['revenue']]);
            }
            $row([]);
            $row(['Ürün', 'İnceleme', 'Sepete ekleme', 'Satılan adet', 'Doğrulanmış ürün cirosu (TL)']);
            foreach ($report['products'] as $product) {
                $row([$product['name'], $product['views'], $product['cart_adds'], $product['sold'], $product['revenue_cents'] / 100]);
            }
            $row([]);
            $row(['Arama terimi', 'Arama sayısı', 'Sonuçsuz arama']);
            foreach ($report['searches'] as $search) {
                $row([$search->search_term, $search->searches, $search->empty_results]);
            }
            fclose($out);
        }, 'newtic-analitik-'.now()->format('Ymd').'-'.$days.'gun.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function compareMetric(int $current, int $previous): array
    {
        return [
            'previous' => $previous,
            'percent' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
        ];
    }

    private function paidOrders(): \Illuminate\Database\Eloquent\Builder
    {
        return VerifiedOrderRevenue::query();
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
        $previousStart = $start->copy()->subDays($days);
        $previousEnd = $start->copy()->subSecond();
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

        // Equal-length calendar periods. Do not fabricate a percentage when the
        // previous period had zero data (especially during initial rollout).
        $previousEvents = DB::table('analytics_events')
            ->whereBetween('occurred_at', [$previousStart, $previousEnd]);
        $previousOrders = $this->paidOrders()
            ->whereBetween('placed_at', [$previousStart, $previousEnd]);
        $previousVisitors = (clone $previousEvents)->distinct('session_key')->count('session_key');
        $previousPageViews = (clone $previousEvents)->where('event_name', 'page_view')->count();
        $previousOrderCount = (clone $previousOrders)->count();
        $previousRevenueCents = (int) (clone $previousOrders)->sum('total_cents');
        $previousAverageCents = $previousOrderCount
            ? (int) round($previousRevenueCents / $previousOrderCount) : 0;
        $comparison = [
            'visitors' => $this->compareMetric($visitors, $previousVisitors),
            'pageViews' => $this->compareMetric($pageViews, $previousPageViews),
            'orderCount' => $this->compareMetric($orderCount, $previousOrderCount),
            'revenueCents' => $this->compareMetric($revenueCents, $previousRevenueCents),
            'averageCents' => $this->compareMetric($averageCents, $previousAverageCents),
        ];

        // Only aggregate registered-account buyers. Guest orders are
        // deliberately excluded instead of matching customers by email.
        $paidMembers = (clone $paidOrders)->whereNotNull('user_id');
        $memberCount = (clone $paidMembers)->distinct()->count('user_id');
        $returningMemberCount = (clone $paidMembers)
            ->whereIn('user_id', $this->paidOrders()->where('placed_at', '<', $start)
                ->whereNotNull('user_id')->select('user_id'))
            ->distinct()->count('user_id');
        $buyers = [
            'all' => $memberCount,
            'returning' => $returningMemberCount,
            'new' => $memberCount - $returningMemberCount,
            'returning_percent' => $memberCount
                ? round(100 * $returningMemberCount / $memberCount, 1) : null,
        ];

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
            VerifiedOrderRevenue::constrain(
                $query->whereBetween('placed_at', [$start, $end])
            );
        })->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(quantity) as units, SUM(line_total_cents) as revenue_cents')
            ->groupBy('product_id')->orderByDesc('units')->limit(30)->get()->keyBy('product_id');

        $ids = $productEvents->keys()->merge($soldProducts->keys())->unique()->values();
        $catalog = Product::query()->whereIn('id', $ids)
            ->get(['id', 'name', 'stock_quantity', 'tracks_stock'])->keyBy('id');
        $products = $ids->map(fn ($id) => [
            'id' => (int) $id,
            'name' => $catalog[$id]->name ?? ('Ürün #'.$id),
            'views' => (int) ($productEvents[$id]->views ?? 0),
            'cart_adds' => (int) ($productEvents[$id]->cart_adds ?? 0),
            'sold' => (int) ($soldProducts[$id]->units ?? 0),
            'revenue_cents' => (int) ($soldProducts[$id]->revenue_cents ?? 0),
            'stock' => (int) ($catalog[$id]->stock_quantity ?? 0),
            'tracks_stock' => (bool) ($catalog[$id]->tracks_stock ?? false),
        ])->sortByDesc('views')->take(15)->values();

        // Explainable opportunity rules, not AI or proven causal findings.
        // A minimum sample size reduces false alerts from tiny datasets.
        $opportunities = $products->filter(fn (array $product) => $product['views'] >= 10)
            ->map(function (array $product) {
                $reason = ($product['tracks_stock'] && $product['stock'] === 0)
                    ? 'Yoğun ilgi var, ürün stokta yok'
                    : (($product['cart_adds'] / max(1, $product['views'])) < 0.05
                        ? 'Çok inceleniyor, sepete eklenme oranı düşük'
                        : null);

                return $reason ? [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'views' => $product['views'],
                    'cart_adds' => $product['cart_adds'],
                    'reason' => $reason,
                ] : null;
            })->filter()->take(5)->values();

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
            'devices', 'live', 'comparison', 'buyers', 'opportunities'
        );
    }
}
