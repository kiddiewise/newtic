<div class="product-grid" data-search-product-grid>
  @forelse($products as $product)
    @include('store.partials.product-card', ['product' => $product])
  @empty
    <p class="nothing-found">Aradığınız kriterde ürün bulunamadı.</p>
  @endforelse
</div>
<div class="pagination-wrap">{{ $products->links() }}</div>
