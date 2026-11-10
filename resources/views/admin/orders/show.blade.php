@extends('admin.layout')
@section('title', $order->order_number.' | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div><a class="admin-back" href="{{ route('admin.orders.index') }}">← Siparişler</a><p class="admin-eyebrow">SİPARİŞ DETAYI</p>
    <h1>{{ $order->order_number }}</h1><p>{{ $order->created_at->format('d.m.Y H:i') }} · {{ $order->statusLabel() }} @if($order->is_test) · TEST SİPARİŞİ @endif</p>
  </div>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Havale / EFT ödeme kontrolü</h2>
    <a class="admin-secondary" href="{{ route('admin.payment-notifications.index') }}">Bildirim kutusu</a>
  </div>
  <p class="admin-description">Müşterinin ödeme bildirimi banka hareketini doğrulamaz. Tutarı ve açıklamadaki sipariş numarasını karşılaştırmadan onay vermeyin.</p>
  @forelse($order->paymentNotifications as $notification)
    <div class="admin-payment-history">
      <strong>{{ $notification->statusLabel() }}</strong>
      <small>{{ $notification->created_at->format('d.m.Y H:i') }} · {{ $notification->bank_name_snapshot }} · {{ $notification->iban_snapshot }}</small>
      @if($notification->reviewer)<small>İnceleyen: {{ $notification->reviewer->name }} · {{ $notification->reviewed_at?->format('d.m.Y H:i') }}</small>@endif
      @if($notification->customer_note)<p>Müşteri notu: {{ $notification->customer_note }}</p>@endif
      @if($notification->admin_note)<p>Kontrol notu: {{ $notification->admin_note }}</p>@endif
      @if($notification->status === 'pending')
        <a class="admin-primary" href="{{ route('admin.payment-notifications.index') }}">Banka hareketini kontrol et ve karar ver</a>
      @endif
    </div>
  @empty
    <p class="admin-description">Bu sipariş için henüz müşteri ödeme bildirimi bulunmuyor.</p>
  @endforelse
  @if($order->status === 'pending_payment')
    <p class="admin-description">Sipariş ancak bir bildirim yönetici tarafından doğrulanınca <strong>Hazırlanıyor</strong> aşamasına geçer.</p>
  @endif
</div>
<div class="admin-panel">
  <h2>Durum yönetimi</h2><p class="admin-description">Ödeme doğrulaması otomatik yapılmaz. Havale/EFT işlemini ayrıca kontrol edin.</p>
  @if($allowed)
    <form class="admin-form admin-order-status" method="POST" action="{{ route('admin.orders.update', $order) }}">
      @csrf @method('PATCH')
      <label>Yeni durum
        <select name="status" required>
          @foreach($allowed as $state)
            <option value="{{ $state }}">{{ match($state) {
              'preparing'=>'Hazırlanıyor', 'shipped'=>'Kargolandı', 'completed'=>'Tamamlandı', 'cancelled'=>'İptal edildi', default=>$state
            } }}</option>
          @endforeach
        </select>
      </label>
      <button type="submit" class="admin-primary">Durumu güncelle</button>
    </form>
  @else
    <p class="admin-description">Sipariş bu durumdan sonra değiştirilemez.</p>
  @endif
  <p class="admin-description">İptal edilen siparişler için ayrılan stok yalnızca bir kez iade edilir. Kargolanmış sipariş otomatik iptal edilemez.</p>
</div>
<div class="admin-panel"><h2>Müşteri ve teslimat</h2>
  <p><strong>{{ $order->customer_name }}</strong></p><p>{{ $order->customer_email }} · {{ $order->phone }}</p>
  <p>{{ $order->address_label }} · {{ $order->street_address }}, {{ $order->district }}/{{ $order->city }}</p>
  @if($order->invoice_type === 'corporate')
    <p><strong>Kurumsal:</strong> {{ $order->company_title }} · {{ $order->tax_office }} · {{ $order->tax_number }}</p>
  @else<p>Fatura: Bireysel</p>@endif
  @if($order->order_note)<p><strong>Sipariş notu:</strong> {{ $order->order_note }}</p>@endif
</div>
<div class="admin-panel"><h2>Ürünler</h2>
  @foreach($order->items as $line)
    <div class="admin-order-row"><span><strong>{{ $line->product_name }}</strong><small>SKU: {{ $line->sku }} · {{ $line->quantity }} adet</small></span>
      <strong>{{ number_format($line->line_total_cents / 100, 2, ',', '.') }} ₺</strong></div>
  @endforeach
  <div class="checkout-price-line"><span>Ara toplam</span><strong>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</strong></div>
  <div class="checkout-price-line"><span>Kargo</span><strong>{{ number_format($order->shipping_cents / 100, 2, ',', '.') }} ₺</strong></div>
  <div class="checkout-price-line is-total"><span>Toplam</span><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></div>
</div>
@endsection
