<section class="catalog-infinite" data-infinite-catalog
  data-infinite-next-url="{{ $products->nextPageUrl() }}"
  data-infinite-total="{{ $products->total() }}">
  <div class="product-grid" data-infinite-product-grid data-search-product-grid>
    @forelse($products as $product)
      @include('store.partials.product-card', ['product' => $product])
    @empty
      <p class="nothing-found">{{ $emptyMessage ?? 'Ürün bulunamadı.' }}</p>
    @endforelse
  </div>
  {{-- Server-rendered links remain usable for browsers without JavaScript. --}}
  <nav class="pagination-wrap" data-infinite-pagination aria-label="Ürün sayfaları">{{ $products->links() }}</nav>
  <div class="catalog-load-more" data-infinite-trigger aria-live="polite" aria-atomic="true" hidden>
    <span class="catalog-load-spinner" aria-hidden="true"></span>
    <span data-infinite-status>Diğer ürünler yükleniyor…</span>
  </div>
</section>
