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
        'pending_payment' => ['preparing','cancelled'],
        'preparing' => ['shipped','cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        abort_if($status !== '' && !array_key_exists($status, self::TRANSITIONS), 422);
        $orders = Order::query()
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders','status'));
    }

    public function show(Order $order): View
    {
        $order->load('items');
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
