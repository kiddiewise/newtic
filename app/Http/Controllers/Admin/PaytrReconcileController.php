<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaytrPendingPaymentService;
use Illuminate\Http\RedirectResponse;

final class PaytrReconcileController extends Controller
{
    public function __invoke(Order $order, PaytrPendingPaymentService $service): RedirectResponse
    {
        abort_unless($order->payment_method === 'paytr',404);
        $attempt = $order->paytrAttempts()->where('status','pending')->first();
        if (!$attempt) return back()->with('error','Bekleyen ödeme bulunamadı.');
        $result = $service->reconcile($attempt->id);
        $message = match($result) {
            'paid' => 'Ödeme PayTR üzerinden doğrulandı; sipariş hazırlanıyor.',
            'unopened_expired' => 'Ödeme ekranı açılmadan süre dolmuş. Sipariş kapatıldı, stok geri alındı.',
            'no_success_yet' => 'PayTR başarılı ödeme bildirmedi. Kesin sonuç bekleniyor; stok korundu.',
            'not_due' => 'Ödeme ekranı henüz açılmadı; ödeme süresi devam ediyor.',
            'unchanged' => 'Ödeme durumu değişmedi.',
            'external_refund' => 'PayTR üzerinde iade kaydı var. Sağlayıcı panelinden inceleyin; sipariş değiştirilmedi.',
            default => 'Ödeme sonucu doğrulanamadı. Stok ve sipariş durumu değiştirilmedi.',
        };
        return back()->with(in_array($result,['provider_error','context_mismatch','external_refund'],true)?'error':'success',$message);
    }
}
