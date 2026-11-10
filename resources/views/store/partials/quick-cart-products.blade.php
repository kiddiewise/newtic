<div class="quick-cart-selected">
  @php $anchorQuantity = (int) ($cartQuantities[$anchor->id] ?? 0); @endphp
  <div class="quick-cart-product-row is-selected" data-quick-row data-product-id="{{ $anchor->id }}" data-quantity="{{ $anchorQuantity }}" data-stock="{{ (int) $anchor->stock_quantity }}"
    data-add-url="{{ route('cart.add') }}" data-update-url="{{ route('cart.update', $anchor) }}">
    <a class="quick-cart-image" href="{{ route('product.show', $anchor->slug) }}">
      <img src="{{ $anchor->image_path ? asset($anchor->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="" width="74" height="74" loading="lazy">
    </a>
    <div class="quick-cart-product-info">
      <span class="quick-cart-product-caption">Seçtiğin ürün</span>
      <a href="{{ route('product.show', $anchor->slug) }}" class="quick-cart-product-name">{{ $anchor->name }}</a>
      <div class="quick-cart-price-line">
        <strong>{{ number_format((float) $anchor->price, 2, ',', '.') }} ₺</strong>
        @if($anchor->discount_percent > 0)<span>-%{{ $anchor->discount_percent }}</span>@endif
      </div>
    </div>
    @if($anchor->options->isNotEmpty())
      <div class="quick-cart-variant-choice">
        <small>Sepette {{ $anchorQuantity }} adet · {{ $anchor->option_label }} seçimi gerekli</small>
        <a href="{{ route('product.show', $anchor->slug) }}">Seçenekleri görüntüle</a>
      </div>
    @else
    <div class="quick-cart-stepper" aria-label="{{ $anchor->name }} adedi">
      <button type="button" data-quick-minus aria-label="{{ $anchor->name }} azalt" @disabled($anchorQuantity <= 0)>−</button>
      <output data-quick-quantity aria-label="Sepetteki adet">{{ $anchorQuantity }}</output>
      <button type="button" data-quick-plus aria-label="{{ $anchor->name }} artır" @disabled($anchorQuantity >= min(999, $anchor->stock_quantity))>+</button>
    </div>
    @endif
  </div>
</div>

@if($cart['free_shipping'])
  <div class="quick-cart-shipping is-free">
    <x-store-icon name="truck" />
    <span><strong>Kargo ücretsiz!</strong><small>Ücretsiz kargo limitine ulaştın.</small></span>
  </div>
@elseif($cart['count'] > 0)
  <div class="quick-cart-shipping">
    <x-store-icon name="truck" />
    <span><strong>{{ number_format($cart['remaining_cents'] / 100, 2, ',', '.') }} ₺ daha ekle, kargo ücretsiz!</strong><small>Kargo koşulları sepet tutarına göre güncellenir.</small></span>
    @if($cart['threshold_cents'] > 0)
      <span class="quick-cart-progress"><span style="width:{{ min(100, round(100 * $cart['subtotal_cents'] / $cart['threshold_cents'])) }}%"></span></span>
    @endif
  </div>
@endif

@if($products->count() > 1)
  <div class="quick-cart-related-head">
    <div><span class="quick-cart-section-kicker">BİRLİKTE ALINABİLİR</span><h3>Aynı koleksiyondan diğer ürünler</h3></div>
    <a href="{{ route('category.show', $root->slug) }}">Tümünü gör</a>
  </div>
  <div class="quick-cart-related-list">
    @foreach($products->skip(1) as $related)
      @php $quantity = (int) ($cartQuantities[$related->id] ?? 0); @endphp
      <div class="quick-cart-product-row is-related" data-quick-row data-product-id="{{ $related->id }}"
        data-quantity="{{ $quantity }}" data-stock="{{ (int) $related->stock_quantity }}"
        data-add-url="{{ route('cart.add') }}" data-update-url="{{ route('cart.update', $related) }}">
        <a class="quick-cart-image" href="{{ route('product.show', $related->slug) }}">
          <img src="{{ $related->image_path ? asset($related->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="" width="62" height="62" loading="lazy">
        </a>
        <div class="quick-cart-product-info">
          <span class="quick-cart-product-caption">{{ $related->category?->name }}</span>
          <a href="{{ route('product.show', $related->slug) }}" class="quick-cart-product-name">{{ $related->name }}</a>
          <div class="quick-cart-price-line"><strong>{{ number_format((float) $related->price, 2, ',', '.') }} ₺</strong>
          @if($related->discount_percent > 0)<span>-%{{ $related->discount_percent }}</span>@endif</div>
        </div>
        @if($related->options->isNotEmpty())
      <div class="quick-cart-variant-choice">
        <small>{{ $quantity }} adet · {{ $related->option_label }} seçimi gerekli</small>
        <a href="{{ route('product.show', $related->slug) }}">Seçenekleri görüntüle</a>
      </div>
    @else
    <div class="quick-cart-stepper" aria-label="{{ $related->name }} adedi">
          <button type="button" data-quick-minus aria-label="{{ $related->name }} azalt" @disabled($quantity <= 0)>−</button>
          <output data-quick-quantity aria-label="Sepetteki adet">{{ $quantity }}</output>
          <button type="button" data-quick-plus aria-label="{{ $related->name }} artır" @disabled($quantity >= min(999, $related->stock_quantity))>+</button>
        </div>
    @endif
      </div>
    @endforeach
  </div>
@endif
