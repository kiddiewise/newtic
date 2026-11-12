@extends('admin.layout')
@section('title', 'Kargo Operasyonları | NEWTIC')
@section('topbar', 'Kargo Operasyonları')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <p class="admin-eyebrow">SİPARİŞLER / OPERASYON MERKEZİ</p>
    <h1>Kargo operasyonları</h1>
    <p>Gönderi hareketlerini takip et; iade, iptal ve başarısızlıkları ödeme kayıtlarını değiştirmeden incele.</p>
  </div>
  <a class="admin-secondary" href="{{ route('admin.shipping-integrations.index') }}"><x-admin-icon name="settings"/> Entegrasyon ayarları</a>
</div>

<section class="admin-panel ship-ops-overview">
  <div class="ship-ops-head">
    <div>
      <h2>Gönderi kontrol merkezi</h2>
      <p>Bildirim ulaşmayan kargolar, 15 dakikalık aralıklarla yapılabilen yalnızca-okuma sorgusuyla kontrol edilir. Bu işlem kesinlikle yeni barkod veya para iadesi oluşturmaz.</p>
    </div>
    @if(\App\Support\ShippingTestAccess::allowed())
      <form method="POST" action="{{ route('admin.shipping-operations.sync') }}">
        @csrf
        <button class="admin-primary" type="submit"><x-admin-icon name="refresh-cw"/> Kargoları kontrol et</button>
      </form>
    @endif
  </div>
  <div class="ship-ops-stats">
    <div><strong>{{ $counts['all'] }}</strong><span>Toplam gönderi</span></div>
    <div class="is-warning"><strong>{{ $counts['attention'] }}</strong><span>İnceleme bekleyen</span></div>
    <div><strong>{{ $counts['moving'] }}</strong><span>Devam eden</span></div>
    <div><strong>{{ $counts['delivered'] }}</strong><span>Teslim edilen</span></div>
  </div>
  <p class="ship-hint"><strong>Durum ayrımı:</strong> Kargo “İptal”, “İade” veya “Hatalı” olduğunda siparişin ödeme onayı ve stok miktarı kendiliğinden değişmez. Gerçek işlem için kargo firması ve banka/PAYTR kayıtları ayrıca kontrol edilmelidir.</p>
</section>

<section class="admin-panel ship-ops-results">
  <div class="admin-panel-title"><h2>Gönderiler</h2><span>{{ $shipments->total() }} kayıt</span></div>
  <nav class="admin-order-filters ship-ops-filters" aria-label="Kargo durumuna göre filtrele">
    @foreach(['attention'=>'İnceleme bekleyen','all'=>'Tümü','moving'=>'Devam eden','delivered'=>'Teslim edilen','reviewed'=>'İncelenen','sync_error'=>'Bağlantı sorunu'] as $key=>$label)
      <a href="{{ route('admin.shipping-operations.index', array_filter(['filter'=>$key,'q'=>$term])) }}"
        @class(['is-active'=>$filter===$key]) @if($filter===$key) aria-current="page" @endif>
        {{ $label }} <small>{{ $counts[$key] }}</small>
      </a>
    @endforeach
  </nav>
  <form method="GET" action="{{ route('admin.shipping-operations.index') }}" class="ship-ops-search">
    <input type="hidden" name="filter" value="{{ $filter }}">
    <input type="search" name="q" value="{{ $term }}" maxlength="100"
      placeholder="Sipariş numarasına göre ara" aria-label="Sipariş numarasına göre ara">
    <button class="admin-secondary" type="submit"><x-admin-icon name="search"/> Ara</button>
  </form>

  <div class="ship-ops-list">
    @forelse($shipments as $shipment)
      <article class="ship-ops-item">
        <div class="ship-ops-item-main">
          <div>
            <a class="ship-ops-order-link" href="{{ route('admin.orders.show', $shipment->order) }}">{{ $shipment->order->order_number }}</a>
            <small>{{ $shipment->carrier->name }} · {{ $shipment->warehouse->name }}</small>
            <small>Gönderi #{{ $shipment->remote_id ?: 'henüz yok' }}
              @if($shipment->tracking_number) · Takip: {{ $shipment->tracking_number }} @endif</small>
          </div>
          <span @class(['ship-ops-status','is-danger'=>\App\Support\ShippingStatus::isException($shipment->remote_status)||$shipment->state==='uncertain'])>
            {{ $shipment->state==='uncertain' ? 'Gönderi kaydı kontrol edilmeli' : \App\Support\ShippingStatus::label($shipment->remote_status) }}
          </span>
        </div>
        <p>{{ \App\Support\ShippingStatus::description($shipment->remote_status) }}</p>
        <div class="ship-ops-meta">
          <span>Son API kontrolü: {{ $shipment->last_synced_at?->format('d.m.Y H:i') ?: 'Henüz yapılmadı' }}</span>
          @if($shipment->sync_failure_count)
            <strong>API sorgu hatası: {{ $shipment->sync_failure_count }} kez</strong>
          @endif
          @if($shipment->exception_acknowledged_at)
            <span class="ship-ops-reviewed">İncelendi · {{ $shipment->exception_acknowledged_at->format('d.m.Y H:i') }}</span>
          @endif
        </div>
        @if($shipment->exception_note)
          <div class="ship-ops-review-note"><strong>Operasyon notu:</strong> {{ $shipment->exception_note }}</div>
        @endif
        @if((\App\Support\ShippingStatus::isException($shipment->remote_status)
            || $shipment->state === 'uncertain' || $shipment->sync_failure_count >= 2)
            && !$shipment->exception_acknowledged_at)
          <form class="ship-ops-review-form" method="POST"
            action="{{ route('admin.shipping-operations.review', $shipment) }}">
            @csrf
            <label for="ship-review-{{ $shipment->id }}">İnceleme ve aksiyon notu (zorunlu)</label>
            <textarea id="ship-review-{{ $shipment->id }}" name="reason" required minlength="15"
              maxlength="500" rows="2" placeholder="Kargo sağlayıcı panelinde kontrol edildi; yapılacak işlemler..."></textarea>
            <button type="submit" class="admin-secondary"><x-admin-icon name="check"/> İncelendi olarak kaydet</button>
          </form>
        @endif
        <a class="admin-text-link" href="{{ route('admin.orders.show', $shipment->order) }}">Sipariş detayını aç <x-admin-icon name="arrow-right"/></a>
      </article>
    @empty
      <p class="admin-empty">Bu filtrede gönderi yok.</p>
    @endforelse
  </div>
  <div class="pagination-wrap">{{ $shipments->links() }}</div>
</section>
@endsection
