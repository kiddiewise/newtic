@extends('layouts.store')
@section('title', 'Sepetim | Polaris Kurşun')
@section('content')

<header class="cart-mobile-header" aria-label="Sepet üst menüsü">
  <a href="{{ route('home') }}" class="cart-mobile-back" data-cart-back aria-label="Alışverişe geri dön">
    <x-store-icon name="chevron-left" />
  </a>
  <strong>Sepetim</strong>
  <a href="{{ route('wishlist.index') }}" class="cart-mobile-favorites" aria-label="Favorilerim">
    <x-store-icon name="heart" />
  </a>
</header>

<main class="container cart-page cart-redesign" id="content">
  <nav class="breadcrumbs cart-breadcrumbs" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span><span>Sepetim</span></nav>

  <div class="cart-page-heading">
    <div><p class="eyebrow">POLARIS KURŞUN</p><h1>Sepetim <span>({{ $cart['count'] }} Ürün)</span></h1></div>
    <a href="{{ route('search') }}">Alışverişe devam et <span aria-hidden="true">›</span></a>
  </div>

  @if(session('success'))<div class="cart-flash is-success" role="status">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="cart-flash is-error" role="alert">{{ session('error') }}</div>@endif
  @if($errors->any())
    <div class="cart-flash is-error" role="alert"><strong>Sepet güncellenemedi:</strong>
      @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
  @endif

  @if($cart['lines'] === [])
    <div class="cart-empty">
      <div class="cart-empty-art" aria-hidden="true">
        <x-store-icon name="shopping-bag" />
      </div>
      <h2>Sepetin henüz boş</h2>
      <p>Farklı gramajları ve ürünleri aynı sepette birleştirebilirsin.</p>
      <a class="cart-primary" href="{{ route('search') }}">Ürünleri keşfet</a>
    </div>
  @else
    <div class="cart-shipping-highlight {{ $cart['free_shipping'] ? 'is-free' : '' }}">
      <span class="cart-shipping-highlight-icon" aria-hidden="true">
        <x-store-icon name="truck" />
      </span>
      <div>
        @if($cart['free_shipping'])
          <strong>Bu alışverişinde kargo ücretsiz!</strong>
          <small>Ücretsiz kargo limitine ulaştın.</small>
        @else
          <strong>{{ number_format($cart['remaining_cents'] / 100, 2, ',', '.') }} ₺ daha ekle, kargo ücretsiz olsun.</strong>
          <small>{{ number_format($cart['threshold_cents'] / 100, 2, ',', '.') }} ₺ ve üzeri alışverişte ücretsiz kargo.</small>
          @if($cart['threshold_cents'] > 0)
            <div class="cart-progress" role="progressbar" aria-label="Ücretsiz kargo ilerlemesi" aria-valuemin="0" aria-valuemax="{{ $cart['threshold_cents'] }}" aria-valuenow="{{ min($cart['subtotal_cents'], $cart['threshold_cents']) }}">
              <span style="width:{{ min(100, round($cart['subtotal_cents'] / $cart['threshold_cents'] * 100)) }}%"></span>
            </div>
          @endif
        @endif
      </div>
    </div>

    <div class="cart-columns">
      <div class="cart-items">
        <div class="cart-box-heading">
          <h2>Sepetim <span>({{ $cart['count'] }} Ürün)</span></h2>
          <form method="POST" action="{{ route('cart.clear') }}" onsubmit="return confirm('Sepetteki tüm ürünler kaldırılsın mı?')">
            @csrf <button type="submit" class="cart-text-action">Sepeti boşalt</button>
          </form>
        </div>

        @foreach($cart['lines'] as $line)
          @php
            $item = $line['product'];
            $faved = in_array($item->id, $wishlistIds, true);
          @endphp
          <article class="cart-item" data-cart-item="{{ $item->id }}">
            <a class="cart-item-image" href="{{ route('product.show', $item->slug) }}" aria-label="{{ $item->name }} detayına git">
              <img src="{{ asset($item->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="{{ $item->name }}" width="105" height="105" loading="lazy">
            </a>
            <div class="cart-item-main">
              <a class="cart-item-name" href="{{ route('product.show', $item->slug) }}">{{ $item->name }}</a>
              <div class="cart-item-meta">{{ $item->category->name }} · SKU: {{ $item->sku }}</div>
              <div class="cart-item-price-group">
                <strong class="cart-item-line-total">{{ number_format($line['line_cents'] / 100, 2, ',', '.') }} ₺</strong>
                @if($line['savings_cents'] > 0)
                  <s>{{ number_format($line['regular_cents'] * $line['quantity'] / 100, 2, ',', '.') }} ₺</s>
                @endif
              </div>
              @if($line['savings_cents'] > 0)
                <div class="cart-item-discount">
                  <span class="cart-savings-tag">✓ Kazancın {{ number_format($line['savings_cents'] / 100, 2, ',', '.') }} ₺</span>
                  <span class="cart-discount-percent">-%{{ $item->discount_percent }} indirim</span>
                </div>
              @endif
              <span class="cart-item-unit">{{ number_format($line['unit_cents'] / 100, 2, ',', '.') }} ₺ / adet</span>
            </div>
            <div class="cart-item-actions">
              <form class="cart-update-form cart-quantity-stepper" method="POST" action="{{ route('cart.update', $item) }}" data-cart-update-form>
                @csrf @method('PATCH')
                <button type="button" data-cart-minus aria-label="{{ $item->name }} adedini azalt" @disabled($line['quantity'] <= 1)>−</button>
                <label class="sr-only" for="cart-quantity-{{ $item->id }}">Ürün adedi: {{ $item->name }}</label>
                <input id="cart-quantity-{{ $item->id }}" name="quantity" type="number" value="{{ $line['quantity'] }}" min="1" max="{{ min(999, $item->stock_quantity) }}" step="1" inputmode="numeric" required>
                <button type="button" data-cart-plus aria-label="{{ $item->name }} adedini artır" @disabled($line['quantity'] >= min(999, $item->stock_quantity))>+</button>
                <button class="cart-quantity-save" type="submit">Güncelle</button>
              </form>
              <form method="POST" action="{{ route('cart.remove', $item) }}">
                @csrf @method('DELETE')
                <button class="cart-remove-button" type="submit" aria-label="{{ $item->name }} ürününü sepetten sil">Sil</button>
              </form>
              <form class="cart-favorite-form" method="POST" action="{{ route('wishlist.toggle', $item) }}">
                @csrf
                <button type="submit" class="cart-favorite-button {{ $faved ? 'is-favorite' : '' }}" aria-pressed="{{ $faved ? 'true' : 'false' }}" aria-label="{{ $item->name }} {{ $faved ? 'favorilerden çıkar' : 'favorilere ekle' }}" title="{{ $faved ? 'Favorilerden çıkar' : 'Favorilere ekle' }}">
                  <x-store-icon name="heart" />
                </button>
              </form>
            </div>
          </article>
        @endforeach
      </div>

      <aside class="cart-summary" aria-labelledby="cart-summary-title">
        <h2 id="cart-summary-title">Sepet özeti</h2>
        @include('store.partials.cart-summary-details', ['cart' => $cart])
        <a class="cart-checkout-action" href="{{ route('checkout.index') }}">Siparişi Tamamla</a>
        <p class="cart-checkout-note">Test ortamında ödeme alınmaz; deneme siparişi oluşturulabilir.</p>
      </aside>
    </div>
  @endif
