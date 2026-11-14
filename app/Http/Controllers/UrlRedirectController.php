<?php

namespace App\Http\Controllers;

use App\Models\UrlRedirect;
use App\Support\SeoMetadata;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UrlRedirectController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) abort(404);

        try {
            $path = UrlRedirect::normalize($request->getPathInfo());
        } catch (\InvalidArgumentException) {
            abort(404);
        }
        // Laravel fallback routes otherwise turn wrong-method requests to
        // known POST/PATCH/DELETE endpoints into 404 instead of proper 405.
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if (($route->isFallback ?? false) || in_array('GET', $route->methods(), true)) continue;
            if ($route->matches($request, false)) {
                return response('', 405)->header('Allow', implode(', ', $route->methods()));
            }
        }
        $entry = UrlRedirect::query()->where('old_path', $path)
            ->where('is_active', true)->first();
        if (!$entry) abort(404);

        // Track hits without storing visitor IPs or identifying data.
        UrlRedirect::query()->whereKey($entry->id)
            ->update(['hit_count' => \Illuminate\Support\Facades\DB::raw('hit_count + 1'), 'last_hit_at' => now()]);

        $destination = SeoMetadata::url($request, ltrim($entry->new_path, '/'));
        if ($request->getQueryString()) {
            $destination .= '?'.$request->getQueryString();
        }

        return redirect()->away($destination, 301)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}