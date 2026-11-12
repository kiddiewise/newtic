@extends('admin.layout')
@section('title', 'WooCommerce Aktarım #'.$import->id.' | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <a class="admin-back" href="{{ route('admin.product-imports.index') }}">← Aktarım dosyaları</a>
    <p class="admin-eyebrow">KATALOG / AKTARIM ÖNİZLEMESİ</p>
    <h1>Ürün aktarımı #{{ $import->id }}</h1>
    <p>{{ $import->original_filename }} · {{ $import->created_at->format('d.m.Y H:i') }}</p>
  </div>
  <a class="admin-secondary" href="{{ route('admin.products.index') }}">Ürünleri aç</a>
</div>
<section class="admin-panel" data-import-stage="{{ $import->status }}" data-import-url="{{ route('admin.product-imports.show', $import) }}">
  <div class="admin-panel-title">
    <h2>{{ $import->status === 'preview' ? 'Aktarım önizlemesi' : 'Aktarım ilerlemesi' }}</h2>
    <span class="import-status import-status-{{ $import->status }}">{{ ['preview'=>'Henüz başlamadı','running'=>'Devam ediyor','completed'=>'Tamamlandı'][$import->status] ?? $import->status }}</span>
  </div>
  <div class="import-summary-grid">
    <div><strong>{{ number_format($import->total_rows, 0, ',', '.') }}</strong><small>Toplam kaynak satırı</small></div>
    <div><strong data-import-processed>{{ $import->processed_rows }}</strong><small>İşlendi</small></div>
    <div><strong data-import-created>{{ $import->created_rows }}</strong><small>Yeni ürün</small></div>
    <div><strong data-import-updated>{{ $import->updated_rows }}</strong><small>Güncellendi</small></div>
    <div><strong data-import-skipped>{{ $import->skipped_rows }}</strong><small>Atlanan</small></div>
    <div><strong data-import-failed>{{ $import->failed_rows }}</strong><small>İnceleme gerekli</small></div>
  </div>
  @if($import->status === 'preview')
    <div class="import-alert">Önizleme güvenlidir: Henüz ürün, stok veya fiyat değişmedi. Gramaj/varyasyon satırları <strong>ayrı ürün</strong> olarak kaydedilecektir.</div>
    <form action="{{ route('admin.product-imports.start', $import) }}" method="POST" class="import-start-form">
      @csrf
      <label class="import-check"><input type="checkbox" name="publish_products" value="1"> <span><strong>Aktarılan ürünleri yayına al</strong><small>Varsayılan olarak yeni ürünler pasif eklenir. Taslak, gizli veya stokta olmayan ürünler yayımlanmaz; WooCommerce'te stok takibi kapalı ürünler bu ayarla korunur.</small></span></label>
      <button type="submit" class="admin-primary"><x-admin-icon name="check"/> Aktarımı başlat</button>
    </form>
  @else
    @php $pct = $import->total_rows ? (int) round($import->processed_rows * 100 / $import->total_rows) : 0; @endphp
    <progress class="import-progress" data-import-progress max="{{ $import->total_rows }}" value="{{ $import->processed_rows }}" aria-label="Ürün aktarımının ilerlemesi">{{ $pct }}%</progress>
    <p class="import-progress-caption" data-import-caption>{{ $import->processed_rows }} / {{ $import->total_rows }} satır (%{{ $pct }})</p>
    @if($import->status === 'running')
      <form data-import-step-form action="{{ route('admin.product-imports.step', $import) }}" method="POST">
        @csrf
        <button type="submit" class="admin-primary">Aktarımı sürdür</button>
      </form>
      <p class="admin-description" data-import-live-status role="status" aria-live="polite">Bu sayfa açıkken satırlar küçük partiler halinde işlenir. Sayfadan ayrılırsanız aynı linkten devam edebilirsiniz.</p>
    @else
      <p class="import-alert">Aktarım bitti. Hata veya atlanan satırları aşağıda inceleyip eksik ürünleri düzelttikten sonra CSV'yi yeniden yükleyebilirsiniz. Tekrar yükleme, aynı kaynak ID'ye ait ürünleri çoğaltmaz.</p>
    @endif
  @endif
</section>
<section class="admin-panel">
  <div class="admin-panel-title"><h2>WooCommerce kayıt türleri</h2></div>
  <div class="import-type-list">
    @foreach($types as $type => $count)<span><strong>{{ $type }}</strong> {{ number_format($count, 0, ',', '.') }} satır</span>@endforeach
  </div>
  <p class="admin-description">Varyasyonlu ana ürün satırları ayrı satış ürünü olarak oluşturulmaz; her varyasyon kendi SKU/fiyat/stok bilgileriyle kaydedilir. WooCommerce'in özel alanları kaynak kaydında saklanır.</p>
</section>
<section class="admin-panel">
  <div class="admin-panel-title"><h2>Dosya örnekleri</h2><span>İlk {{ $samples->count() }} satır</span></div>
  <div class="import-table-scroll">
    <table class="import-table"><thead><tr><th>CSV satırı</th><th>Kaynak ID</th><th>Tip</th><th>Ürün adı</th><th>SKU</th><th>Fiyat</th><th>Durum</th></tr></thead><tbody>
      @foreach($samples as $line)
        <tr>
          <td>{{ $line->row_number }}</td><td>{{ $line->woo_id ?: '—' }}</td>
          <td>{{ $line->row_type }}</td>
          <td>{{ \App\Support\WooCsvReader::get($line->payload, 'Name') ?: 'Üst üründen alınacak' }}</td>
          <td>{{ \App\Support\WooCsvReader::get($line->payload, 'SKU') ?: 'Kaynak ID ile üretilecek' }}</td>
          <td>{{ \App\Support\WooCsvReader::get($line->payload, 'Regular price') }}</td>
          <td>{{ $line->status }}</td>
        </tr>
      @endforeach
    </tbody></table>
  </div>
</section>
@if($recent->isNotEmpty())
<section class="admin-panel">
  <div class="admin-panel-title"><h2>İncelenecek satırlar</h2><span>Son {{ $recent->count() }} kayıt</span></div>
  <div class="import-table-scroll">
    <table class="import-table"><thead><tr><th>CSV satırı</th><th>Ürün</th><th>Durum</th><th>Açıklama</th></tr></thead><tbody>
      @foreach($recent as $line)
        <tr><td>{{ $line->row_number }}</td>
          <td>{{ \App\Support\WooCsvReader::get($line->payload, 'Name') }}</td>
          <td>{{ $line->status }}</td><td>{{ $line->message }}</td>
        </tr>
      @endforeach
    </tbody></table>
  </div>
</section>
@endif
@endsection
