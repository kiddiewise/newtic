<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'verified', 'rejected', 'all'], true), 422);

        $notifications = PaymentNotification::query()->with(['order', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();

        $pendingCount = PaymentNotification::query()->where('status', 'pending')->count();

        return view('admin.payment-notifications.index', compact('notifications', 'status', 'pendingCount'));
    }

    public function verify(Request $request, PaymentNotification $paymentNotification): RedirectResponse
    {
        $request->validate([
            'bank_verified' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($request, $paymentNotification) {
            // Lock in the same order as the customer endpoint to prevent deadlocks.
            $order = Order::query()->whereKey($paymentNotification->order_id)->lockForUpdate()->firstOrFail();
            $notice = PaymentNotification::query()->whereKey($paymentNotification->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending_payment'
                || $order->payment_method !== 'bank_transfer'
                || $notice->order_id !== $order->id || $notice->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => 'Bu bildirim artık doğrulanamaz. Sipariş ve bildirim durumunu yenileyin.',
                ]);
            }

            $notice->update([
                'status' => 'verified',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'admin_note' => 'Banka hareketi yönetici tarafından kontrol edilerek doğrulandı.',
            ]);

            // This is the only path from "payment pending" to "preparing".
            $order->update(['status' => 'preparing']);
        }, 3);

        return redirect()->route('admin.orders.show', $paymentNotification->order_id)
            ->with('success', 'Banka ödemesi doğrulandı. Sipariş Hazırlanıyor durumuna geçirildi.');
    }

    public function reject(Request $request, PaymentNotification $paymentNotification): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $data, $paymentNotification) {
            $order = Order::query()->whereKey($paymentNotification->order_id)->lockForUpdate()->firstOrFail();
            $notice = PaymentNotification::query()->whereKey($paymentNotification->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending_payment'
                || $order->payment_method !== 'bank_transfer'
                || $notice->order_id !== $order->id || $notice->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => 'Bu ödeme bildirimi artık reddedilemez.',
                ]);
            }

            $notice->update([
                'status' => 'rejected',
                'admin_note' => $data['admin_note'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }, 3);

        return redirect()->route('admin.orders.show', $paymentNotification->order_id)
            ->with('success', 'Bildirim reddedildi. Sipariş ödeme bekliyor; müşteri tekrar bildirim yapabilir.');
    }
}
