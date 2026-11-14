<article class="product-card">
  <a href="{{ route('product.show', $product->slug) }}" class="product-card-media" aria-label="{{ $product->name }} ürün detayını görüntüle">
    @if($product->discount_percent > 0)<span class="product-discount-pill">-%{{ $product->discount_percent }}</span>@endif
    <img src="{{ $product->image_path ? asset($product->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="{{ $product->name }}" width="360" height="360" loading="lazy">
  </a>
  @include('store.partials.product-badges', ['product' => $product, 'placement' => 'card'])
  <div class="product-card-content">
    <span class="product-card-brand">POLARIS KURŞUN</span>
    <a href="{{ route('product.show', $product->slug) }}" class="product-card-name">{{ $product->name }}</a>
    @if((int) ($product->review_count ?? 0) > 0)
      <a class="product-card-rating" href="{{ route('product.show', $product->slug) }}#urun-yorumlari"
        aria-label="{{ $product->name }}: 5 üzerinden {{ number_format((float) $product->review_average, 1, ',', '.') }} yıldız, {{ (int) $product->review_count }} yorum. Yorumları görüntüle">
        <span class="product-card-rating-star" aria-hidden="true">★</span>
        <strong>{{ number_format((float) $product->review_average, 1, ',', '.') }}</strong>
        <span class="product-card-rating-count">{{ number_format((int) $product->review_count, 0, ',', '.') }} yorum</span>
      </a>
    @endif
    @if($product->discount_percent > 0)<s class="product-card-old-price">{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s>@endif
    <div class="product-card-bottom">
      <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
      @if($product->options->isNotEmpty())
      <a class="product-card-action product-card-option-link" href="{{ route('product.show', $product->slug) }}" aria-label="{{ $product->name }} için ürün seçeneği belirle">
        <x-store-icon name="shopping-cart" />
        <span class="product-card-action-label">{{ $product->stock_quantity > 0 ? 'Seçenek Seç' : 'Tükendi' }}</span>
      </a>
      @else
      <form class="product-card-add-form" method="POST" action="{{ route('cart.add') }}" data-quick-add data-quick-product="{{ $product->id }}">
        @csrf
        <input type="hidden" name="items[{{ $product->id }}]" value="1">
        <button class="product-card-action" type="submit" aria-label="{{ $product->name }} sepete ekle" title="{{ $product->stock_quantity > 0 ? 'Sepete ekle' : 'Tükendi' }}" @disabled($product->stock_quantity <= 0)>
          <x-store-icon name="shopping-cart" />
          <span class="product-card-action-label">{{ $product->stock_quantity > 0 ? 'Sepete Ekle' : 'Tükendi' }}</span>
        </button>
      </form>
      @endif
    </div>
    @if($product->stock_quantity > 0 && $product->stock_quantity <= 10)
      <small class="product-card-low-stock">Son {{ $product->stock_quantity }} ürün</small>
    @endif
  </div>
</article>
