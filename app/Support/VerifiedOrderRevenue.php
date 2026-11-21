<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single source of truth for confirmed, non-test order revenue.
 *
 * A bank claim is not revenue until an administrator verifies it. A PAYTR
 * attempt is not revenue until a signed callback marked the non-test attempt
 * successful. No frontend event, session flag or payment redirect counts.
 *
 * Refunds/partial refunds require a separate ledger before claiming "net
 * revenue"; this deliberately reports confirmed gross order totals.
 */
final class VerifiedOrderRevenue
{
    public static function query(): Builder
    {
        return self::constrain(Order::query());
    }

    public static function constrain(Builder $orders): Builder
    {
        return $orders->where('is_test', false)
            ->whereNotIn('status', ['pending_payment', 'cancelled'])
            ->where(function (Builder $query): void {
                $query->where(function (Builder $bank): void {
                    $bank->where('payment_method', 'bank_transfer')
                        ->whereHas('paymentNotifications',
                            fn (Builder $notice) => $notice->where('status', 'verified'));
                })->orWhere(function (Builder $card): void {
                    $card->where('payment_method', 'paytr')
                        ->whereHas('paytrAttempts',
                            fn (Builder $attempt) => $attempt->where('status', 'success')
                                ->where('is_test', false));
                });
            });
    }
}
