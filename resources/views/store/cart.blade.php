@extends('layouts.store')
@section('title', 'Sepetim | Polaris Kurşun')
@section('content')
<main class="container cart-page" id="content">
  <nav class="breadcrumbs" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span><span>Sepetim</span></nav>

  <div class="cart-page-heading">
    <div><p class="eyebrow">POLARIS KURŞUN</p><h1>Sepetim <span>{{ $cart['count'] }} adet</span></h1></div>
    <a href="{{ route('search') }}">Alışverişe devam et <span aria-hidden="true">›</span></a>
  </div>

  @if(session('success'))<div class="cart-flash is-success" role="status">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="cart-flash is-error" role="alert">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="cart-flash is-error" role="alert">
    <strong>Sepet güncellenemedi:</strong>
    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
  </div>@endif

  @if($cart['lines'] === [])
    <div class="cart-empty">
      <div class="cart-empty-art" aria-hidden="true">▤</div>
      <h2>Sepetin henüz boş</h2>
      <p>İstediğin gramajları ve farklı ürünleri birlikte seçebilirsin.</p>
      <a class="cart-primary" href="{{ route('search') }}">Ürünleri keşfet</a>
    </div>
  @else
    <div class="cart-columns">
      <div class="cart-items">
        <div class="cart-box-heading">
          <h2>Sepetteki ürünler</h2>
          <form method="POST" action="{{ route('cart.clear') }}" onsubmit="return confirm('Sepetteki tüm ürünler kaldırılsın mı?')">
            @csrf <button type="submit" class="cart-text-action">Sepeti boşalt</button>
          </form>
        </div>
        @foreach($cart['lines'] as $line)
          @php $item = $line['product']; @endphp
          <article class="cart-item">
            <a class="cart-item-image" href="{{ route('product.show', $item->slug) }}">
              <img src="{{ asset($item->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="{{ $item->name }}" width="105" height="105" loading="lazy">
            </a>
            <div class="cart-item-main">
              <a class="cart-item-name" href="{{ route('product.show', $item->slug) }}">{{ $item->name }}</a>
              <div class="cart-item-meta">{{ $item->category->name }} · SKU: {{ $item->sku }}</div>
              @if($item->discount_percent > 0)<div class="cart-item-discount"><s>{{ number_format((float) $item->compare_at_price, 2, ',', '.') }} ₺</s><span>-%{{ $item->discount_percent }}</span></div>@endif
              <strong class="cart-item-unit">{{ number_format($line['unit_cents'] / 100, 2, ',', '.') }} ₺ / adet</strong>
              <div class="cart-item-actions">
                <form class="cart-update-form" method="POST" action="{{ route('cart.update', $item) }}" data-cart-update-form>
                  @csrf @method('PATCH')
                  <label class="sr-only" for="cart-quantity-{{ $item->id }}">Ürün adedi: {{ $item->name }}</label>
                  <input id="cart-quantity-{{ $item->id }}" name="quantity" type="number" value="{{ $line['quantity'] }}" min="1" max="{{ min(999, $item->stock_quantity) }}" step="1" inputmode="numeric" required>
                  <button type="submit">Güncelle</button>
                </form>
                <form method="POST" action="{{ route('cart.remove', $item) }}">
                  @csrf @method('DELETE')
                  <button class="cart-remove-button" type="submit">Kaldır</button>
                </form>
              </div>
            </div>
            <strong class="cart-item-line-total">{{ number_format($line['line_cents'] / 100, 2, ',', '.') }} ₺</strong>
          </article>
        @endforeach
      </div>

      <aside class="cart-summary" aria-labelledby="cart-summary-title">
        <h2 id="cart-summary-title">Sipariş özeti</h2>
        @if($cart['free_shipping'])
          <div class="cart-shipping-notice is-free"><strong>Ücretsiz kargo kazandın!</strong><span>Sepetin ücretsiz kargo koşulunu sağlıyor.</span></div>
        @elseif($cart['threshold_cents'] > 0)
          <div class="cart-shipping-notice">
            <strong>{{ number_format($cart['remaining_cents'] / 100, 2, ',', '.') }} ₺ daha ekle, kargo ücretsiz olsun!</strong>
            <div class="cart-progress" role="progressbar" aria-label="Ücretsiz kargoya kalan tutar" aria-valuemin="0" aria-valuemax="{{ $cart['threshold_cents'] }}" aria-valuenow="{{ min($cart['subtotal_cents'], $cart['threshold_cents']) }}">
              <span style="width: {{ min(100, round($cart['subtotal_cents'] / $cart['threshold_cents'] * 100)) }}%"></span>
            </div>
            <small>{{ number_format($cart['threshold_cents'] / 100, 2, ',', '.') }} ₺ ve üzeri ücretsiz kargo</small>
          </div>
        @else
          <div class="cart-shipping-notice is-free"><strong>Ücretsiz kargo</strong></div>
        @endif
        <dl class="cart-totals">
          <div><dt>Ürünler ({{ $cart['count'] }} adet)</dt><dd>{{ number_format($cart['subtotal_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
          <div><dt>Kargo</dt><dd class="{{ $cart['shipping_cents'] === 0 ? 'is-free' : '' }}">{{ $cart['shipping_cents'] === 0 ? 'Ücretsiz' : number_format($cart['shipping_cents'] / 100, 2, ',', '.').' ₺' }}</dd></div>
          <div class="cart-grand-total"><dt>Genel toplam</dt><dd>{{ number_format($cart['total_cents'] / 100, 2, ',', '.') }} ₺</dd></div>
        </dl>
        <button class="cart-checkout-disabled" type="button" disabled>Ödeme yakında</button>
        <p class="cart-checkout-note">Sepet işlevi aktif. Sipariş oluşturma ve ödeme alma henüz bu test ortamında etkin değildir.</p>
      </aside>
    </div>
  @endif
</main>
@endsection
