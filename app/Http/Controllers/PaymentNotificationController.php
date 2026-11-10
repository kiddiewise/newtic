<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\PaymentNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class PaymentNotificationController extends Controller
{
    /**
     * The URL contains a numeric order id, but is not a public payment token.
     * Anonymous customers must retain the same checkout session.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        $isOwner = $request->user() && $order->user_id === $request->user()->id;
        $isSessionOrder = in_array($order->id,
            array_map('intval', (array) $request->session()->get('confirmed_order_ids', [])), true);
        abort_unless($isOwner || $isSessionOrder || $request->hasValidSignature(false), 404);

        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ]);

        $created = DB::transaction(function () use ($order, $data) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== 'pending_payment' || $lockedOrder->payment_method !== 'bank_transfer') {
                throw ValidationException::withMessages([
                    'payment' => 'Bu sipariş için yeni ödeme bildirimi yapılamaz.',
                ]);
            }

            $pending = PaymentNotification::query()
                ->where('order_id', $lockedOrder->id)->where('status', 'pending')
                ->first();
            if ($pending) {
                return false; // Repeated taps don't generate more admin notifications.
            }

            if (PaymentNotification::query()->where('order_id', $lockedOrder->id)
                ->where('status', 'verified')->exists()) {
                throw ValidationException::withMessages([
                    'payment' => 'Bu siparişin ödemesi zaten onaylandı.',
                ]);
            }

            $bank = BankAccount::query()->whereKey($data['bank_account_id'])
                ->where('is_active', true)->first();
            if (!$bank) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'Seçtiğiniz banka hesabı artık kullanılamıyor.',
                ]);
            }

            PaymentNotification::create([
                'order_id' => $lockedOrder->id,
                'bank_account_id' => $bank->id,
                'bank_name_snapshot' => $bank->bank_name,
                'iban_snapshot' => $bank->iban,
                'status' => 'pending',
                'customer_note' => $data['customer_note'] ?? null,
            ]);

            return true;
        }, 3);

        $returnUrl = URL::temporarySignedRoute(
            'checkout.success', now()->addDays(14), ['order' => $order->id], absolute: false
        );
        return redirect()->to($returnUrl)->with('success',
            $created
                ? 'Ödeme bildiriminiz alındı. Banka hareketleri kontrol edildikten sonra siparişiniz güncellenecek.'
                : 'Ödeme bildiriminiz zaten kontrol sırasındadır.'
        );
    }
}
