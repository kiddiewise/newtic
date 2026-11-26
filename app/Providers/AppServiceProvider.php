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

        // Numeric route throttles can share the same authenticated-user key
        // with unrelated admin polling. EDM operations need independent budgets.
        foreach (['edm-lookup'=>[10,30], 'edm-probe'=>[4,12], 'edm-settings'=>[6,18]] as $name=>$budget) {
            RateLimiter::for($name, function (Request $request) use ($name,$budget): array {
                $blocked = function (Request $request, array $headers) {
                    $seconds = max(1,(int)($headers['Retry-After'] ?? 60));
                    $message = 'İşlem sınırına ulaşıldı. '.$seconds.' saniye sonra tekrar deneyin.';
                    $headers['Cache-Control'] = 'private, no-store, max-age=0';
                    if ($request->expectsJson()) {
                        return response()->json(['message'=>$message],429,$headers);
                    }
                    // Never flash submitted credentials or recipient identifiers.
                    $page = app(\App\Http\Controllers\Admin\EdmSettingsController::class)->edit();
                    $view = $page->getOriginalContent();
                    $view->with('rateLimitMessage',$message);
                    return response()->view('admin.edm-settings',$view->getData(),429,$headers);
                };
                return [
                    Limit::perMinute($budget[0])->by($name.':admin:'.$request->user()->getAuthIdentifier())->response($blocked),
                    Limit::perMinute($budget[1])->by($name.':store')->response($blocked),
                ];
            });
        }

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
