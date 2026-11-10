@extends('admin.layout')
@section('title','Arama Sonuçları | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">HIZLI ARAMA</p><h1>Arama sonuçları</h1><p>Sipariş numarası, müşteri adı, ürün adı veya stok koduyla ara.</p></div>
</div>
<form action="{{ route('admin.search') }}" class="admin-form admin-search-page-form" role="search" method="GET">
  <label>Arama terimi
    <input type="search" name="q" value="{{ $term }}" minlength="2" maxlength="100" autofocus required placeholder="Örn. PK-, müşteri adı veya SKU">
  </label>
  <button type="submit" class="admin-primary">Ara <x-admin-icon name="search" /></button>
</form>
@if(mb_strlen($term) < 2)
<div class="admin-panel"><p class="admin-empty">Aramak için en az iki karakter girin.</p></div>
@else
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Siparişler ve müşteriler</h2><span>{{ $orders->count() }} sonuç (en fazla 15)</span></div>
  @forelse($orders as $order)
    <a class="admin-search-result" href="{{ route('admin.orders.show', $order) }}">
      <span class="admin-search-result-icon"><x-admin-icon name="bag" /></span>
      <span><strong>{{ $order->order_number }}</strong><small>{{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->statusLabel() }}</small></span>
      <strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong>
      <x-admin-icon name="arrow-right" />
    </a>
  @empty <p class="admin-empty">Eşleşen sipariş bulunamadı.</p>
  @endforelse
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Ürünler</h2><span>{{ $products->count() }} sonuç (en fazla 15)</span></div>
  @forelse($products as $product)
    <a class="admin-search-result" href="{{ route('admin.products.edit', $product) }}">
      <img src="{{ asset($product->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="" width="50" height="50" loading="lazy">
      <span><strong>{{ $product->name }}</strong><small>{{ $product->sku }} · {{ $product->category?->name }} · Stok: {{ $product->stock_quantity }}</small></span>
      <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
      <x-admin-icon name="arrow-right" />
    </a>
  @empty <p class="admin-empty">Eşleşen ürün bulunamadı.</p>
  @endforelse
</div>
@endif
@endsection