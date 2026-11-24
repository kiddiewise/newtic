<section class="checkout-card paytr-order-status" id="payment-instructions-title" aria-labelledby="paytr-payment-status-title">
  <div class="payment-instructions-heading">
    <div><span class="payment-eyebrow">PAYTR / KREDİ KARTI</span><h2 id="paytr-payment-status-title">Kart ödeme durumu</h2></div>
    <span class="payment-status-pill {{ in_array($order->status, ['preparing','shipped','out_for_delivery','completed'], true) ? 'is-verified' : '' }}">{{ $tracking['payment'] }}</span>
  </div>
  <div class="payment-copy-grid">
    <div class="payment-copy-item">
      <span>Sipariş tutarı</span>
      <strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong>
    </div>
    <div class="payment-copy-item">
      <span>Sipariş numarası</span><strong>{{ $order->order_number }}</strong>
    </div>
  </div>
  @if($order->status === 'pending_payment')
    <div class="paytr-processing-notice">
      <x-store-icon name="clock-3" />
      <div><strong>PAYTR ödeme bildirimi bekleniyor</strong><p>Ödeme penceresinde işlem yaptıysanız birazdan buraya dönüp durumu yenileyin. Ödeme ekranındaki yönlendirme tek başına onay değildir.</p></div>
    </div>
    @php($pendingAttempt = $order->paytrAttempts()->where('status','pending')->first())
    @if($pendingAttempt?->canRequestToken())
      <a href="{{ route('checkout.paytr.frame', $order) }}" class="checkout-outline-action">Ödeme ekranına dön</a>
    @else
      <p class="payment-guide">Ödeme ekranını açma süresi doldu. Sistem PayTR'den sonucu kontrol ediyor. Ödeme yaptıysanız tekrar ödeme yapmayın. Güncel sonucu bu sayfadan takip edebilirsiniz.</p>
      <a href="{{ request()->fullUrl() }}" class="checkout-outline-action">Ödeme durumunu yenile</a>
    @endif
  @elseif($order->status === 'cancelled')
    <div class="paytr-token-error" role="status"><x-store-icon name="x" /><div><strong>Kart ödemeniz başarısız oldu.</strong><p>Bu sipariş sonlandırıldı, ayrılan stok iade edildi. Sepetinizden yeniden deneyebilirsiniz.</p></div></div>
    <a href="{{ route('cart.index') }}" class="checkout-outline-action">Sepete dön ve yeniden dene</a>
  @elseif(in_array($order->status, ['preparing','shipped','out_for_delivery','completed'], true))
    <div class="payment-review-status is-verified"><x-store-icon name="check" /> PAYTR ödeme bildirimi doğrulandı. Siparişinizin güncel durumu: <strong>{{ $tracking['badge'] }}</strong>.</div>
  @endif
</section>
