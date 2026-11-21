<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AnalyticsEventController extends Controller
{
    private const EVENTS = [
        'page_view', 'view_item', 'view_category', 'view_cart',
        'begin_checkout', 'add_to_cart', 'search', 'heartbeat',
    ];

    public function ingest(Request $request): JsonResponse
    {
        // This is first-party, opt-in analytics only. No behavior is collected
        // by the storefront until the user explicitly accepts analytics.
        // Bots, payment redirects, admin pages and customer account pages
        // are never recorded.
        $data = $request->validate([
            'consent' => ['required', 'accepted'],
            'session' => ['required', 'string', 'regex:/^[a-f0-9-]{36}$/D'],
            'event' => ['required', Rule::in(self::EVENTS)],
            'path' => ['required', 'string', 'max:220', 'regex:/^\/[A-Za-z0-9\/._%-]*$/D'],
            'product_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'category_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'term' => ['nullable', 'string', 'max:100'],
            'results_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'source' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/D'],
        ]);

        $path = $data['path'];
        if (preg_match('#^/(admin|hesabim|giris|uye-ol|sifre-|auth|siparis|odeme/paytr|odeme/basarili|odeme/basarisiz|api|install|kurumsal|kampanya/abonelikten-cik)(/|$)#i', $path)) {
            return response()->json(['ok' => true], 202);
        }
        if (preg_match('/bot|spider|crawler|slurp|headless|lighthouse/i', (string) $request->userAgent())) {
            return response()->json(['ok' => true], 202);
        }

        // A user-generated session UUID is random. Persist only its HMAC,
        // never a logged-in account identifier, email, full IP or user-agent.
        $sessionKey = hash_hmac('sha256', $data['session'], (string) config('app.key'));
        $event = $data['event'];
        $now = now();
        $device = preg_match('/tablet|ipad/i', (string) $request->userAgent()) ? 'tablet'
            : (preg_match('/mobile|iphone|android/i', (string) $request->userAgent()) ? 'mobile' : 'desktop');
        $pageType = str_starts_with($path, '/urun/') ? 'product'
            : (str_starts_with($path, '/kategori/') ? 'category'
                : ($path === '/ara' ? 'search' : ($path === '/sepet' ? 'cart'
                    : ($path === '/odeme' ? 'checkout' : ($path === '/' ? 'home' : 'other')))));

        if ($event === 'heartbeat') {
            DB::table('analytics_sessions')->where('session_key', $sessionKey)->update([
                'last_seen_at' => $now,
            ]);

            return response()->json(['ok' => true], 202);
        }

        $productId = $data['product_id'] ?? null;
        if ($productId && ! DB::table('products')->where('id', $productId)->exists()) {
            $productId = null;
        }
        $categoryId = $data['category_id'] ?? null;
        if ($categoryId && ! DB::table('categories')->where('id', $categoryId)->exists()) {
            $categoryId = null;
        }
        if (in_array($event, ['view_item', 'add_to_cart'], true) && ! $productId) {
            return response()->json(['ok' => true], 202);
        }

        $term = null;
        if ($event === 'search' && filled($data['term'] ?? null)) {
            $candidate = trim(preg_replace('/\s+/u', ' ', (string) $data['term']));
            // Never store emails, phone numbers, long numeric strings or URLs.
            if (mb_strlen($candidate) >= 2 && mb_strlen($candidate) <= 60
                && ! preg_match('/[@:\/\/]|[0-9]{7,}/u', $candidate)) {
                $term = mb_strtolower($candidate);
            }
        }

        $source = isset($data['source']) ? mb_strtolower($data['source']) : null;
        DB::table('analytics_sessions')->upsert([[
            'session_key' => $sessionKey,
            'current_path' => $path,
            'page_type' => $pageType,
            'device' => $device,
            'source' => $source,
            'started_at' => $now,
            'last_seen_at' => $now,
        ]], ['session_key'], ['current_path', 'page_type', 'device', 'last_seen_at']);

        DB::table('analytics_events')->insert([
            'session_key' => $sessionKey,
            'event_name' => $event,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'page_path' => $path,
            'search_term' => $term,
            'results_count' => $event === 'search' ? ($data['results_count'] ?? null) : null,
            'source' => $source,
            'device' => $device,
            'occurred_at' => $now,
        ]);

        return response()->json(['ok' => true], 202)->header('Cache-Control', 'no-store');
    }

    public function forget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session' => ['required', 'string', 'regex:/^[a-f0-9-]{36}$/D'],
        ]);
        $key = hash_hmac('sha256', $data['session'], (string) config('app.key'));
        DB::transaction(function () use ($key) {
            DB::table('analytics_events')->where('session_key', $key)->delete();
            DB::table('analytics_sessions')->where('session_key', $key)->delete();
        });

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }
}
