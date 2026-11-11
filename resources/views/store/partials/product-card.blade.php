<article class="product-card">
  <a href="{{ route('product.show', $product->slug) }}" class="product-card-media" aria-label="{{ $product->name }} ürün detayını görüntüle">
    @if($product->is_demo)<span class="product-demo-pill">DEMO</span>@endif
    @if($product->discount_percent > 0)<span class="product-discount-pill">-%{{ $product->discount_percent }}</span>@endif
    <img src="{{ $product->image_path ? asset($product->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="{{ $product->name }}" width="360" height="360" loading="lazy">
  </a>
  <div class="product-card-content">
    <span class="product-card-brand">POLARIS KURŞUN</span>
    <a href="{{ route('product.show', $product->slug) }}" class="product-card-name">{{ $product->name }}</a>
    @if((int) ($product->review_count ?? 0) > 0)
      <a class="product-card-rating" href="{{ route('product.show', $product->slug) }}#urun-yorumlari"
        aria-label="{{ $product->name }}: 5 üzerinden {{ number_format((float) $product->review_average, 1, ',', '.') }} yıldız, {{ (int) $product->review_count }} yorum. Yorumları görüntüle">
        <span class="product-card-rating-star" aria-hidden="true">★</span>
        <strong>{{ number_format((float) $product->review_average, 1, ',', '.') }}</strong>
        <span class="product-card-rating-count">{{ number_format((int) $product->review_count, 0, ',', '.') }} yorum</span>
        @if((int) ($product->review_test_count ?? 0) > 0)
          <span class="product-card-rating-test">TEST</span>
        @endif
      </a>
    @endif
    @if($product->discount_percent > 0)<s class="product-card-old-price">{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s>@endif
    <div class="product-card-bottom">
      <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
      @if($product->options->isNotEmpty())
      <a class="product-card-action product-card-option-link" href="{{ route('product.show', $product->slug) }}" aria-label="{{ $product->name }} için ürün seçeneği belirle">
        <x-store-icon name="shopping-cart" />
        <span class="product-card-action-label">{{ $product->stock_quantity > 0 ? 'Seçenek Seç' : 'Stokta Yok' }}</span>
      </a>
      @else
      <form class="product-card-add-form" method="POST" action="{{ route('cart.add') }}" data-quick-add data-quick-product="{{ $product->id }}">
        @csrf
        <input type="hidden" name="items[{{ $product->id }}]" value="1">
        <button class="product-card-action" type="submit" aria-label="{{ $product->name }} sepete ekle" title="{{ $product->stock_quantity > 0 ? 'Sepete ekle' : 'Stokta yok' }}" @disabled($product->stock_quantity <= 0)>
          <x-store-icon name="shopping-cart" />
          <span class="product-card-action-label">{{ $product->stock_quantity > 0 ? 'Sepete Ekle' : 'Stokta Yok' }}</span>
        </button>
      </form>
      @endif
    </div>
    @if($product->view_count > 0)<small class="product-card-views">◉ {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</small>@endif
  </div>
</article>
