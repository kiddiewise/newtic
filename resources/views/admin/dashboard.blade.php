@extends('admin.layout')
@section('title', 'Genel Bakış | NEWTIC')
@section('content')
<div class="newtic-dashboard" data-analytics-dashboard data-live-url="{{ route('admin.analytics.live', [], false) }}">
<div class="newtic-dashboard-welcome admin-welcome">
  <div>
    <p class="admin-eyebrow">MAĞAZA YÖNETİMİ</p>
    <h1>Merhaba, {{ explode(' ', trim(auth()->user()->name))[0] }} 👋</h1>
  </div>
  <a href="{{ route('home') }}" class="newtic-dashboard-store-link" target="_blank" rel="noopener noreferrer"><x-admin-icon name="external-link" /> Mağazayı görüntüle</a>
</div>
<section class="admin-panel admin-dashboard-actions admin-dashboard-actions-first">
    <div class="admin-panel-title"><h2>Hızlı işlemler</h2></div>
    <div class="admin-action-grid">
      <a href="{{ route('admin.products.create') }}"><span class="is-mint"><x-admin-icon name="plus" /></span>Yeni ürün</a>
      <a href="{{ route('admin.orders.index') }}"><span class="is-blue"><x-admin-icon name="bag" /></span>Siparişler</a>
      <a href="{{ route('admin.payment-notifications.index') }}"><span class="is-orange"><x-admin-icon name="landmark" /></span>Ödeme bildirimleri</a>
      <a href="{{ route('admin.categories.index') }}"><span class="is-purple"><x-admin-icon name="grid" /></span>Kategoriler</a>
      <a href="{{ route('admin.index') }}"><span class="is-mint"><x-admin-icon name="layout" /></span>Anasayfa Düzenle</a>
      <a href="{{ route('admin.bank-accounts.index') }}"><span class="is-blue"><x-admin-icon name="settings" /></span>Banka hesapları</a>
    </div>
  </section>
<a class="newtic-dashboard-live" href="{{ route('admin.analytics') }}" data-live-section>
  <div><span class="newtic-dashboard-live-label"><i aria-hidden="true"></i> Canlı</span><strong>Şu anda sitede</strong><small>Son 5 dk · İzinli oturumlar</small></div>
  <div class="newtic-dashboard-live-count"><strong data-live-count aria-live="polite">{{ $liveOnline }}</strong><span>aktif oturum</span><small data-live-updated>{{ now()->format('H:i:s') }}</small></div><x-admin-icon name="arrow-right"/>
</a>
@if($updateAvailable && $updateRelease)
  <section class="admin-dashboard-notice" style="display:flex;align-items:center;gap:13px;background:#edf2f8;border:1px solid #cbd7e5;border-left:4px solid #092037;border-radius:12px;padding:15px 17px;margin-bottom:18px;color:#092037" aria-label="Yeni NEWTIC sürümü">
    <x-admin-icon name="refresh-cw" />
    <span style="flex:1"><strong>NEWTIC {{ $updateRelease['version'] }} güncellemesi hazır!</strong> Yeni özellikler, iyileştirmeler ve sürüm ayrıntılarını inceleyebilirsiniz.</span>
    <a style="white-space:nowrap;background:#092037;color:#fff;border-radius:9px;padding:10px 14px;text-decoration:none;font-weight:600" href="{{ route('admin.software-updates.index') }}">Güncellemeyi gör</a>
  </section>
@endif
@if(!\App\Models\BankAccount::query()->where('is_active', true)->exists() && !\App\Support\PaytrTestAccess::available())
  <div class="admin-dashboard-notice"><x-admin-icon name="info" /><span><strong>Ödeme kurulumu gerekiyor:</strong> Henüz etkin bir ödeme yöntemi yok. Havale/EFT için banka hesabı; kart için PAYTR bağlantısı ve sağlayıcı onayı gerekir. <a href="{{ route('admin.bank-accounts.index') }}">Banka hesapları</a> · <a href="{{ route('admin.paytr-settings.edit') }}">PAYTR ayarları</a></span></div>
