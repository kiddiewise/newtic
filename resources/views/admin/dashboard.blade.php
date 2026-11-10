@extends('admin.layout')
@section('title', 'Genel Bakış | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions admin-welcome">
  <div>
    <p class="admin-eyebrow">MAĞAZA YÖNETİMİ</p>
    <h1>Merhaba, {{ explode(' ', trim(auth()->user()->name))[0] }} 👋</h1>
    <p>Mağazanın son durumuna tek ekrandan ulaş, öncelikli işleri hızlıca tamamla.</p>
  </div>
  <a href="{{ route('home') }}" class="admin-secondary" target="_blank" rel="noopener noreferrer"><x-admin-icon name="external-link" /> Mağazayı görüntüle</a>
</div>
@if(config('newtic.checkout_test_mode', true))
  <div class="admin-dashboard-notice"><x-admin-icon name="info" /><span><strong>Test ortamı:</strong> Gerçek tahsilat yapılmıyor. Ciro yalnızca doğrulanmış ve test dışı siparişlerden hesaplanır; deneme siparişleri sipariş sayaçlarına dahildir.</span></div>
@endif
<div class="admin-kpi-grid">
  <a href="{{ route('admin.orders.index') }}" class="admin-kpi">
    <span class="admin-kpi-icon is-mint"><x-admin-icon name="bag" /></span>
    <span class="admin-kpi-label">Toplam sipariş</span>
    <strong>{{ number_format($metrics['orders'], 0, ',', '.') }}</strong>
    <small>Tüm sipariş kayıtları</small>
  </a>
  <div class="admin-kpi">
    <span class="admin-kpi-icon is-purple"><x-admin-icon name="chart" /></span>
    <span class="admin-kpi-label">Doğrulanmış ciro</span>
    <strong class="admin-kpi-money">{{ number_format($metrics['verified_revenue_cents']/100, 2, ',', '.') }} ₺</strong>
    <small>Test dışı, banka onaylı</small>
  </div>
  <a href="{{ route('admin.orders.index', ['status' => 'pending_payment']) }}" class="admin-kpi">
    <span class="admin-kpi-icon is-orange"><x-admin-icon name="clock" /></span>
    <span class="admin-kpi-label">Ödeme bekleyen</span>
    <strong>{{ number_format($metrics['unpaid'], 0, ',', '.') }}</strong>
    <small>Ödeme kontrolüne geç</small>
  </a>
  <a href="{{ route('admin.orders.index', ['status' => 'preparing']) }}" class="admin-kpi">
    <span class="admin-kpi-icon is-blue"><x-admin-icon name="package" /></span>
    <span class="admin-kpi-label">Hazırlanan sipariş</span>
    <strong>{{ number_format($metrics['preparing'], 0, ',', '.') }}</strong>
    <small>Paketleme sırasındaki işler</small>
  </a>
</div>
<div class="admin-dashboard-grid">
  <section class="admin-panel admin-chart-card">
    <div class="admin-panel-title">
      <div><h2>Satış performansı</h2><p class="admin-description">Son 7 gün · Doğrulanmış gerçek sipariş cirosu</p></div>
      <span class="admin-chip"><x-admin-icon name="calendar" /> 7 gün</span>
    </div>
    <div class="admin-chart-legend"><span><i></i> Günlük ciro (₺)</span><small>Sütunlara dokunarak tutarı görebilirsin</small></div>
    <div class="admin-sales-chart" role="img" aria-label="Son yedi günün gerçek tahsilat grafiği">
      @foreach($trend as $day)
        <div class="admin-chart-column" tabindex="0" title="{{ $day['date'] }} · {{ number_format($day['revenue']/100, 2, ',', '.') }} ₺ · {{ $day['orders'] }} sipariş"
          aria-label="{{ $day['date'] }}, doğrulanmış ciro {{ number_format($day['revenue']/100, 2, ',', '.') }} TL, {{ $day['orders'] }} sipariş">
          <span class="admin-chart-bar" style="height:{{ max($day['revenue'] > 0 ? 5 : 2, round(($day['revenue']/$chartMax)*100)) }}%"></span>
          <span class="admin-chart-day">{{ $day['label'] }}<small>{{ $day['date'] }}</small></span>
        </div>
      @endforeach
    </div>
    @if($trend->every(fn ($day) => $day['revenue'] === 0))
      <p class="admin-chart-empty">Bu dönemde banka doğrulaması yapılmış gerçek satış yok. Sipariş bildirimleri ayrı olarak izlenebilir.</p>
    @endif
  </section>

  <section class="admin-panel admin-dashboard-actions">
    <div class="admin-panel-title"><h2>Hızlı işlemler</h2></div>
    <div class="admin-action-grid">
      <a href="{{ route('admin.products.create') }}"><span class="is-mint"><x-admin-icon name="plus" /></span>Yeni ürün</a>
      <a href="{{ route('admin.orders.index') }}"><span class="is-blue"><x-admin-icon name="bag" /></span>Siparişleri yönet</a>
      <a href="{{ route('admin.payment-notifications.index') }}"><span class="is-orange"><x-admin-icon name="landmark" /></span>Ödeme bildirimleri</a>
      <a href="{{ route('admin.categories.index') }}"><span class="is-purple"><x-admin-icon name="grid" /></span>Kategoriler</a>
      <a href="{{ route('admin.index') }}"><span class="is-mint"><x-admin-icon name="layout" /></span>Vitrin düzenle</a>
      <a href="{{ route('admin.bank-accounts.index') }}"><span class="is-blue"><x-admin-icon name="settings" /></span>Banka hesapları</a>
    </div>
  </section>
  <section class="admin-panel admin-dashboard-orders">
    <div class="admin-panel-title">
      <h2>Son siparişler</h2><a class="admin-text-link" href="{{ route('admin.orders.index') }}">Tüm siparişler <x-admin-icon name="arrow-right" /></a>
    </div>
    <div class="admin-table-scroll">
      <table class="admin-data-table">
        <thead><tr><th>Sipariş no</th><th>Müşteri</th><th>Tarih</th><th>Tutar</th><th>Durum</th></tr></thead>
        <tbody>
        @forelse($recentOrders as $order)
          <tr>
            <td><a href="{{ route('admin.orders.show', $order) }}" class="admin-table-link">{{ $order->order_number }}</a> @if($order->is_test)<small class="admin-test-tag">TEST</small>@endif</td>
            <td>{{ $order->customer_name }}</td>
            <td>{{ $order->created_at->format('d.m.Y H:i') }}</td>
            <td><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></td>
            <td><span class="admin-state-pill state-{{ $order->status }}">{{ $order->statusLabel() }}</span>
              @if($order->pending_claims_count)<a href="{{ route('admin.orders.show', $order) }}" class="admin-table-alert">Bildirim var</a>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="admin-empty">Henüz sipariş bulunmuyor.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <section class="admin-panel admin-dashboard-payments">
    <div class="admin-panel-title"><h2>Havale / EFT</h2><span class="admin-kpi-icon is-mint"><x-admin-icon name="landmark" /></span></div>
    <div class="admin-payment-spotlight">
      <strong>{{ $metrics['pending_notifications'] }}</strong>
      <span>Kontrol bekleyen bildirim</span>
    </div>
    <p class="admin-description">Müşteri bildirimi ödeme onayı değildir. Gerçek banka hareketini kontrol etmeden siparişi hazırlama.</p>
    <a class="admin-primary admin-block-action" href="{{ route('admin.payment-notifications.index') }}">Bildirimleri incele <x-admin-icon name="arrow-right" /></a>
  </section>
  <section class="admin-panel admin-dashboard-stock">
    <div class="admin-panel-title"><h2>Stok uyarıları</h2><a class="admin-text-link" href="{{ route('admin.products.index') }}">Ürünler <x-admin-icon name="arrow-right" /></a></div>
    <p class="admin-description">Stok miktarı 10 ve altında olan aktif ürünler.</p>
    @forelse($lowStockProducts as $product)
      <a href="{{ route('admin.products.edit', $product) }}" class="admin-stock-item">
        <img src="{{ asset($product->image_path ?: 'assets/products/demo-sinker.svg') }}" width="44" height="44" loading="lazy" alt="">
        <span>{{ $product->name }}<small>{{ $product->sku }}</small></span>
        <strong>Stok: {{ $product->stock_quantity }}</strong>
      </a>
    @empty
      <div class="admin-empty"><x-admin-icon name="check" /> Düşük stoklu ürün bulunmuyor.</div>
    @endforelse
  </section>
  <section class="admin-panel admin-dashboard-status">
    <div class="admin-panel-title"><h2>Sipariş dağılımı</h2></div>
    @php
      $dashboardStates = ['pending_payment' => ['Ödeme bekliyor', '#f4a746'], 'preparing' => ['Hazırlanıyor', '#438cd1'], 'shipped' => ['Kargoda', '#9570d7'], 'out_for_delivery' => ['Dağıtımda', '#397abb'], 'completed' => ['Tamamlandı', '#17a780'], 'cancelled' => ['İptal', '#d97478']];
    @endphp
    @foreach($dashboardStates as $key => $stateInfo)
      @php [$label, $color] = $stateInfo; @endphp
      @php $count = (int) ($statusCounts[$key] ?? 0); @endphp
      <div class="admin-distribution-row">
        <span><i style="background:{{ $color }}"></i>{{ $label }}</span><strong>{{ $count }}</strong>
        <div class="admin-distribution-track"><span style="background:{{ $color }};width:{{ ($count / $totalStatus)*100 }}%"></span></div>
      </div>
    @endforeach
    <small class="admin-description">Gerçek veriler ve test siparişleri birlikte gösterilir.</small>
  </section>
</div>
@endsection
