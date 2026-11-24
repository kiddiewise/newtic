<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReturnRequest;
use App\Services\OrderReturnService;
use App\Services\PaytrRefundClient;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

final class OrderReturnController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string)$request->query('status','');
        $cases = OrderReturnRequest::query()->with('order')
            ->when($status === 'open', fn($q) => $q->whereIn('status', OrderReturnRequest::ACTIVE))
            ->when(array_key_exists($status, OrderReturnRequest::LABELS), fn($q) => $q->where('status',$status))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.returns.index', compact('cases','status'));
    }

    public function store(Request $request, \App\Models\Order $order, OrderReturnService $service): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required','in:cancel,return'],
            'reason' => ['required', Rule::in(array_keys(OrderReturnRequest::REASONS))],
            'note' => ['nullable','string','max:1000'], 'items' => ['required','array','max:100'],
            'items.*' => ['required','integer','min:0','max:100000'],
        ]);
        $service->create($order, $data, $request->user()->id);
        return redirect()->route('admin.orders.show',$order)->with('success','Talep oluşturuldu.');
    }

    public function update(Request $request, OrderReturnRequest $returnRequest, OrderReturnService $service, PaytrRefundClient $paytr): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve','return_code','received','reject','complete_unpaid','refund','reconcile','finish'])],
            'public_note' => ['nullable','string','max:1000'],
            'return_carrier' => ['nullable','string','max:100'],
            'return_code' => ['nullable','string','max:100'],
            'bank_reference' => ['nullable','string','max:100'],
            'confirmed' => ['nullable','boolean'], 'shipping' => ['nullable','boolean'], 'restock' => ['nullable','boolean'],
        ]);
        $actor = $request->user()->id;
        if ($data['action'] === 'refund') {
            $order = $returnRequest->order;
            if ($order->payment_method === 'bank_transfer' && (blank($data['bank_reference'] ?? null) || $order->is_test)) {
                throw ValidationException::withMessages(['return'=>'Havale iadesi için gerçek banka transferi ve işlem referansı gerekir; test siparişinden gerçek iade kaydı oluşturulamaz.']);
            }
            $case = $service->reserveRefund($returnRequest, $data, $actor);
            if ($order->payment_method === 'bank_transfer') {
                $service->refundResult($case, 'verified', $actor, $data['bank_reference']);
            } else {
                try { $state = $paytr->send($case); }
                catch (\Throwable $e) {
                    Log::warning('paytr_refund_uncertain', ['return_id'=>$case->id,'exception'=>class_basename($e)]);
                    $state = 'uncertain';
                }
                $service->refundResult($case, $state, $actor);
            }
        } elseif ($data['action'] === 'reconcile') {
            if ($returnRequest->order->payment_method !== 'paytr' || !in_array($returnRequest->refund_status, ['processing','uncertain','submitted'], true)) {
                throw ValidationException::withMessages(['return'=>'Sorgulanacak PAYTR iadesi yok.']);
            }
            try { $state = $paytr->reconcile($returnRequest); }
            catch (\Throwable) { $state = 'uncertain'; }
            $service->refundResult($returnRequest, $state, $actor);
        } elseif ($data['action'] === 'finish') {
            $service->finish($returnRequest, $data, $actor);
        } else {
            $service->review($returnRequest, $data, $actor);
        }
        return redirect()->route('admin.orders.show', $returnRequest->order_id)->with('success','Talep güncellendi. Para iadesinin gerçek durumu aşağıda gösterilir.');
    }
}
