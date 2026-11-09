@extends('admin.layout')
@section('title', ($category->exists ? 'Kategori Düzenle' : 'Yeni Kategori').' | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <a class="admin-back" href="{{ route('admin.categories.index') }}">← Kategori ağacı</a>
    <p class="admin-eyebrow">KATEGORİ YÖNETİMİ</p>
    <h1>{{ $category->exists ? 'Kategoriyi düzenle' : 'Yeni kategori oluştur' }}</h1>
    <p>Ana kategoriyi, alt kategoriyi veya daha alt seviyeyi seç; kategori görselini ve bannerlarını yönet.</p>
  </div>
  @if($category->exists)
    <a class="admin-secondary" href="{{ route('admin.categories.create', ['parent_id' => $category->id]) }}">+ Bunun altına kategori ekle</a>
  @endif
</div>

<div class="admin-panel">
  <form class="admin-form admin-settings-grid" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($category->exists) @method('PATCH') @endif
    <label>Kategori adı *
      <input name="name" value="{{ old('name', $category->name) }}" maxlength="190" required placeholder="Örn. Fırdöndülü Renkli Surf Kurşun">
    </label>
    <label>Bağlı olduğu ana kategori
      <select name="parent_id">
        <option value="">— Ana kategori (en üst seviye) —</option>
        @foreach($choices as $choice)
          <option value="{{ $choice['category']->id }}"
            @selected(old('parent_id', $category->parent_id ?? $suggestedParentId) == $choice['category']->id)
          >{{ $choice['label'] }}</option>
        @endforeach
      </select>
      <small>Alt kategori yapmak için bağlı olacağı kategoriyi seç. Kendi alt kategorisini üst kategori olarak seçemezsin.</small>
    </label>
    <label>Görünme sırası *
      <input type="number" name="sort_order" min="0" max="100000" required value="{{ old('sort_order', $category->sort_order ?? 0) }}">
    </label>
    <label class="admin-check">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true))>
      Kategori mağazada yayınlansın
    </label>
    <label class="admin-full">Kategori açıklaması
      <textarea name="description" rows="4" maxlength="3000" placeholder="İsteğe bağlı">{{ old('description', $category->description) }}</textarea>
    </label>
    @if($category->image_path)
      <div class="admin-full"><img class="admin-category-preview" src="{{ asset($category->image_path) }}" alt="{{ $category->name }}"></div>
    @endif
    <label class="admin-full">Alt kategori kartı için görsel (JPG, PNG, WebP, AVIF · en çok 4 MB)
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/avif">
      <small>Bu, banner değil; kategori kutusunda kullanılan kare görseldir.</small>
    </label>
    <div class="admin-form-actions">
      <a class="admin-secondary" href="{{ route('admin.categories.index') }}">Geri</a>
      <button class="admin-primary" type="submit">{{ $category->exists ? 'Değişiklikleri kaydet' : 'Kategori oluştur' }}</button>
    </div>
  </form>
</div>

@if($category->exists)
  <div class="admin-panel">
    <div class="admin-panel-title">
      <h2>Bu kategoriye özel bannerlar</h2><span>{{ $category->banners->count() }}/3</span>
    </div>
    <p class="admin-description">Masaüstü ve mobil görseli ayrı yükleyebilirsin. Banner yoksa kategori doğrudan ürünleri gösterir. En fazla 3 banner eklenebilir.</p>

    @foreach($category->banners as $banner)
      <div class="admin-banner-edit-card">
        <div class="admin-banner-preview">
          <img src="{{ asset($banner->mobile_image_path ?: $banner->desktop_image_path) }}" alt="{{ $banner->alt_text ?: 'Kategori bannerı' }}" loading="lazy">
        </div>
        <form class="admin-form admin-settings-grid" method="POST" enctype="multipart/form-data" action="{{ route('admin.categories.banners.update', [$category, $banner]) }}">
          @csrf @method('PATCH')
          <label>Açıklama / alt metin
            <input name="alt_text" maxlength="190" value="{{ $banner->alt_text }}">
          </label>
          <label>Site içi bağlantı
            <input name="link_url" maxlength="255" placeholder="/kategori/ornek" value="{{ $banner->link_url }}">
          </label>
          <label>Masaüstü görseli değiştir
            <input type="file" name="desktop_image" accept="image/jpeg,image/png,image/webp,image/avif">
          </label>
          <label>Mobil görseli değiştir
            <input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp,image/avif">
          </label>
          <label>Sıra
            <input name="sort_order" type="number" min="0" max="10000" value="{{ $banner->sort_order }}" required>
          </label>
          <label class="admin-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($banner->is_active)> Aktif
          </label>
          <label>Başlangıç zamanı (isteğe bağlı)
            <input type="datetime-local" name="starts_at" value="{{ $banner->starts_at?->format('Y-m-d\TH:i') }}">
          </label>
          <label>Bitiş zamanı (isteğe bağlı)
            <input type="datetime-local" name="ends_at" value="{{ $banner->ends_at?->format('Y-m-d\TH:i') }}">
          </label>
          <div class="admin-form-actions">
            <button class="admin-primary" type="submit">Bannerı güncelle</button>
          </div>
        </form>
        <form method="POST" action="{{ route('admin.categories.banners.destroy', [$category, $banner]) }}" onsubmit="return confirm('Banner kalıcı olarak silinsin mi?')">
          @csrf @method('DELETE')
          <button class="admin-danger" type="submit">Bannerı sil</button>
        </form>
      </div>
    @endforeach

    @if($category->banners->count() < 3)
      <h3>Yeni banner ekle</h3>
      <form class="admin-form admin-settings-grid" method="POST" enctype="multipart/form-data" action="{{ route('admin.categories.banners.store', $category) }}">
        @csrf
        <label>Masaüstü bannerı *
          <input type="file" name="desktop_image" accept="image/jpeg,image/png,image/webp,image/avif" required>
          <small>Geniş görsel önerilir (ör. 1200×500), en fazla 5 MB.</small>
        </label>
        <label>Mobil banner (isteğe bağlı)
          <input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp,image/avif">
          <small>Mobil için 16:9 oranında ayrı görsel önerilir.</small>
        </label>
        <label>Banner açıklaması
          <input name="alt_text" maxlength="190" placeholder="Örn. Surf Kurşun Kampanyası">
        </label>
        <label>Tıklanınca açılacak site içi sayfa
          <input name="link_url" maxlength="255" placeholder="/kategori/renkli-surf-kursun">
        </label>
        <label>Gösterim sırası
          <input type="number" name="sort_order" min="0" max="10000" value="{{ $category->banners->count() + 1 }}" required>
        </label>
        <label class="admin-check">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" checked> Aktif
        </label>
        <label>Başlangıç zamanı
          <input type="datetime-local" name="starts_at">
        </label>
        <label>Bitiş zamanı
          <input type="datetime-local" name="ends_at">
        </label>
        <div class="admin-form-actions"><button type="submit" class="admin-primary">Kategori bannerı ekle</button></div>
      </form>
    @endif
  </div>
@endif
<p class="admin-description">Kategori adı veya üst kategorisi değiştiğinde mevcut bağlantı adresi ve eski ürünler korunur. Ürünleri alt kategoriye taşımak için Ürünler → Düzenle ekranından seç.</p>
@endsection
