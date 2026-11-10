<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const TRANSITIONS = [
        'pending_payment' => ['cancelled'], // Preparing requires bank verification via payment inbox.
        'preparing' => ['shipped','cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        abort_if($status !== '' && !array_key_exists($status, self::TRANSITIONS), 422);
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);
        $orders = Order::query()->withCount(['paymentNotifications as pending_payment_notifications_count' => fn ($q) => $q->where('status', 'pending')])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term) {
                $safe = '%'.addcslashes($term, '%_\\').'%';
                $q->where('order_number', 'like', $safe)->orWhere('customer_name', 'like', $safe)
                    ->orWhere('customer_email', 'like', $safe);
            }))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders','status','term'));
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'paymentNotifications.reviewer']);
        $allowed = self::TRANSITIONS[$order->status] ?? [];
        return view('admin.orders.show', compact('order','allowed'));
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required',Rule::in(array_keys(self::TRANSITIONS))],
        ]);
        DB::transaction(function () use ($order, $data) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $next = $data['status'];
            if (!in_array($next, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Bu sipariş durumu değişikliği yapılamaz.',
                ]);
            }

            if ($next === 'cancelled') {
                // A verified bank transfer requires a separate refund procedure.
                // Never silently mark a paid order cancelled and leave funds unrefunded.
                if ($locked->paymentNotifications()->where('status', 'verified')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Ödemesi doğrulanan siparişi iptal etmek için önce iade işlemi ve kaydı gereklidir.',
                    ]);
                }
                if ($locked->paymentNotifications()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'İptalden önce bekleyen ödeme bildirimini kontrol edip sonuçlandırın.',
                    ]);
                }
                // Restore reserved stock exactly once; cancelled orders are terminal.
                foreach ($locked->items()->orderBy('product_id')->get() as $item) {
                    if ($item->product_id) {
                        Product::query()->whereKey($item->product_id)->increment('stock_quantity', $item->quantity);
                    }
                }
            }
            $locked->update(['status' => $next]);
        }, 3);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Sipariş durumu güncellendi.');
    }
}
