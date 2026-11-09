<a href="{{ route('product.show', $product->slug) }}" class="product-card">
  <span class="product-card-media">
    @if($product->is_demo)<span class="product-demo-pill">DEMO</span>@endif
    @if($product->discount_percent > 0)<span class="product-discount-pill">-%{{ $product->discount_percent }}</span>@endif
    <img src="{{ $product->image_path ? asset($product->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="{{ $product->name }}" width="360" height="360" loading="lazy">
  </span>
  <span class="product-card-content">
    <span class="product-card-brand">POLARIS KURŞUN</span>
    <span class="product-card-name">{{ $product->name }}</span>
    @if($product->discount_percent > 0)<s class="product-card-old-price">{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s>@endif
    <span class="product-card-bottom"><strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong><span class="product-card-action" aria-hidden="true">↗</span></span>
    @if($product->view_count > 0)<small class="product-card-views">◉ {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</small>@endif
  </span>
</a>
