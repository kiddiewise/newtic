<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturnRequest;
use App\Services\OrderReturnService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

final class OrderReturnController extends Controller
{
    public function store(Request $request, Order $order, OrderReturnService $service): RedirectResponse
    {
        $owner = $request->user() && $order->user_id === $request->user()->id;
        $session = in_array($order->id, array_map('intval', (array)$request->session()->get('confirmed_order_ids', [])), true);
        abort_unless($owner || $session || $request->hasValidSignature(false), 404);
        $data = $request->validate([
            'kind' => ['required','in:cancel,return'],
            'reason' => ['required', Rule::in(array_keys(OrderReturnRequest::REASONS))],
            'note' => ['nullable','string','max:1000'], 'items' => ['required','array','max:100'],
            'items.*' => ['required','integer','min:0','max:100000'],
        ]);
        $service->create($order, $data, $request->user()?->id);
        return redirect()->to(URL::temporarySignedRoute('checkout.success', now()->addDays(14), ['order'=>$order->id], absolute:false))
            ->with('success','Talebiniz alındı. Güncel durumu bu sipariş ekranından takip edebilirsiniz.');
    }
}
