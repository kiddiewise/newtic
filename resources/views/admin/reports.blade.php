@extends('admin.layout')
@section('title','Raporlar | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div><p class="admin-eyebrow">ANALİZ / PERFORMANS</p><h1>Satış raporları</h1><p>Gerçek banka onaylı satışları ve operasyon durumunu karşılaştır.</p></div>
  <nav class="admin-catalog-filters" aria-label="Rapor dönemi">
  @foreach([7=>'7 gün',30=>'30 gün',90=>'90 gün'] as $value=>$label)
    <a href="{{ route('admin.reports',['days'=>$value]) }}" @class(['admin-filter-pill','is-active'=>$days===$value])>{{ $label }}</a>
  @endforeach
  </nav>
</div>
<div class="admin-dashboard-notice"><x-admin-icon name="info" /><span>Test siparişleri ciroya dahil değildir. Tutarlar yalnızca banka doğrulaması yapılmış, test dışı siparişlerden hesaplanır.</span></div>
<div class="admin-kpi-grid">
  <div class="admin-kpi"><span class="admin-kpi-icon is-blue"><x-admin-icon name="bag" /></span><span class="admin-kpi-label">Toplam sipariş</span><strong>{{ $totalOrders }}</strong><small>{{ $testOrders }} deneme siparişi dahil</small></div>
  <div class="admin-kpi"><span class="admin-kpi-icon is-mint"><x-admin-icon name="chart" /></span><span class="admin-kpi-label">Doğrulanmış ciro</span><strong class="admin-kpi-money">{{ number_format($paidCents/100,2,',','.') }} ₺</strong><small>{{ $paidCount }} gerçek ödeme onaylı sipariş</small></div>
  <div class="admin-kpi"><span class="admin-kpi-icon is-orange"><x-admin-icon name="clock" /></span><span class="admin-kpi-label">Ödeme bekleyen</span><strong>{{ $paymentPending }}</strong><small>Bu dönemin siparişleri</small></div>
  <div class="admin-kpi"><span class="admin-kpi-icon is-purple"><x-admin-icon name="package" /></span><span class="admin-kpi-label">Ortalama onaylı sepet</span><strong class="admin-kpi-money">{{ number_format($averageCents/100,2,',','.') }} ₺</strong><small>Gerçek banka onaylı siparişlerde</small></div>
</div>
<div class="admin-dashboard-grid">
  <section class="admin-panel">
    <div class="admin-panel-title"><h2>En çok satılan ürünler</h2><span>En fazla 10 ürün</span></div>
    <p class="admin-description">Doğrulanmış gerçek siparişlerdeki ürün adetlerine göre sıralama.</p>
    @forelse($topProducts as $product)
      <div class="admin-detail-line"><span>{{ $product->product_name }} <small>· {{ $product->sold }} adet</small></span><strong>{{ number_format($product->revenue/100,2,',','.') }} ₺</strong></div>
    @empty<p class="admin-empty">Bu dönemde onaylanmış gerçek satış bulunmuyor.</p>@endforelse
  </section>
  <section class="admin-panel">
    <div class="admin-panel-title"><h2>Sipariş durumları</h2></div>
    @foreach(['pending_payment'=>'Ödeme bekliyor','preparing'=>'Hazırlanıyor','shipped'=>'Kargoda','out_for_delivery'=>'Dağıtımda','completed'=>'Tamamlandı','cancelled'=>'İptal'] as $key=>$label)
      <div class="admin-detail-line"><span>{{ $label }}</span><strong>{{ $statusCounts[$key] ?? 0 }} sipariş</strong></div>
    @endforeach
    <a href="{{ route('admin.orders.index') }}" class="admin-primary admin-block-action">Siparişleri görüntüle <x-admin-icon name="arrow-right" /></a>
  </section>
</div>
@endsection