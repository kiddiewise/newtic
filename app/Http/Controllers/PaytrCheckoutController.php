<?php

namespace App\Http\Controllers;

use App\Exceptions\PaytrTokenException;
use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Services\PaytrTokenService;
use App\Support\PaytrTestAccess;
use App\Support\PaytrClientIp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
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
        abort_unless($order->payment_method === 'paytr', 404);

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
            $message = 'Kartla ödeme şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.';
        } else {
            $attempt = PaytrPaymentAttempt::query()->where('order_id', $order->id)
                ->where('status', 'pending')->latest('id')->first();

            if (! $attempt) {
                $message = 'Aktif PAYTR ödeme girişimi bulunamadı.';
            } elseif (!$attempt->canRequestToken()) {
                $message = 'Ödeme ekranını açma süresi doldu. Önce sipariş durumunu kontrol edin; sonuç doğrulanmadan tekrar ödeme yapmayın.';
            } else {
                try {
                    $token = $service->requestIframeToken(
                        $order, $attempt, PaytrClientIp::resolve($request),
                        $this->signedReturn($order, 'success'),
                        $this->signedReturn($order, 'failed'),
                    );
                } catch (PaytrTokenException $e) {
                    // Only a whitelisted failure class, never customer/merchant
                    // data, gateway text, token, URL or IP address, is recorded.
                    Log::warning('paytr_iframe_test_failure', ['safe_code' => $e->safeCode]);
                    $message = 'PAYTR ödeme formu açılamadı ('.$e->safeCode.'). Sipariş durumunu kontrol edip tekrar deneyin.';
                } catch (\InvalidArgumentException $e) {
                    Log::warning('paytr_iframe_test_failure', ['safe_code' => 'invalid_checkout_network']);
                    $message = 'Güvenli ödeme için müşteri bağlantısı doğrulanamadı. Ödeme alınmadı. Tekrar deneyin.';
                } catch (RuntimeException $e) {
                    Log::warning('paytr_iframe_test_failure', ['safe_code' => 'test_configuration']);
                    $message = 'PAYTR ödeme formu şu anda açılamıyor. Lütfen sipariş durumunu kontrol edin.';
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
