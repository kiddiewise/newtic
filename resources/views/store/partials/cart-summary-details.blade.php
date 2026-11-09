@if($cart['free_shipping'])
  <div class="cart-shipping-notice is-free"><strong>Ücretsiz kargo kazandın!</strong><span>Sepetin ücretsiz kargo koşulunu sağlıyor.</span></div>
@elseif($cart['threshold_cents'] > 0)
  <div class="cart-shipping-notice">
    <strong>{{ number_format($cart['remaining_cents'] / 100, 2, ',', '.') }} ₺ daha ekle, kargo ücretsiz olsun!</strong>
    <div class="cart-progress" role="progressbar" aria-label="Ücretsiz kargo ilerlemesi" aria-valuemin="0" aria-valuemax="{{ $cart['threshold_cents'] }}" aria-valuenow="{{ min($cart['subtotal_cents'], $cart['threshold_cents']) }}">
      <span style="width:{{ min(100, round($cart['subtotal_cents'] / $cart['threshold_cents'] * 100)) }}%"></span>
    </div>
    <small>{{ number_format($cart['threshold_cents'] / 100, 2, ',', '.') }} ₺ ve üzeri ücretsiz kargo</small>
  </div>
@endif
<dl class="cart-totals">
  @if($cart['savings_cents'] > 0)
    <div><dt>Ürünler toplamı</dt><dd>{{ number_format($cart['regular_subtotal_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
    <div class="cart-summary-savings"><dt>İndirim kazancın</dt><dd>−{{ number_format($cart['savings_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
  @else
    <div><dt>Ürünler ({{ $cart['count'] }} adet)</dt><dd>{{ number_format($cart['subtotal_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
  @endif
  <div><dt>Kargo ücreti</dt>
    <dd class="{{ $cart['shipping_cents'] === 0 ? 'is-free' : '' }}">
      @if($cart['shipping_cents'] === 0)
        @if($cart['shipping_fee_cents'] > 0)<s class="cart-shipping-old">{{ number_format($cart['shipping_fee_cents'] / 100, 2, ',', '.') }} ₺</s>@endif
        Ücretsiz
      @else
        {{ number_format($cart['shipping_cents'] / 100, 2, ',', '.') }} ₺
      @endif
    </dd>
  </div>
  <div class="cart-grand-total"><dt>Ödenecek tutar</dt><dd>{{ number_format($cart['total_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
</dl>
@if($cart['savings_cents'] > 0)
  <div class="cart-total-savings">✓ Bu alışverişte {{ number_format($cart['savings_cents'] / 100, 2, ',', '.') }} ₺ indirim kazandın</div>
@endif
