@extends('admin.layout')
@section('title','Müşteriler | NEWTIC')
@section('content')
<div class="admin-page-heading"><div><p class="admin-eyebrow">MAĞAZA / MÜŞTERİLER</p><h1>Müşteriler</h1><p>Kayıtlı üyeleri ve sipariş sayılarını güvenli şekilde görüntüle.</p></div></div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Kayıtlı müşteriler</h2><span>{{ $customers->total() }} müşteri</span></div>
  <form class="admin-catalog-search" method="GET" action="{{ route('admin.customers') }}" role="search">
    <input type="search" name="q" value="{{ $term }}" maxlength="100" placeholder="İsim veya e-posta ile ara">
    <button type="submit" class="admin-secondary"><x-admin-icon name="search" /> Ara</button>
  </form>
  @forelse($customers as $customer)
    <div class="admin-search-result">
      <span class="admin-search-result-icon"><x-admin-icon name="users" /></span>
      <span><strong>{{ $customer->name }}</strong><small>{{ $customer->email }} · {{ $customer->created_at->format('d.m.Y') }} tarihinde kayıt</small></span>
      <span class="admin-chip">{{ $customer->orders_count }} sipariş</span>
      <a href="{{ route('admin.orders.index', ['q' => $customer->email]) }}" class="admin-secondary">Siparişler <x-admin-icon name="arrow-right" /></a>
    </div>
  @empty<p class="admin-empty">Kayıtlı müşteri bulunamadı.</p>@endforelse
  {{ $customers->links() }}
</div>
<p class="admin-description">Bu liste yalnızca kayıtlı üyeleri gösterir. Üye olmadan alışveriş yapan müşterilerin siparişlerine Siparişler ekranından ulaşabilirsin.</p>
@endsection