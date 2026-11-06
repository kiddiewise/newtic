@extends('admin.layout')
@section('title', 'Ana Sayfa Yönetimi | NEWTIC')
@section('content')
  <div class="admin-page-heading">
    <div><p class="admin-eyebrow">VİTRİN YÖNETİMİ</p><h1>Ana sayfa blokları</h1><p>Blokları düzenleyin ve sıralamalarını belirleyin.</p></div>
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
        <a class="admin-secondary" href="{{ route('admin.sections.edit', $section) }}">Düzenle</a>
      </div>
    @empty
      <p class="admin-empty">Henüz vitrin bloğu eklenmedi.</p>
    @endforelse
  </div>
  <div class="admin-panel">
    <div class="admin-panel-title"><h2>Yeni vitrin bloğu</h2></div>
    <form class="admin-add-section" action="{{ route('admin.sections.store') }}" method="POST">
      @csrf
      <label>Blok türü
        <select name="type" required>
          <option value="slider">Tam genişlik görsel slider</option>
          <option value="categories">Kategoriler</option>
          <option value="products">Popüler ürünler</option>
          <option value="banner">Yatay kampanya bannerları</option>
        </select>
      </label>
      <button class="admin-primary" type="submit">Blok ekle</button>
    </form>
  </div>
@endsection
