<article class="product-card">
  <a href="{{ route('product.show', $product->slug) }}" class="product-card-media" aria-label="{{ $product->name }} ürün detayını görüntüle">
    @if($product->is_demo)<span class="product-demo-pill">DEMO</span>@endif
    @if($product->discount_percent > 0)<span class="product-discount-pill">-%{{ $product->discount_percent }}</span>@endif
    <img src="{{ $product->image_path ? asset($product->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="{{ $product->name }}" width="360" height="360" loading="lazy">
  </a>
  <div class="product-card-content">
    <span class="product-card-brand">POLARIS KURŞUN</span>
    <a href="{{ route('product.show', $product->slug) }}" class="product-card-name">{{ $product->name }}</a>
    @if($product->discount_percent > 0)<s class="product-card-old-price">{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s>@endif
    <div class="product-card-bottom">
      <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
      <form class="product-card-add-form" method="POST" action="{{ route('cart.add') }}" data-quick-add data-quick-product="{{ $product->id }}">
        @csrf
        <input type="hidden" name="items[{{ $product->id }}]" value="1">
        <button class="product-card-action" type="submit" aria-label="{{ $product->name }} sepete ekle" title="{{ $product->stock_quantity > 0 ? 'Sepete ekle' : 'Stokta yok' }}" @disabled($product->stock_quantity <= 0)>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 4h2l2.4 10.5h12l2-7H6"/>
            <circle cx="10" cy="19.5" r="1.15"/><circle cx="19" cy="19.5" r="1.15"/>
            <path d="M15.5 2.5v6M12.5 5.5h6"/>
          </svg>
        </button>
      </form>
    </div>
    @if($product->view_count > 0)<small class="product-card-views">◉ {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</small>@endif
  </div>
</article>
