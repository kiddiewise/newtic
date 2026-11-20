<?php

namespace App\Support;

use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * PAYTR expects a public customer IP. Shared hosts may expose the local
 * reverse-proxy IP as REMOTE_ADDR. Forwarded headers are only considered when
 * the direct peer is a loopback/private proxy, never for public remote peers.
 */
final class PaytrClientIp
{
    public static function resolve(Request $request): string
    {
        $remote = (string) $request->server('REMOTE_ADDR', '');
        if (self::isPublic($remote)) {
            return $remote;
        }

        if (filter_var($remote, FILTER_VALIDATE_IP)
            && ! self::isPublic($remote)) {
            // Prefer the nearest externally-routable hop. The proxy is
            // responsible for appending its trusted client value.
            $forwarded = array_reverse(array_map(
                'trim', explode(',', (string) $request->header('X-Forwarded-For', ''))
            ));
            foreach ($forwarded as $candidate) {
                if (self::isPublic($candidate)) {
                    return $candidate;
                }
            }

            $realIp = (string) $request->header('X-Real-IP', '');
            if (self::isPublic($realIp)) {
                return $realIp;
            }
        }

        throw new InvalidArgumentException(
            'PAYTR için geçerli, genel erişime açık müşteri IP adresi belirlenemedi.'
        );
    }

    public static function isPublic(string $ip): bool
    {
        return strlen($ip) <= 39 && filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
