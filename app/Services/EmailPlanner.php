<?php

namespace App\Services;

use App\Models\EmailDelivery;
use App\Models\EmailSetting;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\ProductReview;
use App\Models\ShippingShipment;
use App\Models\User;
use Illuminate\Support\Carbon;

final class EmailPlanner
{
    public function queue(
        string $event, string $recipient, ?Order $order = null,
        ?User $user = null, ?int $referenceId = null,
        ?Carbon $dueAt = null, string $suffix = '', ?string $secretPayload = null
    ): void {
        $settings = EmailSetting::current();
        if (!$settings->active() || !$settings->allows($event)) return;
        if ($order?->is_test && !EmailSetting::previewEnvironment()) return;
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return;
        // A staging installation never sends to a real customer.
        if (EmailSetting::previewEnvironment() && !$settings->safeRecipient($recipient)) return;
        $key = hash('sha256', implode('|', [
            $event, $order?->id ?? 0, $user?->id ?? 0,
            $referenceId ?? 0, strtolower(trim($recipient)), $suffix,
        ]));
        EmailDelivery::query()->firstOrCreate(['dedupe_key' => $key], [
            'event' => $event, 'order_id' => $order?->id, 'user_id' => $user?->id,
            'reference_id' => $referenceId, 'recipient_email' => strtolower(trim($recipient)),
            'recipient_name' => $order?->customer_name ?? $user?->name,
            'status' => 'pending', 'attempts' => 0, 'available_at' => $dueAt ?? now(),
            'secret_payload' => $secretPayload,
        ]);
    }

    public function orderCreated(Order $order): void
    {
        $this->queue('customer_order_received', $order->customer_email, $order);
        $this->admins('admin_new_order', $order);
    }

    public function orderChanged(Order $order): void
    {
        if (!$order->wasChanged('status')) return;
        $event = match ($order->status) {
            'preparing' => 'customer_payment_verified',
            'shipped' => 'customer_order_shipped',
            'out_for_delivery' => 'customer_out_for_delivery',
            'completed' => 'customer_delivered',
            'cancelled' => 'customer_cancelled',
            default => null,
        };
        if ($event) $this->queue($event, $order->customer_email, $order);
        if ($order->status === 'completed' && $order->user_id !== null
            && $order->shippingShipment?->remote_status === 'delivered') {
            $settings = EmailSetting::current();
            $days = max(1, min(14, (int)$settings->review_delay_days));
            $this->queue('customer_review_invite', $order->customer_email,
                $order, $order->user, null, now()->addDays($days));
        }
    }

    public function paymentNotificationCreated(PaymentNotification $notice): void
    {
        $order = $notice->order;
        if (!$order) return;
        $this->queue('customer_payment_notice', $order->customer_email, $order, null, $notice->id);
        $this->admins('admin_payment_notice', $order, $notice->id);
    }

    public function paymentNotificationChanged(PaymentNotification $notice): void
    {
        if ($notice->wasChanged('status') && $notice->status === 'rejected' && $notice->order) {
            $this->queue('customer_payment_rejected', $notice->order->customer_email,
                $notice->order, null, $notice->id);
        }
    }

    public function shipmentChanged(ShippingShipment $shipment): void
    {
        if (!$shipment->wasChanged('remote_status')) return;
        if (in_array($shipment->remote_status, ['on_return','returned','canceled','failed'], true)) {
            $this->admins('admin_shipping_exception', $shipment->order, $shipment->id,
                $shipment->remote_status.'|'.(string)$shipment->last_callback_time);
        }
    }

    public function reviewSubmitted(ProductReview $review): void
    {
        $this->admins('admin_review_pending', $review->order, $review->id,
            hash('sha256', $review->body.'|'.$review->rating));
    }

    public function reviewApproved(ProductReview $review): void
    {
        if ($review->status === 'approved' && $review->order && $review->user) {
            $this->queue('customer_review_approved', $review->user->email,
                $review->order, $review->user, $review->id,
                null, (string)$review->updated_at);
        }
    }

    public function welcome(User $user): void
    {
        if ($user->is_admin) return;
        $this->queue('customer_welcome', $user->email, null, $user);
        if (!$user->email_verified_at) $this->verification($user, 'initial');
    }

    public function verification(User $user, string $unique): void
    {
        if (!$user->is_admin && !$user->email_verified_at) {
            $this->queue('customer_email_verification', $user->email, null, $user, null, null, $unique);
        }
    }

    public function passwordReset(User $user, string $token): void
    {
        // Password broker tokens are encrypted at rest, expire independently
        // of the outbox and are cleared as soon as processed.
        if ($user->is_admin) return;
        $this->queue('customer_password_reset', $user->email,
            null, $user, null, null, hash('sha256', $token), $token);
    }

    private function admins(string $event, ?Order $order, ?int $referenceId = null, string $suffix = ''): void
    {
        if (!$order) return;
        foreach (EmailSetting::current()->adminRecipients() as $email) {
            $this->queue($event, $email, $order, null, $referenceId, null, $suffix);
        }
    }
}
