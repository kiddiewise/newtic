<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use App\Models\BlogPost;
use App\Support\ProductReviewVisibility;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $sections = HomeSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'slides' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'categories' => fn ($q) => $q->where('is_active', true),
                'products' => fn ($q) => ProductReviewVisibility::withRatingSummary($q->with(['options', 'badges']))->where('is_active', true),
            ])
            ->get();

        // Empty blogs never add a placeholder section to the storefront.
        $latestPosts = BlogPost::published()->with('category')
            ->orderByDesc('published_at')->orderByDesc('id')->limit(4)->get();

        return view('store.home', compact('sections', 'latestPosts'));
    }
}
