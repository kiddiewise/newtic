<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Services\PaytrTokenService;
use App\Support\PaytrTestAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use RuntimeException;

final class PaytrCheckoutController extends Controller
{
    public function show(Request $request, Order $order, PaytrTokenService $service): Response
    {
        $isOwner = $request->user() && $order->user_id === $request->user()->id;
        $sessionOwned = in_array(
            $order->id, (array) $request->session()->get('confirmed_order_ids', []), true
        );
        abort_unless($isOwner || $sessionOwned || $request->hasValidSignature(false), 404);
        abort_unless($order->payment_method === 'paytr' && $order->is_test, 404);

        if ($order->status !== 'pending_payment') {
            return response()->view('store.paytr-iframe', [
                'order' => $order, 'token' => null,
                'message' => 'Bu siparişin ödeme durumu değişti. Güncel durumu inceleyin.',
                'paymentReturnUrl' => $this->signedReturn($order, 'result'),
            ])->header('Cache-Control', 'private, no-store');
        }

        $token = null;
        $message = null;
        if (! PaytrTestAccess::available()) {
            $message = 'PAYTR test ödemesi kapalı. Yönetim panelinden test modunu etkinleştirin.';
        } else {
            $attempt = PaytrPaymentAttempt::query()->where('order_id', $order->id)
                ->where('status', 'pending')->latest('id')->first();

            if (! $attempt) {
                $message = 'Aktif PAYTR ödeme girişimi bulunamadı.';
            } else {
                try {
                    $token = $service->requestIframeToken(
                        $order, $attempt, (string) $request->ip(),
                        $this->signedReturn($order, 'success'),
                        $this->signedReturn($order, 'failed'),
                    );
                } catch (RuntimeException|\InvalidArgumentException $e) {
                    // Do not expose raw PAYTR responses or configuration details.
                    $message = 'PAYTR test formu şu anda açılamadı. Ödeme alınmadı. Tekrar deneyin veya PAYTR ayarlarını kontrol edin.';
                }
            }
        }

        return response()->view('store.paytr-iframe', [
            'order' => $order, 'token' => $token,
            'message' => $message,
            'paymentReturnUrl' => $this->signedReturn($order, 'result'),
        ])->header('Cache-Control', 'private, no-store, max-age=0')
          ->header('Referrer-Policy', 'no-referrer');
    }

    private function signedReturn(Order $order, string $result): string
    {
        // Relative signature ensures proxy/Hostinger HTTPS offload does not
        // invalidate signed URLs. APP_URL is the canonical HTTPS staging host.
        $relative = URL::temporarySignedRoute('checkout.success',
            now()->addDays(14), ['order' => $order->id, 'paytr_return' => $result],
            absolute: false);

        return rtrim((string) config('app.url'), '/').'/'.ltrim($relative, '/');
    }
}
