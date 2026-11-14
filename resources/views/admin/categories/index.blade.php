@extends('admin.layout')
@section('title', 'Kategoriler | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <p class="admin-eyebrow">KATALOG YÖNETİMİ</p>
    <h1>Kategori ağacı</h1>
    <p>Ana kategori, alt kategori ve daha alt seviyeleri buradan yönetebilirsin.</p>
  </div>
  <a class="admin-primary" href="{{ route('admin.categories.create') }}">+ Yeni kategori</a>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Tüm kategoriler</h2><span>{{ $categories->count() }} kategori</span></div>
  @forelse($choices as $choice)
    @php $category = $categories->get($choice['category']->id); @endphp
    <div class="admin-catalog-row category-tree-admin-row" style="--depth:{{ min(5, $choice['depth']) }}">
      <img class="admin-catalog-photo" src="{{ asset($category->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" loading="lazy" width="68" height="68">
      <div class="admin-catalog-info">
        <strong>{{ $choice['label'] }}</strong>
        <small>
          {{ $category->products_count }} doğrudan ürün ·
          {{ $category->children_count }} alt kategori ·
          {{ $category->banners_count }} banner
        </small>
        <span class="admin-status {{ $category->is_active ? 'is-active' : '' }}">{{ $category->is_active ? 'Yayında' : 'Pasif' }}</span>
      </div>
      <div class="category-tree-actions">
        <a class="admin-secondary" href="{{ route('admin.categories.create', ['parent_id' => $category->id]) }}">+ Alt kategori</a>
        <a class="admin-secondary" href="{{ route('admin.categories.edit', $category) }}">Düzenle</a>
      </div>
    </div>
  @empty
    <p class="admin-empty">Henüz kategori bulunmuyor. İlk ana kategorini oluştur.</p>
  @endforelse
</div>
<p class="admin-description">Bir alt kategori oluşturduğunda ürünler otomatik taşınmaz. Mevcut ürünleri <strong>Ürünler → Düzenle</strong> ekranından ilgili alt kategoriye taşıyabilirsin.</p>
@endsection
