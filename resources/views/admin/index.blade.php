@extends('admin.layout')
@section('title', 'Anasayfa Düzenle | NEWTIC')
@section('content')
  <div class="admin-page-heading">
    <div><p class="admin-eyebrow">SİSTEM AYARLARI</p><h1>Anasayfa Düzenle</h1><p>Ana sayfa blokları</p><p>Blokları düzenleyin ve sıralamalarını belirleyin.</p></div>
  </div>
  <div class="admin-quick-links">
    <a href="{{ route('admin.products.create') }}"><strong>+ Ürün ekle</strong><small>Fotoğraf, fiyat ve stok</small></a>
    <a href="{{ route('admin.categories.create') }}"><strong>+ Kategori ekle</strong><small>Kategori adı ve görseli</small></a>
    <a href="{{ route('admin.store-settings.edit') }}"><strong>Kargo ayarları ↗</strong><small>Ücretsiz kargo ve teslimat ücreti</small></a>
    <a href="{{ route('admin.orders.index') }}"><strong>Siparişleri yönet ↗</strong><small>Müşteri, sipariş ve teslimat durumları</small></a>
    <a href="{{ route('admin.appearance.edit') }}"><strong>Mobil / masaüstü logoları ↗</strong><small>Marka görsellerini yönet</small></a>
    <a href="{{ route('admin.announcements.index') }}"><strong>Kayan duyurular ↗</strong><small>Zamanlı kampanya mesajları oluştur</small></a>
  </div>
  <div class="admin-panel">
    <div class="admin-panel-title"><h2>Yayındaki bloklar</h2><span>{{ $sections->count() }} blok</span></div>
    @forelse($sections as $section)
      <div class="admin-section-row">
        <div class="admin-section-icon" aria-hidden="true">{{ match($section->type){'slider'=>'▧','categories'=>'▦','products'=>'◈',default=>'▤'} }}</div>
        <div class="admin-section-info">
          <strong>{{ $section->title ?: 'Başlıksız' }}</strong>
          <small>{{ match($section->type){'slider'=>'Görsel slider','categories'=>'Kategoriler','products'=>'Ürün seçkisi',default=>'Banner alanı'} }} · Sıra: {{ $section->sort_order }}</small>
        </div>
        <span class="admin-status {{ $section->is_active ? 'is-active' : '' }}">{{ $section->is_active ? 'Aktif' : 'Pasif' }}</span>
        <div class="admin-section-actions" aria-label="{{ $section->title ?: 'Başlıksız' }} için blok işlemleri">
          <form method="POST" action="{{ route('admin.sections.move', $section) }}">
            @csrf
            <input type="hidden" name="direction" value="up">
            <button class="admin-section-action" type="submit" aria-label="{{ $section->title ?: 'Başlıksız' }} bloğunu yukarı taşı" title="Bir sıra yukarı taşı" @disabled($loop->first)>
              <x-admin-icon name="arrow-up" />
            </button>
          </form>
          <form method="POST" action="{{ route('admin.sections.move', $section) }}">
            @csrf
            <input type="hidden" name="direction" value="down">
            <button class="admin-section-action" type="submit" aria-label="{{ $section->title ?: 'Başlıksız' }} bloğunu aşağı taşı" title="Bir sıra aşağı taşı" @disabled($loop->last)>
              <x-admin-icon name="arrow-down" />
            </button>
          </form>
          <form method="POST" action="{{ route('admin.sections.destroy', $section) }}"
                onsubmit="return confirm('Bu vitrin bloğu ve ona bağlı tüm görsel/ürün seçimleri silinsin mi? Bu işlem geri alınamaz.')">
            @csrf
            @method('DELETE')
            <button class="admin-section-action admin-section-action-delete" type="submit" aria-label="{{ $section->title ?: 'Başlıksız' }} bloğunu sil" title="Bloğu sil">
              <x-admin-icon name="trash-2" />
            </button>
          </form>
        </div>
        <a class="admin-secondary" href="{{ route('admin.sections.edit', $section) }}">Düzenle</a>
      </div>
    @empty
      <p class="admin-empty">Henüz vitrin bloğu eklenmedi.</p>
    @endforelse
  </div>
  <div class="admin-panel">
    <div class="admin-panel-title"><h2>Yeni vitrin bloğu</h2></div>
    <button class="admin-primary" type="button" data-showcase-open aria-haspopup="dialog" aria-controls="showcase-dialog">+ Blok ekle</button>
    <dialog id="showcase-dialog" class="showcase-dialog" aria-labelledby="showcase-dialog-title">
      <div class="showcase-dialog-heading"><h3 id="showcase-dialog-title">Yeni blok ekle</h3><button type="button" class="admin-secondary" data-showcase-close aria-label="Pencereyi kapat">✕</button></div>
      <p>Eklemek istediğiniz vitrin alanını seçin. İçerik eklenene kadar ana sayfada görünmez.</p>
    <form class="admin-add-section" action="{{ route('admin.sections.store') }}" method="POST">
      @csrf
      <label>Blok türü
        <select name="type" required>
          <option value="slider">Tam genişlik görsel slider</option>
          <option value="categories">Kategoriler</option>
          <option value="products">Ürün carousel (yatay kaydırmalı)</option>
          <option value="banner">Yatay kampanya bannerları</option>
        </select>
      </label>
      <button class="admin-primary" type="submit">Bloğu oluştur</button>
    </form>
    </dialog>
  </div>
@endsection
