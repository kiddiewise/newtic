@extends('admin.layout')
@section('title', 'Siparişler | NEWTIC')
@section('content')
<div class="newtic-orders">
  <div class="newtic-orders-heading">
    <div><p class="admin-eyebrow">OPERASYON</p><h1>Siparişler</h1><p>{{ number_format($orders->total(), 0, ',', '.') }} sipariş · {{ $unreadCount }} yeni sipariş</p></div>
    <details class="newtic-order-actions">
      <summary>İşlemler <span aria-hidden="true">···</span></summary>
      <div>
        <a href="{{ route('admin.payment-notifications.index') }}"><x-admin-icon name="landmark" /> Ödeme bildirimleri</a>
        <form method="POST" action="{{ route('admin.orders.expire-unpaid') }}" onsubmit="return confirm('Yalnızca süresi dolmuş, ödeme bildirimi beklemeyen havale siparişleri iptal edilecek ve stokları iade edilecek. Devam edilsin mi?')">
          @csrf <button type="submit">Ödenmeyen siparişleri kontrol et</button>
        </form>
      </div>
    </details>
  </div>
  <div class="newtic-order-panel">
    <form method="GET" action="{{ route('admin.orders.index') }}" class="newtic-order-filter">
      @if($unreadOnly)<input type="hidden" name="unread" value="1">@endif
      <div class="newtic-order-search">
        <x-admin-icon name="search" />
        <input type="search" name="q" value="{{ $term }}" maxlength="100" aria-label="Sipariş veya müşteri ara" placeholder="Sipariş no, müşteri, telefon">
        <button type="submit">Ara</button>
      </div>
      <div class="newtic-order-filter-fields">
        <label>Durum<select name="status">
          <option value="">Tüm durumlar</option>
          @foreach(['pending_payment'=>'Ödeme bekleyen','preparing'=>'Hazırlanıyor','shipped'=>'Kargoda','out_for_delivery'=>'Dağıtımda','completed'=>'Tamamlanan','cancelled'=>'İptal'] as $value=>$label)
            <option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>
          @endforeach
        </select></label>
        <label>Ödeme<select name="payment">
          <option value="">Tümü</option><option value="paytr" @selected($payment==='paytr')>Kart / PAYTR</option><option value="bank_transfer" @selected($payment==='bank_transfer')>Havale / EFT</option>
        </select></label>
        <label>Sıralama<select name="sort">
          @foreach(['newest'=>'En yeni','oldest'=>'En eski','highest'=>'En yüksek tutar','lowest'=>'En düşük tutar'] as $value=>$label)
            <option value="{{ $value }}" @selected($sort===$value)>{{ $label }}</option>
          @endforeach
        </select></label>
      </div>
      <div class="newtic-order-filter-actions">
        <a href="{{ route('admin.orders.index') }}">Temizle</a><button type="submit">Filtrele</button>
      </div>
    </form>
    @php($listQuery = array_filter(['q'=>$term,'status'=>$status,'payment'=>$payment,'sort'=>$sort]))
    <nav class="newtic-order-tabs" aria-label="Yeni siparişleri filtrele">
      <a href="{{ route('admin.orders.index',$listQuery) }}" @if(!$unreadOnly) aria-current="page" @endif>Tümü</a>
      <a href="{{ route('admin.orders.index',$listQuery + ['unread'=>1]) }}" @if($unreadOnly) aria-current="page" @endif>Yeni siparişler <span>{{ $unreadCount }}</span></a>
    </nav>
    <div class="newtic-order-list">
      @forelse($orders as $order)
        <a @class(['newtic-order-row','is-unread'=>$unreadIds->has($order->id)]) href="{{ route('admin.orders.show',$order) }}">
          <div class="newtic-order-primary">
            <div class="newtic-order-number"><strong>{{ $order->order_number }}</strong>@if($unreadIds->has($order->id))<span class="newtic-order-new">Yeni</span>@endif</div>
            <span>{{ $order->customer_name }}</span>
            <small>{{ $order->customer_email }}</small>
          </div>
          <div class="newtic-order-meta"><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong><small>{{ $order->created_at->format('d.m.Y · H:i') }}</small><small>{{ $order->payment_method === 'paytr' ? 'Kart / PAYTR' : ($order->payment_method === 'bank_transfer' ? 'Havale / EFT' : $order->payment_method) }}</small></div>
          <div class="newtic-order-status">
            <span class="admin-state-pill state-{{ $order->status }}">{{ $order->statusLabel() }}</span>
            @if($order->is_test)<small class="admin-test-tag">Tahsilatsız kayıt</small>@endif
            @if($order->shippingShipment?->remote_status)<small>Kargo: {{ \App\Support\ShippingStatus::label($order->shippingShipment->remote_status) }}</small>@endif
            @if($order->pending_payment_notifications_count)<small class="admin-table-alert">Ödeme bildirimi ({{ $order->pending_payment_notifications_count }})</small>@endif
          </div>
          <span class="newtic-order-open">Detay <x-admin-icon name="arrow-right" /></span>
        </a>
      @empty
        <div class="admin-empty">Bu filtreye uygun sipariş bulunamadı.</div>
      @endforelse
    </div>
    <div class="pagination-wrap">{{ $orders->links() }}</div>
  </div>
</div>
@endsection
