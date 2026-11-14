@extends('layouts.store')
@section('title', 'PAYTR Test Ödemesi | Polaris Kurşun')
@section('content')
<main class="checkout-shell paytr-payment-page" id="content">
  <div class="checkout-top">
    <a href="{{ route('checkout.success', $order) }}" aria-label="Sipariş bilgilerine dön">‹</a>
    <strong>Güvenli Test Ödemesi</strong>
    <span><x-store-icon name="landmark" /></span>
  </div>
  <div class="paytr-checkout-heading">
    <div class="paytr-test-tag"><x-store-icon name="check" /> PAYTR TEST MODU</div>
    <h1>Kartla test ödemesi</h1>
    <p>Ödeme işlemi PAYTR'nin güvenli ödeme penceresinde tamamlanır. Kart bilgileri NEWTIC'e kaydedilmez.</p>
  </div>
  <div class="checkout-main-grid paytr-checkout-grid">
    <section class="checkout-card paytr-iframe-panel" aria-labelledby="paytr-form-title">
      <div class="paytr-iframe-top">
        <span><x-store-icon name="check" /> PAYTR Güvenli Ödeme</span>
        <span class="paytr-test-indicator">Test · Gerçek tahsilat yok</span>
      </div>
      <h2 id="paytr-form-title">Ödeme ekranı</h2>
      @if($token)
        <div class="paytr-frame-viewport">
          <iframe src="https://www.paytr.com/odeme/guvenli/{{ $token }}"
            id="paytriframe" name="paytriframe" title="PAYTR güvenli test kartı ödeme formu"
            loading="eager" referrerpolicy="no-referrer"
            scrolling="no" style="display:block;width:100%;min-height:520px;border:0"></iframe>
        </div>
        <script src="https://www.paytr.com/js/iframeResizer.min.js" defer></script>
        <script>
          window.addEventListener('load', function () {
            if (typeof window.iFrameResize === 'function') {
              window.iFrameResize({checkOrigin: ['https://www.paytr.com']}, '#paytriframe');
            }
          });
        </script>
        <p class="paytr-secure-note"><x-store-icon name="landmark" /> Kart bilgilerinizi yalnızca PAYTR ekranına girin.</p>
      @else
        <div class="paytr-token-error" role="alert">
          <x-store-icon name="x" />
          <div><strong>Ödeme formu açılamadı</strong><p>{{ $message }}</p></div>
        </div>
        @if($order->status === 'pending_payment')
          <a class="checkout-action paytr-retry-cta" href="{{ request()->fullUrl() }}">Tekrar dene</a>
        @endif
      @endif
      <div class="paytr-iframe-help">
        <strong>Ödeme sonrası</strong>
        <p>PAYTR sunucumuzla sonucu doğruladıktan sonra sipariş durumunuz güncellenir. Yalnızca ödeme ekranındaki yönlendirme başarı onayı değildir.</p>
        <a class="checkout-outline-action" href="{{ $paymentReturnUrl }}">Sipariş durumunu görüntüle</a>
      </div>
    </section>
    <aside class="checkout-card paytr-order-summary">
      <span class="payment-eyebrow">TEST SİPARİŞİ</span>
      <h2>Ödeme özeti</h2>
      <p class="paytr-order-number">{{ $order->order_number }}</p>
      @foreach($order->items as $item)
        <div class="checkout-order-item">
          <span>{{ $item->product_name }}
            @if($item->option_value)<small>· {{ $item->option_label }}: {{ $item->option_value }}</small>@endif
            <small>× {{ $item->quantity }}</small>
          </span>
          <strong>{{ number_format($item->line_total_cents / 100, 2, ',', '.') }} ₺</strong>
        </div>
      @endforeach
      <div class="checkout-price-line"><span>Ürünler</span><strong>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</strong></div>
      <div class="checkout-price-line"><span>Kargo</span><strong>{{ $order->shipping_cents ? number_format($order->shipping_cents / 100, 2, ',', '.').' ₺' : 'Ücretsiz' }}</strong></div>
      <div class="checkout-price-line is-total"><span>Toplam</span><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></div>
      <p class="paytr-test-disclaimer">Bu işlem PAYTR test modu ile yapılır. Gerçek kredi kartıyla ödeme gerçekleştirmeyin; PAYTR'nin test senaryolarını kullanın.</p>
    </aside>
  </div>
</main>
@endsection
