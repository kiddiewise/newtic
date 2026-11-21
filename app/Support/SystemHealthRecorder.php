<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Portable database-backed observations. Do not record request bodies, free-form
 * exception messages or filesystem paths. Telemetry must NEVER break checkout,
 * callbacks, cron or an otherwise successful backup.
 */
final class SystemHealthRecorder
{
    public const MONITORS = [
        'scheduler', 'backup', 'paytr_callback', 'shipping_sync',
        'email_process', 'marketing_process', 'merchant_sync',
        'bank_expiry', 'analytics_prune', 'photo_prune',
    ];

    public static function record(string $key, bool $success, string $detail = 'completed'): void
    {
        if (!in_array($key, self::MONITORS, true)) return;
        if (!preg_match('/^[a-z0-9_-]{1,48}$/D', $detail)) $detail = 'unspecified';

        try {
            $time = now();
            DB::table('system_health_monitors')->updateOrInsert(
                ['key' => $key],
                [
                    'status' => $success ? 'ok' : 'error',
                    'detail' => $detail,
                    'last_checked_at' => $time,
                    $success ? 'last_success_at' : 'last_failure_at' => $time,
                    'updated_at' => $time,
                ]
            );
        } catch (Throwable $exception) {
            // Best effort only: a missed health signal is shown as unknown/stale.
            // Avoid including the original exception which might contain secrets.
            Log::warning('newtic_health_signal_unavailable', ['monitor' => $key]);
        }
    }
}
