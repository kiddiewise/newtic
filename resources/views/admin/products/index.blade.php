@extends('admin.layout')
@section('title', 'Ürünler | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div><p class="admin-eyebrow">KATALOG YÖNETİMİ</p><h1>Ürünler</h1><p>Ürün, fiyat, stok, fotoğraf ve görüntülenmeleri yönetin.</p></div>
  <a class="admin-primary" href="{{ route('admin.products.create') }}">+ Yeni ürün</a>
</div>
<div class="admin-panel">
  <form class="admin-catalog-search" method="GET" action="{{ route('admin.products.index') }}">
    <input type="search" name="q" value="{{ $term }}" maxlength="100" placeholder="Ürün adı veya stok kodu ara">
    <button class="admin-secondary" type="submit">Ara</button>
  </form>
  <div class="admin-panel-title"><h2>Ürün listesi</h2><span>{{ $products->total() }} ürün</span></div>
  @forelse($products as $product)
    <div class="admin-catalog-row">
      <img class="admin-catalog-photo" src="{{ asset($product->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="" loading="lazy" width="68" height="68">
      <div class="admin-catalog-info">
        <strong>{{ $product->name }}</strong>
        <small>{{ $product->sku }} · {{ $product->category?->name }} · Stok: {{ $product->stock_quantity }}</small>
        <div class="admin-catalog-stats">
          <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
          @if($product->discount_percent > 0)
            <s>{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s>
            <span class="admin-sale-tag">-%{{ $product->discount_percent }}</span>
          @endif
          <span>◉ {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</span>
          <span class="admin-status {{ $product->is_active ? 'is-active' : '' }}">{{ $product->is_active ? 'Yayında' : 'Pasif' }}</span>
        </div>
      </div>
      <a class="admin-secondary" href="{{ route('admin.products.edit', $product) }}">Düzenle</a>
    </div>
  @empty
    <p class="admin-empty">Henüz ürün bulunamadı. İlk ürününüzü oluşturun.</p>
  @endforelse
  <div class="pagination-wrap">{{ $products->links() }}</div>
</div>
<p class="admin-description">Görüntülenmeler: Bir ziyaretçinin aynı ürüne 24 saat içinde tekrar bakması yeniden sayılmaz. Bu sayı kesin tekil kişi sayısı değildir.</p>
@endsection
