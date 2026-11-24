<?php

namespace App\Support;

use App\Models\PaytrSetting;

/** Store-local payment policy. The class name is retained for compatibility. */
final class PaytrTestAccess
{
    public static function canonicalHost(): bool
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) && $host !== ''
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && strcasecmp(request()->getHost(), $host) === 0;
    }

    public static function onStaging(): bool
    {
        return self::canonicalHost() && (bool) config('newtic.checkout_test_mode', true);
    }

    public static function liveAllowed(): bool
    {
        return self::canonicalHost() && !config('newtic.checkout_test_mode', true)
            && LegalReadiness::checkoutReady() && LegalReadiness::requiredBeforeLive() === [];
    }

    public static function available(?PaytrSetting $settings = null): bool
    {
        if (!self::canonicalHost()) return false;
        $settings ??= PaytrSetting::query()->find(1);
        if (!$settings || !$settings->is_enabled || !$settings->hasCredentials()) return false;

        // Existing test installations can continue without changing .env.
        // New installations explicitly opt in through their own admin.
        if (!$settings->admin_managed) {
            return self::onStaging() && $settings->test_mode
                && config('newtic.paytr_card_payments_enabled', false)
                && config('newtic.paytr_webhooks_enabled', false);
        }

        return $settings->test_mode || self::liveAllowed();
    }

    public static function acceptsCallbacks(?PaytrSetting $settings): bool
    {
        if (!self::canonicalHost() || !$settings?->hasCredentials()) return false;
        // Disabling new payments or switching mode must not lose callbacks for
        // already issued attempts. The attempt/order snapshots enforce mode.
        return $settings->admin_managed || (self::onStaging()
            && $settings->is_enabled && $settings->test_mode
            && config('newtic.paytr_webhooks_enabled', false));
    }
}
