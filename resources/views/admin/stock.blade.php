@extends('admin.layout')
@section('title','Stok Yönetimi | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions"><div><p class="admin-eyebrow">KATALOG / ENVANTER</p><h1>Stok Yönetimi</h1><p>Stokları takip et; değişiklikleri ürünün kendi düzenleme ekranında güvenle yap.</p></div>
  <a class="admin-primary" href="{{ route('admin.products.create') }}"><x-admin-icon name="plus" /> Yeni ürün ekle</a></div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Stok durumu</h2><span>{{ $products->total() }} ürün</span></div>
  <nav class="admin-catalog-filters" aria-label="Stok filtreleri">
    @foreach(['low'=>'Düşük stok (≤10)','empty'=>'Stokta yok','all'=>'Tüm ürünler'] as $value=>$label)
      <a href="{{ route('admin.stock', array_filter(['filter'=>$value,'q'=>$term])) }}" @class(['admin-filter-pill','is-active'=>$filter===$value])>{{ $label }}</a>
    @endforeach
  </nav>
  <form class="admin-catalog-search" action="{{ route('admin.stock') }}" method="GET" role="search">
    <input type="hidden" name="filter" value="{{ $filter }}"><input type="search" name="q" value="{{ $term }}" maxlength="100" placeholder="Ürün adı veya SKU ile ara">
    <button type="submit" class="admin-secondary"><x-admin-icon name="search" /> Ara</button>
  </form>
  @forelse($products as $product)
    <div class="admin-catalog-row">
      <img class="admin-catalog-photo" src="{{ asset($product->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="" width="68" height="68" loading="lazy">
      <div class="admin-catalog-info"><strong>{{ $product->name }}</strong><small>{{ $product->sku }} · {{ $product->category?->name }}</small>
        @if($product->tracks_stock)
          <span @class(['admin-stock-badge','is-low'=>$product->stock_quantity<=10])>Stok: {{ $product->stock_quantity }}</span>
        @else
          <span class="admin-stock-badge">Stok takibi kapalı</span>
        @endif
      </div>
      <a class="admin-secondary" href="{{ route('admin.products.edit', $product) }}">Stok güncelle <x-admin-icon name="arrow-right" /></a>
    </div>
  @empty<p class="admin-empty">Bu filtreye uyan ürün bulunamadı.</p>@endforelse
  {{ $products->links() }}
</div>
@endsection
