<?php

namespace App\Support;

use App\Models\PaytrSetting;

/**
 * Allows only PAYTR's no-charge test_mode on the exact NEWTIC staging site.
 * Changing APP_URL, Host or disabling sandbox checkout closes the gateway.
 */
final class PaytrTestAccess
{
    public const HOST = 'powderblue-dinosaur-538060.hostingersite.com';

    public static function onStaging(): bool
    {
        return (bool) config('newtic.checkout_test_mode', true)
            && parse_url((string) config('app.url'), PHP_URL_HOST) === self::HOST
            && request()->getHost() === self::HOST
            && request()->isSecure();
    }

    public static function available(?PaytrSetting $settings = null): bool
    {
        if (! self::onStaging()
            || ! config('newtic.paytr_card_payments_enabled', false)
            || ! config('newtic.paytr_webhooks_enabled', false)) {
            return false;
        }

        $settings ??= PaytrSetting::query()->find(1);

        return $settings && $settings->is_enabled && $settings->test_mode
            && $settings->hasCredentials();
    }
}
