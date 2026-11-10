@extends('admin.layout')
@section('title', ($product->exists ? 'Ürün Düzenle' : 'Yeni Ürün').' | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <a class="admin-back" href="{{ route('admin.products.index') }}">← Ürünler</a>
    <p class="admin-eyebrow">KATALOG / ÜRÜN BİLGİLERİ</p>
    <h1>{{ $product->exists ? 'Ürünü düzenle' : 'Yeni ürün ekle' }}</h1>
    <p>Ürünün bilgilerini, fiyatını, stok durumunu ve görsellerini tek ekrandan yönet.</p>
  </div>
  @if($product->exists)
    <a class="admin-secondary" href="{{ route('product.show', $product->slug) }}" target="_blank" rel="noopener noreferrer"><x-admin-icon name="external-link" /> Mağazada gör</a>
  @endif
</div>
@if($categories->isEmpty())
  <div class="admin-alert admin-alert-error"><x-admin-icon name="alert" /><span>Önce bir kategori oluşturmalısın. <a href="{{ route('admin.categories.create') }}">Yeni kategori ekle</a></span></div>
@else
@php
  $currentImage = $product->image_path ?: 'assets/products/demo-sinker.svg';
@endphp
<form class="admin-product-editor" id="admin-product-editor" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
  @csrf
  @if($product->exists) @method('PATCH') @endif
  <div class="admin-product-left">
    <section class="admin-panel admin-editor-section">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-mint"><x-admin-icon name="package" /></span><div><h2>Genel bilgiler</h2><p>Ürünün müşterilere gösterilecek bilgileri</p></div></div>
      <div class="admin-form admin-editor-fields">
        <label class="admin-editor-wide">Ürün adı *<input name="name" autocomplete="off" required maxlength="190" value="{{ old('name', $product->name) }}" placeholder="Örn. Renkli Surf Kurşun 125 gr"></label>
        <label>Stok kodu (SKU) *<input name="sku" autocomplete="off" required maxlength="100" value="{{ old('sku', $product->sku) }}" placeholder="Örn. POL-SURF-125"></label>
        <label>Kategori *<select name="primary_category_id" required>
          <option value="">Kategori veya alt kategori seçin</option>
          @foreach($categories as $choice)
            @php $category = $choice['category']; @endphp
            <option value="{{ $category->id }}" @selected(old('primary_category_id', $product->primary_category_id) == $category->id)>{{ $choice['label'] }}{{ !$category->is_active ? ' (pasif)' : '' }}</option>
          @endforeach
        </select></label>
        <label class="admin-editor-wide">Ürün açıklaması<textarea name="description" rows="7" maxlength="10000" placeholder="Ürünün özelliklerini, avantajlarını ve kullanım bilgilerini yazın">{{ old('description', $product->description) }}</textarea>
          <small>Müşteriler için açık ve anlaşılır ürün açıklaması yaz.</small>
        </label>
      </div>
    </section>
    <section class="admin-panel admin-editor-section">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-purple"><x-admin-icon name="chart" /></span><div><h2>Fiyat ve stok</h2><p>Her gramaj ayrı SKU olarak kaydedilir; ürünler birleştirilmez.</p></div></div>
      <div class="admin-form admin-editor-fields">
        <label>Normal fiyat (₺) *<input type="number" data-price-regular name="regular_price" inputmode="decimal" required min="0.01" max="9999999999.99" step="0.01" value="{{ old('regular_price', $product->compare_at_price ?? $product->price) }}" placeholder="Örn. 159.90"></label>
        <label>İndirimli fiyat (₺)<input type="number" data-price-sale name="sale_price" inputmode="decimal" min="0.01" step="0.01" value="{{ old('sale_price', $product->compare_at_price ? $product->price : '') }}" placeholder="İsteğe bağlı"></label>
        <label>Stok adedi *<input type="number" name="stock_quantity" inputmode="numeric" required min="0" max="100000000" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}"></label>
        <div class="admin-editor-wide admin-discount-preview" data-discount-preview role="status" aria-live="polite">İndirimli fiyat girildiğinde indirim oranı otomatik hesaplanır.</div>
      </div>
    </section>
    <section class="admin-panel admin-editor-section">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-blue"><x-admin-icon name="check" /></span><div><h2>Yayın durumu</h2><p>Ürünün mağazadaki görünürlüğünü belirle</p></div></div>
      <label class="admin-editor-toggle"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))>
        <span class="admin-toggle-track" aria-hidden="true"></span><span><strong>Ürün mağazada yayınlansın</strong><small>Pasif ürünler müşteri kataloğunda görünmez.</small></span>
      </label>
    </section>
  </div>
  <div class="admin-product-right">
    <section class="admin-panel admin-editor-section admin-editor-media">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-orange"><x-admin-icon name="image" /></span><div><h2>Ürün görselleri</h2><p>Kaliteli fotoğraflar alışveriş deneyimini güçlendirir.</p></div></div>
      <div class="admin-media-hero"><img data-admin-product-hero src="{{ asset($currentImage) }}" alt="{{ $product->name ?: 'Ürün önizlemesi' }}" width="340" height="340"></div>
      @if($product->exists && $product->images->isNotEmpty())
      <div class="admin-media-thumbnails">
        @foreach($product->images as $image)
          <img src="{{ asset($image->path) }}" width="58" height="58" loading="lazy" alt="Ürün görseli {{ $loop->iteration }}" title="Mevcut görsel {{ $loop->iteration }}">
        @endforeach
      </div>
      @endif
      <div class="admin-media-thumbnails" data-admin-new-thumbnails hidden aria-label="Yeni seçilen görseller"></div>
      <label class="admin-media-upload">
        <x-admin-icon name="plus" />
        <span><strong>Fotoğraf ekle</strong><small>PNG, JPG, WebP, AVIF · 5 MB · tek seferde en fazla 8</small></span>
        <input type="file" data-admin-image-input name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/avif">
      </label>
      <p class="admin-description">Toplam en fazla 20 görsel. İlk yüklenen ürün fotoğrafı kapak görseli olarak gösterilir.</p>
    </section>
    <section class="admin-panel admin-editor-section admin-editor-help">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-mint"><x-admin-icon name="info" /></span><div><h2>Ürün düzenleme ipuçları</h2><p>Daha iyi ürün sayfaları için</p></div></div>
      <p>Gramajları birleştirmeden her ağırlığı ayrı ürün olarak tut. Tutarlı SKU, doğru stok ve açıklayıcı fotoğraflar kullan.</p>
      @if($product->exists)<p><strong>{{ number_format($product->view_count, 0, ',', '.') }}</strong> toplam görüntülenme (24 saatlik oturum filtresi).</p>@endif
    </section>
  </div>
  <div class="admin-editor-actions">
    <span><x-admin-icon name="info" /> Fiyat, stok ve yayın bilgileri kaydedilince güncellenir.</span>
    <div><a href="{{ route('admin.products.index') }}" class="admin-secondary">Vazgeç</a>
    <button class="admin-primary" type="submit" data-admin-saving><x-admin-icon name="check" /> {{ $product->exists ? 'Değişiklikleri kaydet' : 'Ürünü oluştur' }}</button></div>
  </div>
</form>
@endif
@if($product->exists)
<section class="admin-panel admin-editor-existing-media">
  <div class="admin-panel-title"><h2>Mevcut fotoğrafları yönet</h2><span>{{ $product->images->count() }} fotoğraf</span></div>
  @if($product->images->isEmpty())
    <p class="admin-description">Henüz fotoğraf yüklenmedi. Üstteki alandan fotoğraf ekleyebilirsin.</p>
  @else
    <div class="admin-photo-grid">
      @foreach($product->images as $image)
        <div class="admin-photo-tile">
          <img src="{{ asset($image->path) }}" alt="{{ $product->name }} fotoğrafı" loading="lazy" width="160" height="160">
          @if($product->image_path === $image->path)<span class="admin-cover-label">Kapak</span>@endif
          <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Bu fotoğraf silinsin mi?')">
            @csrf @method('DELETE')
            <button class="admin-danger" type="submit"><x-admin-icon name="close" /> Sil</button>
          </form>
        </div>
      @endforeach
    </div>
  @endif
</section>
@endif
@endsection