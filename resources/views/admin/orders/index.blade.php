@extends('admin.layout')
@section('title', 'Siparişler | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <p class="admin-eyebrow">OPERASYON / SİPARİŞLER</p><h1>Siparişler</h1>
    <p>Ödeme kontrolünden teslimata kadar tüm siparişleri tek yerden takip et.</p>
  </div>
  <a class="admin-secondary" href="{{ route('admin.payment-notifications.index') }}"><x-admin-icon name="landmark" /> Ödeme bildirimleri</a>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Sipariş listesi</h2><span>{{ number_format($orders->total(), 0, ',', '.') }} sipariş</span></div>
  <div class="admin-orders-toolbar">
    <form class="admin-catalog-search" method="GET" action="{{ route('admin.orders.index') }}" role="search">
      @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
      <input type="search" name="q" value="{{ $term }}" maxlength="100" aria-label="Sipariş veya müşteri ara" placeholder="Sipariş no, müşteri adı veya e-posta">
      <button type="submit" class="admin-secondary"><x-admin-icon name="search" /> Ara</button>
      @if($term)<a href="{{ route('admin.orders.index', array_filter(['status' => $status])) }}" class="admin-secondary">Temizle</a>@endif
    </form>
  </div>
  <nav class="admin-order-filters" aria-label="Sipariş durumuna göre filtrele">
    <a href="{{ route('admin.orders.index', array_filter(['q' => $term])) }}" @class(['is-active' => $status === '']) @if(!$status) aria-current="page" @endif>Tümü</a>
    @foreach(['pending_payment' => 'Ödeme bekleyen', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Kargoda', 'out_for_delivery' => 'Dağıtımda', 'completed' => 'Tamamlanan', 'cancelled' => 'İptal'] as $value => $label)
      <a href="{{ route('admin.orders.index', array_filter(['status' => $value, 'q' => $term])) }}"
        @class(['is-active' => $status === $value]) @if($status === $value) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
  </nav>
  <div class="admin-order-card-list">
    @forelse($orders as $order)
      <a class="admin-order-list-item" href="{{ route('admin.orders.show', $order) }}">
        <span><strong>{{ $order->order_number }} @if($order->is_test)<small class="admin-test-tag">TEST</small>@endif</strong>
          <small>{{ $order->customer_name }} · {{ $order->customer_email }}</small>
        </span>
        <span class="admin-order-list-date"><small>Tarih</small><strong>{{ $order->created_at->format('d.m.Y') }}</strong></span>
        <span><small>Tutar</small><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></span>
        <span><span class="admin-state-pill state-{{ $order->status }}">{{ $order->statusLabel() }}</span>
          @if($order->pending_payment_notifications_count)
            <small class="admin-table-alert"><x-admin-icon name="bell" /> Ödeme bildirimi ({{ $order->pending_payment_notifications_count }})</small>
          @endif
        </span>
        <x-admin-icon name="arrow-right" />
      </a>
    @empty
      <div class="admin-empty">Bu filtrede sipariş yok. Farklı bir arama veya durum deneyebilirsin.</div>
    @endforelse
  </div>
  <div class="pagination-wrap">{{ $orders->links() }}</div>
</div>
@endsection