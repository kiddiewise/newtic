@extends('layouts.store')
@section('title', 'Sipariş Alındı | Polaris Kurşun')
@section('content')
<main class="checkout-shell checkout-success-page" id="content">
  <div class="checkout-top"><a href="{{ route('home') }}" aria-label="Ana sayfa">‹</a><strong>Sipariş Bilgileri</strong><span></span></div>
  <div class="checkout-card checkout-success-hero">
    <div class="checkout-success-mark">✓</div>
    <h1>Siparişin oluşturuldu!</h1>
    <p>Sipariş numaran: <strong>{{ $order->order_number }}</strong></p>
    <span class="checkout-status">{{ $order->statusLabel() }}</span>
    @if($order->is_test)
      <p class="checkout-testing-note"><strong>Test siparişi:</strong> Bu mağaza henüz test aşamasında. Gerçek banka ödemesi yapmayın; bu ekran ödeme alındığını göstermez.</p>
    @endif
  </div>
  <div class="checkout-card">
    <h2>Sipariş detayları</h2>
    @foreach($order->items as $line)
      <div class="checkout-order-item"><span>{{ $line->product_name }} <small>× {{ $line->quantity }}</small></span>
        <strong>{{ number_format($line->line_total_cents / 100, 2, ',', '.') }} ₺</strong></div>
    @endforeach
    <div class="checkout-price-line"><span>Ürünler</span><strong>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</strong></div>
    <div class="checkout-price-line"><span>Kargo</span><strong>{{ $order->shipping_cents === 0 ? 'Ücretsiz' : number_format($order->shipping_cents / 100, 2, ',', '.').' ₺' }}</strong></div>
    <div class="checkout-price-line is-total"><span>Toplam</span><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></div>
  </div>
  <div class="checkout-card"><h2>Teslimat bilgileri</h2>
    <p>{{ $order->customer_name }} · {{ $order->phone }}</p><p>{{ $order->street_address }}, {{ $order->district }}/{{ $order->city }}</p>
    @if($order->invoice_type === 'corporate')<p>Kurumsal fatura: {{ $order->company_title }}</p>@endif
    @if($order->order_note)<p><strong>Sipariş notu:</strong> {{ $order->order_note }}</p>@endif
    <div class="checkout-payment-option"><strong>Havale / EFT · {{ $order->statusLabel() }}</strong></div>
    <p class="checkout-muted">Ödeme talimatları ve banka hesap bilgileri henüz tanımlanmadı; ödeme tamamlanmış sayılmaz.</p>
  </div>
  <a class="checkout-action" href="{{ auth()->check() ? route('account.index') : route('home') }}">{{ auth()->check() ? 'Hesabıma Dön' : 'Alışverişe Dön' }}</a>
</main>
@endsection
