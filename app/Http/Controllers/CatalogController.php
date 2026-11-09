<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function category(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $products = Product::query()
            ->where('primary_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('name')->paginate(24);

        return view('store.category', compact('category', 'products'));
    }

    public function product(Request $request, string $slug): View
    {
        $product = Product::query()->with(['category', 'images'])->where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->firstOrFail();

        // An anonymous session is counted at most once per product every 24 hours.
        // No raw IP addresses are collected or stored.
        $sessionKey = 'product_seen_at.'.$product->id;
        $lastSeen = (int) $request->session()->get($sessionKey, 0);
        $agent = (string) $request->userAgent();
        $robot = preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp/i', $agent) === 1;
        $adminPreview = (bool) $request->user()?->is_admin;
        $now = now()->timestamp;

        if (!$robot && !$adminPreview && ($lastSeen === 0 || $now - $lastSeen >= 86400)) {
            $product->increment('view_count');
            $request->session()->put($sessionKey, $now);
        }

        $categoryProducts = Product::query()
            ->where('primary_category_id', $product->primary_category_id)
            ->where('is_active', true)
            ->orderBy('name')->get();

        return view('store.product', compact('product', 'categoryProducts'));
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);
        $products = Product::query()->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->paginate(24)->withQueryString();

        return view('store.search', compact('products', 'term'));
    }
}
