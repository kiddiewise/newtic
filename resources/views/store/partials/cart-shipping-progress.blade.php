@php
  $threshold = max(0, (int) $cart['threshold_cents']);
  $progress = $cart['free_shipping'] ? 100 : ($threshold > 0
    ? (int) min(100, round($cart['subtotal_cents'] * 100 / $threshold)) : 0);
@endphp
<section class="cart-shipping-panel {{ $cart['free_shipping'] ? 'is-complete' : '' }}" aria-label="Ücretsiz kargo ilerlemesi">
  <div class="cart-shipping-panel-copy">
    @if($cart['free_shipping'])
      <strong>Kargon bizden!</strong>
      <small>Sepetin ücretsiz kargo limitine ulaştı.</small>
    @else
      <strong>{{ number_format($cart['remaining_cents'] / 100, 2, ',', '.') }} ₺ daha ekle, kargon ücretsiz olsun.</strong>
      <small>{{ number_format($threshold / 100, 2, ',', '.') }} ₺ ve üzeri ücretsiz kargo</small>
    @endif
  </div>
  @if($threshold > 0)
    <div class="cart-shipping-meter"
      role="progressbar" aria-label="Ücretsiz kargo hedefi"
      aria-valuemin="0" aria-valuemax="{{ $threshold }}"
      aria-valuenow="{{ min((int) $cart['subtotal_cents'], $threshold) }}"
      style="--shipping-progress: {{ $progress }}%">
      <div class="cart-shipping-meter-track" aria-hidden="true">
        <span class="cart-shipping-meter-fill"></span>
        <span class="cart-shipping-truck"><x-store-icon name="truck" /></span>
      </div>
    </div>
  @endif
</section>
