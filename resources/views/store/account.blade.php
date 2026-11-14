@extends('layouts.store')
@section('title', 'Hesabım | Polaris Kurşun')
@section('content')
<main class="checkout-shell account-overview" id="content">
  <div class="checkout-top"><a href="{{ route('home') }}" aria-label="Geri dön">‹</a><strong>Hesabım</strong><span></span></div>
  <div class="checkout-card"><h1>Merhaba, {{ $user->name }}</h1><p>{{ $user->email }}</p>
    <form method="POST" action="{{ route('account.logout') }}">@csrf<button type="submit" class="checkout-outline-action">Güvenli çıkış yap</button></form>
  </div>
  <div class="checkout-card">
    <div class="checkout-card-heading"><h2>Adres defterim</h2><a href="{{ route('account.addresses.create') }}">+ Adres ekle</a></div>
    @forelse($addresses as $address)
      <div class="account-address-row"><div><strong>{{ $address->label }} @if($address->is_default) · Varsayılan @endif</strong><p>{{ $address->full_name }} · {{ $address->district }}/{{ $address->city }}</p></div><a href="{{ route('account.addresses.edit', $address) }}">Düzenle</a></div>
    @empty
      <p class="checkout-muted">Henüz kayıtlı adresin yok.</p>
    @endforelse
    <a href="{{ route('account.addresses.index') }}" class="checkout-outline-action">Tüm adreslerim</a>
  </div>
  <div class="checkout-card"><h2>Siparişlerim</h2>
    @forelse($orders as $order)
      <a href="{{ route('checkout.success', $order) }}" class="account-order-row">
        <span><strong>{{ $order->order_number }}</strong><small>{{ $order->created_at->format('d.m.Y') }} · {{ $order->statusLabel() }}</small>
          @if($order->shippingShipment?->remote_status)
            <small class="account-shipping-state">Kargo: {{ \App\Support\ShippingStatus::label($order->shippingShipment->remote_status) }}</small>
          @endif
        </span>
        <strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong>
      </a>
    @empty
      <p class="checkout-muted">Henüz siparişin bulunmuyor.</p>
    @endforelse
  </div>
</main>
@endsection
