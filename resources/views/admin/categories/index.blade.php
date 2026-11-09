@extends('admin.layout')
@section('title', 'Kategoriler | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div><p class="admin-eyebrow">KATALOG YÖNETİMİ</p><h1>Kategoriler</h1><p>Kategori oluşturun, görsellerini ve yayın durumunu düzenleyin.</p></div>
  <a class="admin-primary" href="{{ route('admin.categories.create') }}">+ Yeni kategori</a>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Mağaza kategorileri</h2><span>{{ $categories->count() }} kategori</span></div>
  @forelse($categories as $category)
    <div class="admin-catalog-row">
      <img class="admin-catalog-photo" src="{{ asset($category->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" loading="lazy" width="68" height="68">
      <div class="admin-catalog-info">
        <strong>{{ $category->name }}</strong>
        <small>{{ $category->products_count }} ürün · Sıra: {{ $category->sort_order }}</small>
        <span class="admin-status {{ $category->is_active ? 'is-active' : '' }}">{{ $category->is_active ? 'Yayında' : 'Pasif' }}</span>
      </div>
      <a class="admin-secondary" href="{{ route('admin.categories.edit', $category) }}">Düzenle</a>
    </div>
  @empty
    <p class="admin-empty">Henüz kategori yok. İlk kategorinizi oluşturun.</p>
  @endforelse
</div>
@endsection
