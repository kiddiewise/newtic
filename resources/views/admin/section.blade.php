@extends('admin.layout')
@section('title', 'Vitrin Bölümü | NEWTIC')
@section('content')
  <div class="admin-page-heading">
    <div><a class="admin-back" href="{{ route('admin.index') }}">← Ana sayfa blokları</a><p class="admin-eyebrow">BÖLÜM AYARLARI</p><h1>{{ $section->title ?: 'Bölüm düzenle' }}</h1>
      <p>Bu bölümün yayın durumunu, sırasını ve içeriğini belirleyin.</p></div>
  </div>
  <div class="admin-panel">
    <div class="admin-panel-title"><h2>Genel ayarlar</h2></div>
    <form action="{{ route('admin.sections.update', $section) }}" method="POST" class="admin-form admin-settings-grid">
      @csrf @method('PATCH')
      <label>Bölüm başlığı<input type="text" name="title" maxlength="100" value="{{ old('title', $section->title) }}"></label>
      <label>Görünme sırası<input type="number" name="sort_order" min="0" max="10000" required value="{{ old('sort_order', $section->sort_order) }}"></label>
      @if($section->type === 'slider')
        <label>Otomatik geçiş süresi (ms)<input name="interval_ms" type="number" min="2500" max="15000" value="{{ data_get($section->settings, 'interval_ms', 5500) }}"></label>
      @endif
      <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($section->is_active)>Bu bölüm yayında olsun</label>
      <div class="admin-form-actions"><button type="submit" class="admin-primary">Ayarları kaydet</button></div>
    </form>
  </div>
  @if(in_array($section->type, ['slider', 'banner'], true))
    <div class="admin-panel">
      <div class="admin-panel-title"><h2>{{ $section->type === 'slider' ? 'Slider görselleri' : 'Banner görselleri' }}</h2><span>{{ $section->slides->count() }} görsel</span></div>
      <p class="admin-description">Her banner yalnız görselden oluşur. Üzerine ek başlık, açıklama veya buton yerleştirilmez. Mobil görsel eklemezseniz masaüstü görseli kullanılır.</p>
      @foreach($section->slides as $slide)
        <article class="admin-slide-item">
          <img src="{{ $slide->desktop_image_url }}" alt="Banner {{ $loop->iteration }}" width="280" height="112" loading="lazy">
          <div class="admin-slide-controls">
            <form action="{{ route('admin.slides.update', [$section,$slide]) }}" method="POST" enctype="multipart/form-data" class="admin-form admin-settings-grid">
              @csrf @method('PATCH')
              <label>Sıralama<input type="number" name="sort_order" min="0" max="10000" required value="{{ $slide->sort_order }}"></label>
              <label>Bağlantı (isteğe bağlı)<input type="url" name="link_url" placeholder="https://" value="{{ $slide->link_url }}"></label>
              <label>Yeni masaüstü görseli<input type="file" name="desktop_image" accept="image/jpeg,image/png,image/webp,image/avif"></label>
              <label>Yeni mobil görseli<input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp,image/avif"></label>
              <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($slide->is_active)>Yayında</label>
              <div class="admin-form-actions"><button class="admin-secondary" type="submit">Bannerı güncelle</button></div>
            </form>
            <form method="POST" action="{{ route('admin.slides.destroy', [$section,$slide]) }}" onsubmit="return confirm('Bu banner silinsin mi?')">
              @csrf @method('DELETE')<button type="submit" class="admin-danger">Bannerı sil</button>
            </form>
          </div>
        </article>
      @endforeach
    </div>
    <div class="admin-panel">
      <div class="admin-panel-title"><h2>Yeni banner yükle</h2></div>
      <form action="{{ route('admin.slides.store', $section) }}" method="POST" enctype="multipart/form-data" class="admin-form admin-settings-grid">
        @csrf
        <label>Masaüstü görseli (zorunlu)<input type="file" name="desktop_image" accept="image/jpeg,image/png,image/webp,image/avif" required></label>
        <label>Mobil görseli (isteğe bağlı)<input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp,image/avif"></label>
        <label class="admin-full">Bağlantı adresi (isteğe bağlı)<input name="link_url" type="url" maxlength="2048" placeholder="https://..."></label>
        <div class="admin-form-actions"><button class="admin-primary" type="submit">Görsel ekle</button></div>
      </form>
    </div>
  @elseif(in_array($section->type, ['categories', 'products'], true))
    @php
      $isCategory = $section->type === 'categories';
      $records = $isCategory ? $categories : $products;
      $selected = $isCategory ? $section->categories : $section->products;
    @endphp
    <div class="admin-panel">
      <div class="admin-panel-title"><h2>{{ $isCategory ? 'Görünecek kategoriler' : 'Popüler ürünler' }}</h2></div>
      <p class="admin-description">Kutucuğu işaretlediğiniz kayıtları vitrinde gösteririz. Sıra numarası küçük olan önce görünür. Pasif kayıtlar listeye eklenemez.</p>
      <form action="{{ route('admin.sections.selections', $section) }}" method="POST">
        @csrf @method('PUT')
        <div class="admin-choices">
          @forelse($records as $record)
            @php $existing = $selected->firstWhere('id', $record->id); @endphp
            <div class="admin-choice">
              <label class="admin-check"><input type="checkbox" name="selected[]" value="{{ $record->id }}" @checked($existing) @disabled(!$record->is_active)>
                <span>{{ $record->name }} @unless($record->is_active)<small>(pasif)</small>@endunless</span>
              </label>
              <label class="admin-order">Sıra<input type="number" name="position[{{ $record->id }}]" min="0" max="10000" value="{{ $existing?->pivot?->sort_order ?? $loop->index }}"></label>
            </div>
          @empty
            <p class="admin-empty">Henüz {{ $isCategory ? 'kategori' : 'ürün' }} bulunmuyor.</p>
          @endforelse
        </div>
        <div class="admin-form-actions"><button type="submit" class="admin-primary">Seçimleri kaydet</button></div>
      </form>
    </div>
    @if($isCategory)
      <div class="admin-panel">
        <div class="admin-panel-title"><h2>Kategori görselleri</h2></div>
        <p class="admin-description">Mobil kısayollarda gösterilecek fotoğrafları yönetin.</p>
        @foreach($categories as $category)
          <form class="admin-image-update" action="{{ route('admin.categories.image', $category) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <img src="{{ asset($category->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" width="65" height="65">
            <span>{{ $category->name }}</span>
            <label class="sr-only" for="category-image-{{ $category->id }}">Yeni görsel seç</label>
            <input id="category-image-{{ $category->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/avif" required>
            <button class="admin-secondary" type="submit">Güncelle</button>
          </form>
        @endforeach
      </div>
    @endif
  @endif
@endsection
