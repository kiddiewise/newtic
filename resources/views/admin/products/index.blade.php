@extends('admin.layout')
@section('title', 'Ürünler | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div><p class="admin-eyebrow">KATALOG / ÜRÜNLER</p><h1>Ürün yönetimi</h1>
    <p>Ürün fotoğraflarını, fiyatlarını, stok ve yayın durumlarını kolayca yönet.</p></div>
  <div class="admin-heading-action-group">
    <a class="admin-secondary" href="{{ route('admin.products.bulk.export') }}"><x-admin-icon name="package" /> Excel'e aktar (CSV)</a>
    <a class="admin-secondary" href="{{ route('admin.products.bulk.index') }}"><x-admin-icon name="layers" /> Toplu ürün yükle</a>
    <a class="admin-secondary" href="{{ route('admin.product-imports.index') }}"><x-admin-icon name="layers" /> WooCommerce'den aktar</a>
    <a class="admin-primary" href="{{ route('admin.products.create') }}"><x-admin-icon name="plus" /> Yeni ürün ekle</a>
  </div>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Ürün listesi</h2><span>{{ number_format($products->total(), 0, ',', '.') }} ürün</span></div>
  <form class="admin-catalog-search" method="GET" action="{{ route('admin.products.index') }}" role="search">
    <input type="hidden" name="status" value="{{ $status }}">
    <input type="search" name="q" value="{{ $term }}" maxlength="100" placeholder="Ürün adı veya SKU ile ara" aria-label="Ürün adı veya SKU ile ara">
    <button class="admin-secondary" type="submit"><x-admin-icon name="search" /> Ara</button>
    @if($term)<a class="admin-secondary" href="{{ route('admin.products.index', ['status' => $status]) }}" title="Aramayı temizle">Temizle</a>@endif
  </form>
  <nav class="admin-catalog-filters" aria-label="Ürün filtreleri">
    @foreach(['all' => 'Tüm ürünler', 'active' => 'Yayında', 'inactive' => 'Pasif', 'low_stock' => 'Düşük stok'] as $key => $label)
      <a href="{{ route('admin.products.index', array_filter(['status' => $key, 'q' => $term])) }}"
        @class(['admin-filter-pill', 'is-active' => $status === $key]) @if($status === $key) aria-current="page" @endif>
        @if($key === 'low_stock')<x-admin-icon name="alert" />@endif{{ $label }}
      </a>
    @endforeach
  </nav>
  <div class="admin-catalog-list">
    @forelse($products as $product)
      <article class="admin-catalog-row">
        <img class="admin-catalog-photo" src="{{ asset($product->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="" loading="lazy" width="68" height="68">
        <div class="admin-catalog-info">
          <strong>{{ $product->name }}</strong>
          <small>SKU: {{ $product->sku }} · {{ $product->category?->name ?: 'Kategori yok' }}</small>
          <div class="admin-catalog-stats">
            <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
            @if($product->discount_percent > 0)
              <s>{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s>
              <span class="admin-sale-tag">-%{{ $product->discount_percent }}</span>
            @endif
            @if($product->tracks_stock)
              <span @class(['admin-stock-badge','is-low' => $product->stock_quantity <= 10])>Stok: {{ $product->stock_quantity }}</span>
            @else
              <span class="admin-stock-badge">Stok takibi kapalı</span>
            @endif
            <span class="admin-status {{ $product->is_active ? 'is-active' : '' }}">{{ $product->is_active ? 'Yayında' : 'Pasif' }}</span>
            <span><x-admin-icon name="chart" /> {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</span>
          </div>
        </div>
        <a class="admin-secondary" href="{{ route('admin.products.edit', $product) }}"><x-admin-icon name="settings" /> Düzenle</a>
      </article>
    @empty
      <div class="admin-empty"><x-admin-icon name="package" /><strong>Bu filtrede ürün bulunamadı.</strong>
        <p>Arama veya filtreyi değiştirebilir, yeni ürün ekleyebilirsin.</p>
      </div>
    @endforelse
  </div>
  <div class="pagination-wrap">{{ $products->links() }}</div>
</div>
<p class="admin-description">Görüntülenme sayısı 24 saatlik oturum filtrelemesiyle hesaplanır; kesin tekil kişi sayısı değildir. Her gramaj ayrı ürün olarak yönetilir.</p>
@endsection
