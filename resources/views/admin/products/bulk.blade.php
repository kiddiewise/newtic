@extends('admin.layout')
@section('title', 'Toplu Ürün Aktarımı | NEWTIC')
@section('topbar', 'Ürünler / Toplu Aktarım')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <a href="{{ route('admin.products.index') }}" class="admin-back">← Ürünler</a>
    <p class="admin-eyebrow">KATALOG / TOPLU İŞLEMLER</p>
    <h1>Ürünleri dışa aktar ve toplu yükle</h1>
    <p>Excel ile düzenlenebilen UTF-8 CSV dosyasıyla tüm ürünleri yedekleyebilir, güncelleyebilir ve yeni ürün ekleyebilirsin.</p>
  </div>
</div>

@if($errors->any())
  <div class="admin-alert admin-alert-error" role="alert">
    <strong>Aktarım yapılmadı. Hataları düzeltip tekrar deneyin.</strong>
    <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
  </div>
@endif

<div class="product-csv-grid">
  <section class="admin-panel">
    <div class="admin-panel-title"><h2>1. Ürünleri Excel'e aktar</h2></div>
    <p class="admin-description">Mevcut tüm ürünler, stoklar, fiyatlar, açıklamalar, SEO alanları, rozetler, seçenekler ve sunucudaki fotoğraf yollarıyla birlikte dışa aktarılır.</p>
    <div class="product-csv-actions">
      <a class="admin-primary" href="{{ route('admin.products.bulk.export') }}"><x-admin-icon name="package" /> Tüm ürünleri CSV indir</a>
      <a class="admin-secondary" href="{{ route('admin.products.bulk.template') }}">Boş CSV şablonu indir</a>
    </div>
    <p class="admin-description">İndirilen CSV'yi Excel'de açıp düzenleyebilirsin. SKU alanını Excel'de <strong>Metin</strong> olarak biçimlendir; başlıklara ve kategori kodlarına dokunma.</p>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-title"><h2>2. Tek dosyayla toplu ürün yükle</h2></div>
    <p class="admin-description">Düzenlediğin dosyayı seç ve tek butonla aktar. <strong>Aynı SKU güncellenir, yeni SKU yeni ürün açar.</strong> Her gramaj ayrı ürün olarak kalır.</p>
    <form class="admin-form product-csv-form" action="{{ route('admin.products.bulk.upload') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <label>CSV dosyası
        <input type="file" name="csv" accept=".csv,text/csv" required aria-describedby="bulk-csv-help">
      </label>
      <p class="admin-description" id="bulk-csv-help">En fazla {{ $limit }} ürün / dosya, 10 MB. UTF-8 ve noktalı virgülle ayrılmış CSV. Dosyada herhangi bir hata varsa hiçbir ürün değiştirilmez.</p>
      <button class="admin-primary" type="submit" data-csv-upload-submit><x-admin-icon name="check" /> Dosyayı yükle ve ürünleri aktar</button>
    </form>
  </section>
</div>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>Dosya kuralları</h2></div>
  <div class="product-csv-guide">
    <p><strong>Güncelleme anahtarı SKU'dur.</strong> Bir SKU'nun değiştirilmesi eski ürünü yeniden adlandırmaz, yeni bir ürün oluşturur. Var olan ürünlerin URL'leri ve sipariş geçmişi korunur.</p>
    <p><strong>Fiyatlar:</strong> <code>129,90</code> veya <code>129.90</code> yazabilirsin. <code>normal_fiyat</code> zorunludur; <code>indirimli_fiyat</code> boşsa indirim kaldırılır. <code>stok_takibi</code> ve <code>yayinda</code> sütunları 1 veya 0 olabilir.</p>
    <p><strong>Seçenekler, rozetler ve ek kategoriler:</strong> Birden fazla değer için <code>|</code> kullan (örnek: <code>Popüler Ürün|Hızlı Teslimat</code>). Rozetler ve kategoriler önceden sistemde tanımlanmış olmalıdır.</p>
    <p><strong>Fotoğraflar:</strong> CSV yalnızca bu sunucuda zaten bulunan <code>storage/products/...</code> veya <code>storage/imports/products/...</code> yollarını kullanabilir. Harici bağlantıdan fotoğraf indirilmez. Görsel sütunu boşsa mevcut fotoğraflar silinmez.</p>
    <p><strong>Güvenli aktarım:</strong> Dosya tamamen doğrulanmadan ürünler değişmez. Aktarım sırasında bir hata oluşursa yapılan güncellemeler geri alınır; dosyada olmayan ürünler silinmez.</p>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>Kategori kodları (kategori_slug)</h2></div>
  <p class="admin-description">İçe aktarılacak ürünün <code>kategori_slug</code> sütununda aşağıdaki kodlardan birini kullan. Var olmayan kategoriler otomatik oluşturulmaz.</p>
  <div class="product-csv-categories">
    @forelse($categories as $category)
      <div><span>{{ $category->name }}</span><code>{{ $category->slug }}</code></div>
    @empty
      <p>Henüz kategori yok. Önce <a href="{{ route('admin.categories.index') }}">kategori oluştur.</a></p>
    @endforelse
  </div>
</section>
@endsection
