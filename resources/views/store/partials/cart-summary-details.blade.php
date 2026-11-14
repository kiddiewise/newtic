@include('store.partials.cart-shipping-progress', ['cart' => $cart])
<dl class="cart-totals">
  @if($cart['savings_cents'] > 0)
    <div><dt>Ürünler toplamı</dt><dd>{{ number_format($cart['regular_subtotal_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
    <div class="cart-summary-savings"><dt>İndirim toplamı</dt><dd>−{{ number_format($cart['savings_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
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
  <div class="cart-total-savings"><x-store-icon name="tag" /><span>Bu alışverişte {{ number_format($cart['savings_cents'] / 100, 2, ',', '.') }} ₺ indirim kazandın</span></div>
@endif