@endif
@if(\App\Support\LegalReadiness::requiredBeforeLive() !== [])
  <div class="admin-dashboard-notice"><x-admin-icon name="info" /><span><strong>Mağaza yayına hazırlık:</strong> Satıcı bilgileri ve zorunlu sözleşmeler tamamlanmadan gerçek sipariş açılamaz. <a href="{{ route('admin.legal.index') }}">Yasal sayfaları tamamla</a></span></div>
@endif
<div class="newtic-dashboard-section-heading"><h2>Mağaza performansı</h2><span>Tüm zamanlar</span></div>
<a href="{{ route('admin.reports') }}" class="newtic-dashboard-revenue">
  <div><span>Doğrulanmış ciro</span><strong>{{ number_format($metrics['verified_revenue_cents']/100,2,',','.') }} ₺</strong><small>Yalnızca doğrulanmış satışlar</small></div>
  <div class="newtic-dashboard-revenue-visual"><x-admin-icon name="chart"/><svg viewBox="0 0 140 48" role="img" aria-label="Son 7 günlük doğrulanmış ciro">
    <polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="@foreach($trend as $day){{ 3+$loop->index*22 }},{{ 44-round($day['revenue']/$chartMax*40) }} @endforeach"/>
  </svg><small>Son 7 gün</small></div>
</a>
<div class="admin-kpi-grid">
  @foreach([
    ['label'=>'Toplam sipariş','value'=>$metrics['orders'],'icon'=>'bag','url'=>route('admin.orders.index')],
    ['label'=>'Hazırlanan sipariş','value'=>$metrics['preparing'],'icon'=>'package','url'=>route('admin.orders.index',['status'=>'preparing'])],
    ['label'=>'Ödeme bekleyen','value'=>$metrics['unpaid'],'icon'=>'clock','url'=>route('admin.orders.index',['status'=>'pending_payment'])],
    ['label'=>'Düşük stoklu ürün','value'=>$metrics['low_stock'],'icon'=>'alert','url'=>route('admin.products.index',['status'=>'low_stock'])],
  ] as $metric)
    <a href="{{ $metric['url'] }}" class="admin-kpi">
      <div class="newtic-dashboard-kpi-top"><span class="admin-kpi-icon"><x-admin-icon :name="$metric['icon']"/></span><x-admin-icon name="arrow-right"/></div>
      <strong>{{ number_format($metric['value'],0,',','.') }}</strong><span class="admin-kpi-label">{{ $metric['label'] }}</span>
    </a>
  @endforeach
</div>
<div class="admin-dashboard-grid">
  <section class="admin-panel admin-dashboard-orders">
    <div class="admin-panel-title"><h2>Son siparişler</h2><a class="admin-text-link" href="{{ route('admin.orders.index') }}">Tüm siparişler <x-admin-icon name="arrow-right"/></a></div>
    <div class="newtic-dashboard-recent">
      @forelse($recentOrders as $order)
        <a class="newtic-dashboard-order" href="{{ route('admin.orders.show',$order) }}">
          <div><strong>{{ $order->order_number }}</strong><span>{{ $order->customer_name }}</span><small>{{ $order->created_at->format('d.m.Y H:i') }}</small></div>
          <div class="newtic-dashboard-order-meta"><strong>{{ number_format($order->total_cents/100,2,',','.') }} ₺</strong><span class="admin-state-pill state-{{ $order->status }}">{{ $order->statusLabel() }}</span>@if($order->is_test)<small class="admin-test-tag">Tahsilatsız kayıt</small>@endif @if($order->pending_claims_count)<small class="admin-table-alert">Bildirim var</small>@endif</div>
          <x-admin-icon name="arrow-right"/>
        </a>
      @empty <div class="admin-empty">Henüz sipariş bulunmuyor.</div> @endforelse
    </div>
  </section>
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
      <p class="admin-chart-empty">Son 7 günde doğrulanmış satış yok.</p>
    @endif
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
    <div class="admin-panel-title"><h2>Stok uyarıları</h2><a class="admin-text-link" href="{{ route('admin.products.index',['status'=>'low_stock']) }}">Ürünler <x-admin-icon name="arrow-right" /></a></div>
    <p class="admin-description">10 ve altında stok bulunan aktif ürünler.</p>
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
    <small class="admin-description">Sipariş kayıtları ile doğrulanmış satışlar ayrı hesaplanır.</small>
  </section>
</div>
</div>
@endsection
