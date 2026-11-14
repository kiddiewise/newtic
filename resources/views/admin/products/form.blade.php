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
        <label class="admin-editor-wide">Kısa açıklama<textarea name="short_description" rows="3" maxlength="10000">{{ old('short_description', $product->short_description) }}</textarea></label>
      </div>
    </section>
    <section class="admin-panel admin-editor-section">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-blue"><x-admin-icon name="search" /></span><div><h2>SEO ve kargo bilgileri</h2><p>WooCommerce'den taşınan değerler burada düzenlenebilir.</p></div></div>
      <div class="admin-form admin-editor-fields">
        <label>Ürün ağırlığı (kg)<input type="number" name="weight_kg" min="0" step="0.001" value="{{ old('weight_kg', $product->weight_kg) }}" placeholder="0.075"></label>
        <label class="admin-editor-wide">SEO başlığı<input name="seo_title" maxlength="255" value="{{ old('seo_title', $product->seo_title) }}"></label>
        <label class="admin-editor-wide">SEO açıklaması<textarea name="seo_description" rows="3" maxlength="2000">{{ old('seo_description', $product->seo_description) }}</textarea></label>
        <label class="admin-editor-wide">SEO odak anahtar kelimesi<input name="seo_focus_keyword" maxlength="255" value="{{ old('seo_focus_keyword', $product->seo_focus_keyword) }}"></label>
        <label class="admin-editor-toggle admin-editor-wide">
          <input type="hidden" name="seo_noindex" value="0">
          <input type="checkbox" name="seo_noindex" value="1" @checked(old('seo_noindex', $product->seo_noindex ?? false))>
          <span class="admin-toggle-track" aria-hidden="true"></span>
          <span><strong>Bu ürünü Google indekslemesinden hariç tut</strong>
            <small>Ürün site haritasından çıkarılır, meta robots noindex gönderilir ve Merchant ürün kaynağında yer almaz.</small></span>
        </label>
        <p class="admin-description admin-editor-wide">Canonical URL bu ürünün kalıcı bağlantısından otomatik oluşur. Google Product/Offer verileri gerçek fiyat ve stoktan hazırlanır; yıldızlar onaylanmış gerçek yorumlardan alınır.</p>
        <label class="admin-editor-wide">Google Merchant marka bilgisi
          <input name="google_brand" maxlength="120" value="{{ old('google_brand', $product->google_brand) }}" placeholder="Boşsa Polaris Kurşun kullanılır">
          <small>Marka adı ürünün gerçek markası olmalıdır.</small>
        </label>
        <label>GTIN / Barkod (varsa)
          <input name="gtin" maxlength="14" inputmode="numeric" value="{{ old('gtin', $product->gtin) }}" placeholder="Örn. 869XXXXXXXXXX">
          <small>Yalnızca gerçek üretici barkodunu yazın.</small>
        </label>
        <label>MPN / Üretici parça numarası (varsa)
          <input name="mpn" maxlength="70" value="{{ old('mpn', $product->mpn) }}" placeholder="Gerçek üretici parça numarası">
        </label>
        @if($product->exists && $product->importSource)
          <p class="admin-description admin-editor-wide">İçe aktarıldı: {{ $product->importSource->source_site }} · Kaynak: {{ $product->importSource->source_key }}. Orijinal WooCommerce özel alanları denetim kaydında korunur.</p>
        @endif
      </div>
    </section>
    <section class="admin-panel admin-editor-section">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-purple"><x-admin-icon name="chart" /></span><div><h2>Fiyat ve stok</h2><p>Her gramaj ayrı SKU olarak kaydedilir; ürünler birleştirilmez.</p></div></div>
      <div class="admin-form admin-editor-fields">
        <label>Normal fiyat (₺) *<input type="number" data-price-regular name="regular_price" inputmode="decimal" required min="0.01" max="9999999999.99" step="0.01" value="{{ old('regular_price', $product->compare_at_price ?? $product->price) }}" placeholder="Örn. 159.90"></label>
        <label>İndirimli fiyat (₺)<input type="number" data-price-sale name="sale_price" inputmode="decimal" min="0.01" step="0.01" value="{{ old('sale_price', $product->compare_at_price ? $product->price : '') }}" placeholder="İsteğe bağlı"></label>
        <label>Stok adedi *<input type="number" name="stock_quantity" inputmode="numeric" required min="0" max="100000000" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}"></label>
        <label class="admin-editor-toggle admin-editor-wide"><input type="hidden" name="tracks_stock" value="0"><input type="checkbox" name="tracks_stock" value="1" @checked(old('tracks_stock', $product->exists ? $product->tracks_stock : true))>
          <span class="admin-toggle-track" aria-hidden="true"></span><span><strong>Stok adedini takip et</strong><small>Kapalıysa ürün stokta kabul edilir ve siparişlerde sayaç azalmaz.</small></span>
        </label>
        <div class="admin-editor-wide admin-discount-preview" data-discount-preview role="status" aria-live="polite">İndirimli fiyat girildiğinde indirim oranı otomatik hesaplanır.</div>
      </div>
    </section>
    <section class="admin-panel admin-editor-section">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-mint"><x-admin-icon name="layers" /></span>
        <div><h2>Ürün seçenekleri / varyasyon</h2><p>Gramaj ayrı ürün olarak kalır; iğne kalınlığı gibi ek seçimleri burada tanımlayabilirsin.</p></div>
      </div>
      <div class="admin-form admin-editor-fields">
        <label class="admin-editor-wide">Seçenek başlığı
          <input name="option_label" maxlength="80" value="{{ old('option_label', $product->option_label ?: 'İğne kalınlığı') }}" placeholder="Örn. İğne kalınlığı">
        </label>
        <label class="admin-editor-wide">Seçenek değerleri (her satıra bir tane)
          <textarea name="options_text" rows="5" maxlength="3000" placeholder="0,25&#10;0,30&#10;0,35">{{ old('options_text', $product->options->pluck('value')->implode("\n")) }}</textarea>
          <small>Örnek: 0,25 · 0,30 · 0,35. Boş bırakırsan bu ürün seçenek istemez. En fazla 30 seçenek; her seçeneğin en fazla 60 karakteri olabilir.</small>
        </label>
      </div>
      <p class="admin-description">75 gr, 100 gr ve 125 gr ürünlerini yine ayrı SKU olarak açarsın. Her ürünün kendi iğne seçenekleri olabilir. Şu an bütün iğne seçenekleri ürünün ortak stok adedini ve fiyatını kullanır.</p>
    </section>
    <section class="admin-panel admin-editor-section">
      <div class="admin-editor-heading">
        <span class="admin-editor-heading-icon is-purple"><x-admin-icon name="star" /></span>
        <div><h2>Ürün rozetleri</h2><p>Görselin hemen altındaki pastel renkli şeritleri seç.</p></div>
      </div>
      @php
        $selectedBadgeIds = collect(old('badge_ids', $product->exists ? $product->badges->pluck('id')->all() : []))
            ->filter()->map(fn ($id) => (int) $id)->all();
      @endphp
      <input type="hidden" name="badge_ids[]" value="">
      @if($badges->isNotEmpty())
        <div class="admin-badge-picker" aria-label="Ürüne eklenecek rozetler">
          @foreach($badges as $badge)
            <label class="admin-badge-choice {{ !$badge->is_active ? 'is-muted' : '' }}">
              <input type="checkbox" name="badge_ids[]" value="{{ $badge->id }}" @checked(in_array($badge->id, $selectedBadgeIds, true))>
              <span class="product-badge product-badge--{{ $badge->color }}">{{ $badge->name }}</span>
              @if(!$badge->is_active)<small>Gizli</small>@endif
            </label>
          @endforeach
        </div>
      @else
        <p class="admin-badge-help">Henüz rozet tanımlamadın.</p>
      @endif
      <p class="admin-badge-help">Birden fazla seçebilirsin. Pasif rozetler mağazada görünmez. <a href="{{ route('admin.product-badges.index') }}" target="_blank" rel="noopener noreferrer">Rozetleri oluştur veya renklerini düzenle</a>.</p>
      @error('badge_ids')<div class="admin-alert admin-alert-error">{{ $message }}</div>@enderror
      @error('badge_ids.*')<div class="admin-alert admin-alert-error">{{ $message }}</div>@enderror
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
  <div class="admin-panel-title"><h2>Ürün fotoğraf galerisi</h2><span>{{ $product->images->count() }} fotoğraf</span></div>
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
