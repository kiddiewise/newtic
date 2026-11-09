@extends('admin.layout')
@section('title', 'Siparişler | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div><p class="admin-eyebrow">SİPARİŞ YÖNETİMİ</p><h1>Siparişler</h1><p>Yeni siparişleri görüntüle, ödeme ve kargo durumunu yönet.</p></div>
</div>
<div class="admin-panel">
  <div class="admin-order-filters">
    <a href="{{ route('admin.orders.index') }}" @class(['is-active' => $status === ''])>Tümü</a>
    @foreach(['pending_payment'=>'Ödeme bekleyen','preparing'=>'Hazırlanan','shipped'=>'Kargolanan','completed'=>'Tamamlanan','cancelled'=>'İptal'] as $value=>$label)
      <a href="{{ route('admin.orders.index', ['status'=>$value]) }}" @class(['is-active'=>$status === $value])>{{ $label }}</a>
    @endforeach
  </div>
  @forelse($orders as $order)
    <a class="admin-order-row" href="{{ route('admin.orders.show', $order) }}">
      <span><strong>{{ $order->order_number }}</strong><small>{{ $order->created_at->format('d.m.Y H:i') }} · {{ $order->customer_name }} @if($order->is_test)· TEST @endif</small></span>
      <span><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong><small>{{ $order->statusLabel() }}</small></span>
    </a>
  @empty<p class="admin-empty">Bu durumda henüz sipariş bulunmuyor.</p>@endforelse
  {{ $orders->links() }}
</div>
@endsection
