<?php

namespace App\Support;

/**
 * No real courier label can be issued by the initial shipping integration.
 * Unlike payment simulation, Dummy Kargo uses an actual provider account but
 * creates no real carrier consignment or shipment-charge quota usage.
 */
final class ShippingTestAccess
{
    public const HOST = 'powderblue-dinosaur-538060.hostingersite.com';

    public static function allowed(): bool
    {
        return (bool) config('newtic.checkout_test_mode', true)
            && (bool) config('newtic.shipping_dummy_enabled', false)
            && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https'
            && parse_url((string) config('app.url'), PHP_URL_HOST) === self::HOST
            && request()->getHost() === self::HOST;
    }
}
