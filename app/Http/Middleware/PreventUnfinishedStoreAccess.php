<?php

namespace App\Http\Middleware;

use App\Services\StoreInstaller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreventUnfinishedStoreAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $installer = app(StoreInstaller::class);
        if ($installer->hasPending() && $installer->tokenFingerprint() !== null && !$installer->installed()
            && !$request->is('install', 'install/*')) {
            return response()->view('setup.waiting', [], 503)
                ->header('Cache-Control', 'no-store, private')
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $next($request);
    }
}
