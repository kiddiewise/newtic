<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight browser-side hardening. Do not replace the hosting/CDN CSP:
 * payment iframes, Google OAuth, and uploaded customer images are governed
 * by the existing policy and must keep working.
 */
final class HardenHttpResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        if (! $headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }

        $sensitive = $request->is(
            'admin', 'admin/*',
            'hesabim', 'hesabim/*',
            'odeme', 'odeme/*',
            'siparis', 'siparis/*',
            'sepet', 'sepet/*',
            'giris', 'uye-ol',
            'sifremi-unuttum', 'sifre-yenile', 'sifre-yenile/*',
            'eposta/dogrula/*',
            'auth/google', 'auth/google/*',
            'install', 'install/*'
        );

        // An emailed signed order link contains private order and address
        // details. Cache/CDNs must never store the corresponding response.
        if ($sensitive) {
            // Respect an existing, equally protective policy (e.g. the
            // one-time admin recovery page explicitly uses "no-store, private").
            // This avoids changing its contract or weakening route-specific
            // response headers.
            $cache = strtolower((string) $headers->get('Cache-Control', ''));
            if (! str_contains($cache, 'no-store')) {
                $headers->set('Cache-Control', 'private, no-store, max-age=0');
            }
            $headers->set('Referrer-Policy', 'no-referrer');
        } elseif (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // Keep camera available for products that request customer photos.
        if (! $headers->has('Permissions-Policy')) {
            $headers->set('Permissions-Policy', 'geolocation=(), microphone=()');
        }

        return $response;
    }
}