</main>

@if($cart['lines'] !== [])
  <div class="cart-mobile-backdrop" data-cart-summary-backdrop aria-hidden="true"></div>
  <section class="cart-mobile-summary-sheet" id="mobile-cart-summary" data-cart-summary-sheet aria-labelledby="mobile-cart-summary-heading" aria-hidden="true">
    <div class="cart-sheet-handle" aria-hidden="true"></div>
    <div class="cart-sheet-heading">
      <h2 id="mobile-cart-summary-heading">Sepet Özeti</h2>
      <button type="button" class="cart-sheet-close" data-cart-summary-close aria-label="Sepet özetini kapat">✕</button>
    </div>
    <div class="cart-sheet-scroll">
      @include('store.partials.cart-summary-details', ['cart' => $cart])
      <p class="cart-checkout-note">Teslimat adresini girerek deneme siparişi oluşturabilirsin.</p>
    </div>
    <div class="cart-sheet-footer">
      <a class="cart-sheet-continue" href="{{ route('checkout.index') }}">Siparişi Tamamla</a>
    </div>
  </section>
  <div class="cart-mobile-bottom" aria-label="Sepet toplamı ve özeti">
    <button class="cart-mobile-total" type="button" data-cart-summary-open aria-controls="mobile-cart-summary" aria-expanded="false">
      <span class="cart-mobile-price">{{ number_format($cart['total_cents'] / 100, 2, ',', '.') }} ₺</span>
      <span class="cart-mobile-shipping">{{ $cart['shipping_cents'] === 0 ? 'Kargo bedava' : 'Kargo dahil' }}</span>
      <x-store-icon name="chevron-up" />
    </button>
    <a class="cart-mobile-summary-open" href="{{ route('checkout.index') }}">Siparişi Tamamla <span aria-hidden="true">›</span></a>
  </div>
@endif
@endsection
