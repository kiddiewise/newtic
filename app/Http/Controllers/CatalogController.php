<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Support\ProductReviewEligibility;
use App\Support\ProductReviewVisibility;
use App\Support\CategoryHierarchy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function category(Request $request, string $slug): View|JsonResponse
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $all = CategoryHierarchy::all();
        abort_unless(CategoryHierarchy::visible($category, $all), 404);

        $breadcrumbs = CategoryHierarchy::ancestors($category, $all);
        $categoryIds = CategoryHierarchy::descendantIds($category, $all);
        $subcategories = $category->activeChildren()->get();
        $banners = $category->banners()->visible()->get();

        $products = ProductReviewVisibility::withRatingSummary(Product::query()->with(['options', 'badges']))
            ->where(function ($query) use ($categoryIds) {
                $query->whereIn('primary_category_id', $categoryIds)
                    ->orWhereHas('categories', fn ($related) => $related->whereIn('categories.id', $categoryIds));
            })
            ->where('is_active', true)
            ->orderBy('name')->orderBy('id')->paginate(12);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('store.partials.infinite-products', [
                    'products' => $products,
                    'emptyMessage' => 'Bu kategoride henüz ürün bulunmuyor.',
                ])->render(),
                'count' => $products->total(),
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('store.category', compact(
            'category', 'breadcrumbs', 'subcategories', 'banners', 'products'
        ));
    }

    public function product(Request $request, string $slug): View
    {
        $product = Product::query()->with(['category', 'images', 'options', 'badges'])->where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->firstOrFail();
        abort_unless(CategoryHierarchy::visible($product->category), 404);

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

        // The multi-grammage picker stays scoped to this specific subcategory.
        $categoryProducts = Product::query()
            ->with('options')
            ->where('primary_category_id', $product->primary_category_id)
            ->where('is_active', true)
            ->orderBy('name')->get();

        // Public ratings include only approved, non-test purchase reviews.
        $publicReviewQuery = $product->reviews();
        ProductReviewVisibility::constrain($publicReviewQuery);
        $isReviewPreview = ProductReviewVisibility::showsTestReviews();
        $testReviewCount = $isReviewPreview
            ? (clone $publicReviewQuery)->where('is_test', true)->count()
            : 0;
        $ratingCounts = (clone $publicReviewQuery)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')->pluck('total', 'rating');
        $reviewCount = (int) $ratingCounts->sum();
        $reviewAverage = $reviewCount
            ? round($ratingCounts->sum(fn ($total, $rating) => $rating * $total) / $reviewCount, 1)
            : 0;
        $reviews = (clone $publicReviewQuery)->with('user:id,name')
            ->latest('id')->paginate(5, ['*'], 'yorum_sayfa')
            ->fragment('urun-yorumlari');

        $canReview = $request->user()
            ? ProductReviewEligibility::order($request->user(), $product) !== null
            : false;
        $myReview = $request->user()
            ? ProductReview::query()->where('product_id', $product->id)
                ->where('user_id', $request->user()->id)->first()
            : null;

        $pickerReset = (bool) $request->session()->get('product_picker_reset', false);

        return view('store.product', compact(
            'product', 'categoryProducts', 'pickerReset',
            'ratingCounts', 'reviewCount', 'reviewAverage', 'reviews',
            'canReview', 'myReview', 'isReviewPreview', 'testReviewCount'
        ));
    }

    /**
     * Search follows the same visibility rules as product pages: a disabled
     * parent category must never leak active descendants into suggestions.
     */
    private function visibleSearchCategoryIds(): array
    {
        $all = CategoryHierarchy::all();

        return $all->filter(
            fn (Category $category) => CategoryHierarchy::visible($category, $all)
        )->pluck('id')->all();
    }

    public function suggestions(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);

        if ($term === '') {
            return response()->json(['items' => []])->header('Cache-Control', 'private, no-store');
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $items = Product::query()
            ->where('is_active', true)
            ->whereIn('primary_category_id', $this->visibleSearchCategoryIds())
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('sku', 'like', $like))
            ->orderBy('name')->limit(6)
            ->get(['id', 'name', 'slug', 'price', 'image_path'])
            ->map(fn (Product $product) => [
                'name' => $product->name,
                'price' => number_format((float) $product->price, 2, ',', '.').' ₺',
                'url' => route('product.show', $product->slug),
                'image' => asset($product->image_path ?: 'assets/products/demo-sinker.svg'),
            ])->values()->all();

        return response()->json(['items' => $items])->header('Cache-Control', 'private, no-store');
    }

    public function search(Request $request): View|JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);

        $like = '%'.addcslashes($term, '%_\\').'%';
        $products = ProductReviewVisibility::withRatingSummary(Product::query()->with(['category', 'options', 'badges']))
            ->where('is_active', true)
            ->whereIn('primary_category_id', $this->visibleSearchCategoryIds())
            ->when($term !== '', fn ($q) =>
                $q->where(fn ($sub) => $sub->where('name', 'like', $like)->orWhere('sku', 'like', $like))
            )
            ->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('store.partials.search-results', compact('products'))->render(),
                'heading' => $term !== '' ? 'Arama: '.$term : 'Tüm ürünler',
                'count' => $products->total(),
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('store.search', compact('products', 'term'));
    }
}
