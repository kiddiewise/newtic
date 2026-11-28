<?php

namespace App\Services\Invoices;

/** Store configuration, never the incoming Host header. No production endpoint. */
final class EdmStoreScope
{
    public static function website(): ?string
    {
        $url=trim((string)config('app.url'));
        if (!filter_var($url,FILTER_VALIDATE_URL)) return null;
        $parts=parse_url($url);
        if (!is_array($parts) || ($parts['scheme']??null)!=='https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port']!==443)
            || !in_array($parts['path']??'',['','/'],true)) return null;
        $host=strtolower($parts['host']);
        if (!str_contains($host,'.') || !filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)
            || filter_var($host,FILTER_VALIDATE_IP)) return null;
        return 'https://'.$host;
    }

    /** Order transport remains test-only and separately requires account evidence. */
    public static function sandboxOrders(): bool
    {
        return self::website()!==null && (bool)config('newtic.checkout_test_mode',true);
    }
}
