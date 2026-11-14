<?php

namespace App\Http\Middleware;

use App\Http\Controllers\UrlRedirectController;
use App\Models\Category;
use App\Models\Product;
use App\Models\UrlRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product/category routes are dynamic and match even when the slug is gone.
 * Ordinary Route::fallback does not run for those old migration URLs.
 * Prefer existing live catalog entries over administrator-defined redirects.
 */
final class ResolveCatalogRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        try {
            $path = UrlRedirect::normalize($request->getPathInfo());
        } catch (\InvalidArgumentException) {
            return $next($request);
        }
        if (!UrlRedirect::query()->where('old_path', $path)->where('is_active', true)->exists()) {
            return $next($request);
        }

        $slug = (string) $request->route('slug');
        $name = (string) $request->route()?->getName();
        $existing = match ($name) {
            'product.show' => Product::query()->where('slug', $slug)->where('is_active', true)->exists(),
            'category.show' => Category::query()->where('slug', $slug)->where('is_active', true)->exists(),
            default => true,
        };

        if ($existing) return $next($request);
        return app(UrlRedirectController::class)($request);
    }
}