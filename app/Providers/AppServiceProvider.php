<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
