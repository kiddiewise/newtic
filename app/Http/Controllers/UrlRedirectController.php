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