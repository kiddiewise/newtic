@extends('admin.layout')
@section('title', 'WooCommerce Ürün Aktarımı | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <p class="admin-eyebrow">KATALOG / ÜRÜN AKTARIMI</p>
    <h1>WooCommerce'den ürünleri taşı</h1>
    <p>Ürünleri, ayrı gramaj ve SKU'ları, görselleri ve kaynak bilgilerini kontrollü şekilde aktar.</p>
  </div>
  <a class="admin-secondary" href="{{ route('admin.products.index') }}">← Ürünlere dön</a>
</div>
<section class="admin-panel import-guide">
  <div class="admin-panel-title"><h2>WordPress'te hazırlık</h2><span>3 kolay adım</span></div>
  <ol class="import-steps">
    <li>Eski mağazada <strong>WordPress → Ürünler → Tüm Ürünler → Dışa aktar</strong> bölümüne gir.</li>
    <li><strong>Tüm sütunlar, tüm ürün tipleri, tüm kategoriler</strong> seçili olsun. <strong>Özel metaları da dışa aktar</strong> seçeneğini işaretle.</li>
    <li><strong>CSV oluştur</strong> diyerek dosyayı indir ve aşağıdan yükle. Eski mağaza bu sırada çalışmaya devam eder.</li>
  </ol>
  <p class="import-note">WooCommerce'in kendi CSV formatı desteklenir. Parçalı, tekrar başlatılabilir aktarım yapılır; ürünlerin farklı gramajları tek üründe birleştirilmez.</p>
</section>
<section class="admin-panel import-upload">
  <div class="admin-panel-title"><h2>CSV dosyasını yükle</h2><span>En fazla 25 MB / 10.000 satır</span></div>
  <form class="import-upload-form" method="POST" action="{{ route('admin.product-imports.upload') }}" enctype="multipart/form-data">
    @csrf
    <label for="import-csv">WooCommerce ürün dışa aktarım dosyası (CSV)</label>
    <input id="import-csv" type="file" name="csv" accept=".csv,text/csv" required>
    <small>Yükleme önce önizleme oluşturur. Ürünler sen aktarımı başlatana kadar değişmez.</small>
    <button type="submit" class="admin-primary"><x-admin-icon name="package"/> Dosyayı incele</button>
  </form>
</section>
<section class="admin-panel">
  <div class="admin-panel-title"><h2>Önceki aktarım dosyaları</h2><span>{{ $batches->count() }} kayıt</span></div>
  @forelse($batches as $import)
    <a class="import-batch-row" href="{{ route('admin.product-imports.show', $import) }}">
      <span><strong>{{ $import->original_filename }}</strong>
        <small>{{ $import->created_at->format('d.m.Y H:i') }} · {{ number_format($import->total_rows, 0, ',', '.') }} satır · {{ $import->processed_rows }} işlendi</small>
      </span>
      <span class="import-status import-status-{{ $import->status }}">{{ ['preview'=>'Önizleme','running'=>'Devam ediyor','completed'=>'Tamamlandı'][$import->status] ?? $import->status }}</span>
    </a>
  @empty
    <p class="admin-description">Henüz bir WooCommerce ürün aktarımı yapılmadı.</p>
  @endforelse
</section>
@endsection
