<?php

namespace App\Http\Middleware;

use App\Models\SeoSetting;
use App\Support\SeoMetadata;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplySeoRobots
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = (string) ($request->route()?->getName() ?? '');
        $sensitive = $request->is('admin', 'admin/*', 'hesabim', 'hesabim/*', 'odeme', 'odeme/*', 'sepet', 'giris', 'uye-ol');
        $nonCatalog = !in_array($route, ['home', 'category.show', 'product.show'], true);
        $globalNoindex = SeoMetadata::staging($request) || SeoSetting::current()->noindex;
        if ($globalNoindex || $sensitive || $nonCatalog) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        } elseif (SeoSetting::current()->nofollow) {
            $response->headers->set('X-Robots-Tag', 'index, nofollow');
        }
        return $response;
    }
}