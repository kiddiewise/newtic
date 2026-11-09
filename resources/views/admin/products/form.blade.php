@extends('admin.layout')
@section('title', ($product->exists ? 'Ürün Düzenle' : 'Yeni Ürün').' | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div><a class="admin-back" href="{{ route('admin.products.index') }}">← Ürünler</a><p class="admin-eyebrow">ÜRÜN YÖNETİMİ</p><h1>{{ $product->exists ? 'Ürünü düzenle' : 'Yeni ürün ekle' }}</h1><p>Her gramajı ayrı stok kodu, fiyat ve stok ile kaydedebilirsiniz.</p></div>
  @if($product->exists)<a class="admin-secondary" href="{{ route('product.show', $product->slug) }}" target="_blank" rel="noopener">Ürünü gör ↗</a>@endif
</div>
@if($categories->isEmpty())
  <div class="admin-alert admin-alert-error">Önce bir kategori oluşturmalısınız. <a href="{{ route('admin.categories.create') }}">Yeni kategori ekle</a></div>
@else
  <div class="admin-panel">
    <form class="admin-form admin-settings-grid" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      @if($product->exists) @method('PATCH') @endif
      <label>Ürün adı *<input name="name" required maxlength="190" value="{{ old('name', $product->name) }}" placeholder="Örn. Renkli Surf Kurşun 125 gr"></label>
      <label>Stok kodu (SKU) *<input name="sku" required maxlength="100" value="{{ old('sku', $product->sku) }}" placeholder="Örn. POL-SURF-125"></label>
      <label>Kategori *
        <select name="primary_category_id" required>
          <option value="">Kategori veya alt kategori seçin</option>
          @foreach($categories as $choice)
            @php $category = $choice['category']; @endphp
            <option value="{{ $category->id }}" @selected(old('primary_category_id', $product->primary_category_id) == $category->id)>{{ $choice['label'] }}{{ !$category->is_active ? ' (pasif)' : '' }}</option>
          @endforeach
        </select>
      </label>
      <label>Stok adedi *<input type="number" name="stock_quantity" required min="0" max="100000000" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}"></label>
      <label>Normal fiyat (₺) *<input type="number" data-price-regular name="regular_price" inputmode="decimal" required min="0.01" max="9999999999.99" step="0.01" value="{{ old('regular_price', $product->compare_at_price ?? $product->price) }}" placeholder="Örn. 159.90"></label>
      <label>İndirimli fiyat (₺) <input type="number" data-price-sale name="sale_price" inputmode="decimal" min="0.01" step="0.01" value="{{ old('sale_price', $product->compare_at_price ? $product->price : '') }}" placeholder="Örn. 119.90 (isteğe bağlı)"></label>
      <div class="admin-full admin-discount-preview" data-discount-preview role="status" aria-live="polite">İndirimli fiyat girildiğinde indirim oranı otomatik hesaplanır.</div>
      <label class="admin-full">Ürün açıklaması<textarea name="description" rows="5" maxlength="10000" placeholder="Ürünün özelliklerini yazın">{{ old('description', $product->description) }}</textarea></label>
      <label class="admin-full">Ürün fotoğrafları (bir defada en fazla 8 fotoğraf; toplam 20)
        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/avif">
        <small>Her fotoğraf en fazla 5 MB. İlk yüklenen fotoğraf kapak olarak gösterilir. Yeni fotoğraf eklemek için ürünü daha sonra tekrar düzenleyebilirsiniz.</small>
      </label>
      <label class="admin-check admin-full"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))> Ürün mağazada yayınlansın</label>
      <div class="admin-form-actions"><a class="admin-secondary" href="{{ route('admin.products.index') }}">Vazgeç</a><button class="admin-primary" type="submit">{{ $product->exists ? 'Ürünü güncelle' : 'Ürünü oluştur' }}</button></div>
    </form>
  </div>
@endif
@if($product->exists)
  <div class="admin-panel">
    <div class="admin-panel-title"><h2>Ürün fotoğraf galerisi</h2><span>{{ $product->images->count() }} fotoğraf</span></div>
    @if($product->images->isEmpty())
      <p class="admin-description">Henüz fotoğraf yüklenmedi. Yukarıdaki formdan fotoğraf ekleyin.</p>
    @else
      <div class="admin-photo-grid">
        @foreach($product->images as $image)
          <div class="admin-photo-tile">
            <img src="{{ asset($image->path) }}" alt="{{ $product->name }} fotoğrafı" loading="lazy" width="160" height="160">
            @if($product->image_path === $image->path)<span class="admin-cover-label">Kapak</span>@endif
            <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Bu fotoğraf silinsin mi?')">
              @csrf @method('DELETE')
              <button class="admin-danger" type="submit">Fotoğrafı sil</button>
            </form>
          </div>
        @endforeach
      </div>
    @endif
  </div>
  <div class="admin-panel">
    <div class="admin-panel-title"><h2>Ürün istatistiği</h2></div>
    <div class="admin-metric"><strong>{{ number_format($product->view_count, 0, ',', '.') }}</strong><span>Toplam görüntülenme (24 saatlik oturum filtresi)</span></div>
  </div>
@endif
@endsection
