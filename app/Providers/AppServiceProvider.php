<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\ShippingShipment;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\EmailPlanner;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A plain IP-only 8/min GET throttle caused customer-facing 429 pages
        // when a pending PAYTR test iframe was refreshed or revisited.
        // Keep protective limits, but scope the normal window to the
        // authorized order and browser session. A separate high global IP
        // ceiling limits abusive traffic without blocking ordinary checkout.
        RateLimiter::for('paytr-frame', function (Request $request): array {
            $order = $request->route('order');
            $orderId = $order instanceof Order ? $order->getKey() : (string) $order;
            $visitor = $request->user()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'session:'.$request->session()->getId();

            return [
                Limit::perMinute(24)->by(
                    'paytr-frame:order:'.$orderId.':visitor:'.hash('sha256', $visitor)
                ),
                Limit::perMinute(120)->by('paytr-frame:network:'.$request->ip()),
            ];
        });

        // Register against the existing authoritative state transitions.
        // Outbox inserts share the business transaction and roll back with it.
        Order::created(fn (Order $row) => app(EmailPlanner::class)->orderCreated($row));
        Order::updated(fn (Order $row) => app(EmailPlanner::class)->orderChanged($row));
        PaymentNotification::created(fn (PaymentNotification $row) => app(EmailPlanner::class)->paymentNotificationCreated($row));
        PaymentNotification::updated(fn (PaymentNotification $row) => app(EmailPlanner::class)->paymentNotificationChanged($row));
        ShippingShipment::updated(fn (ShippingShipment $row) => app(EmailPlanner::class)->shipmentChanged($row));
        ProductReview::updated(function (ProductReview $row) {
            if ($row->wasChanged('status') && $row->status === 'approved') {
                app(EmailPlanner::class)->reviewApproved($row);
            }
        });
        User::created(fn (User $row) => app(EmailPlanner::class)->welcome($row));
    }
}
