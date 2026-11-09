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
          <x-store-icon name="shopping-cart-plus" />
        </button>
      </form>
    </div>
    @if($product->view_count > 0)<small class="product-card-views">◉ {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</small>@endif
  </div>
</article>
