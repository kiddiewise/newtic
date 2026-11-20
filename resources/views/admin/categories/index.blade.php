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
    @php
      $category = $categories->get($choice['category']->id);
      $extraProducts = (int) ($additionalCounts[$category->id] ?? 0);
      $totalProducts = $category->products_count + $extraProducts;
    @endphp
    <div class="admin-catalog-row category-tree-admin-row" style="--depth:{{ min(5, $choice['depth']) }}">
      <img class="admin-catalog-photo" src="{{ asset($category->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" loading="lazy" width="68" height="68">
      <div class="admin-catalog-info">
        <strong>{{ $choice['label'] }}</strong>
        <small>
          {{ $category->products_count }} doğrudan ürün ·
          @if($extraProducts) {{ $extraProducts }} ek kategori ürünü · @endif
          {{ $category->children_count }} alt kategori ·
          {{ $category->banners_count }} banner
        </small>
        <span class="admin-status {{ $category->is_active ? 'is-active' : '' }}">{{ $category->is_active ? 'Yayında' : 'Pasif' }}</span>
      </div>
      <div class="category-tree-actions">
        <a class="admin-secondary" href="{{ route('admin.categories.create', ['parent_id' => $category->id]) }}">+ Alt kategori</a>
        <a class="admin-secondary" href="{{ route('admin.categories.edit', $category) }}">Düzenle</a>
        <button type="button" class="admin-danger" data-category-delete-open
          data-category-name="{{ $category->name }}"
          data-product-count="{{ $totalProducts }}"
          data-children-count="{{ $category->children_count }}"
          data-category-id="{{ $category->id }}"
          data-transfer-url="{{ route('admin.categories.transfer', $category) }}"
          data-delete-url="{{ route('admin.categories.destroy', $category) }}"
          aria-haspopup="dialog">Sil</button>
      </div>
    </div>
  @empty
    <p class="admin-empty">Henüz kategori bulunmuyor. İlk ana kategorini oluştur.</p>
  @endforelse
</div>
<p class="admin-description">Kategoriyi silmeden önce bağlı ürünleri tek işlemle taşıyabilirsin. Alt kategoriler otomatik silinmez veya taşınmaz.</p>

<dialog class="category-transfer-dialog" data-category-transfer-dialog aria-labelledby="category-transfer-title" aria-describedby="category-transfer-description">
  <div class="category-transfer-head">
    <div>
      <p class="admin-eyebrow">KATEGORİ YÖNETİMİ</p>
      <h2 id="category-transfer-title">Kategoriyi yönet</h2>
    </div>
    <button type="button" class="category-transfer-close" data-category-transfer-close aria-label="Pencereyi kapat">×</button>
  </div>
  <p id="category-transfer-description" class="category-transfer-description" data-category-transfer-description></p>
  <p class="category-transfer-warning" data-category-transfer-children hidden></p>
  <form method="POST" data-category-transfer-form>
    @csrf
    <label for="category-transfer-target">Ürünleri taşımak istediğiniz kategori</label>
    <select id="category-transfer-target" name="target_category_id" required>
      <option value="">Kategori seçin</option>
      @foreach($choices as $choice)
        <option value="{{ $choice['category']->id }}">
          {{ $choice['label'] }}{{ !$choice['category']->is_active ? ' (pasif)' : '' }}
        </option>
      @endforeach
    </select>
    <p class="category-transfer-hint">Ana kategori ve ek kategori bağlantıları güncellenir. Ürünlerin kendisi, stokları ve siparişleri korunur. Pasif hedef kategorideki ürünler mağazada görünmeyebilir.</p>
    <div class="category-transfer-actions">
      <button type="button" class="admin-secondary" data-category-transfer-close>Vazgeç</button>
      <button type="submit" class="admin-secondary" name="operation" value="move" data-category-transfer-only>Ürünleri Taşı</button>
      <button type="submit" class="admin-danger" name="operation" value="move_and_delete" data-category-transfer-and-delete>Ürünleri Taşı ve Sil</button>
    </div>
  </form>
  <form method="POST" data-category-empty-delete hidden>
    @csrf @method('DELETE')
    <div class="category-transfer-actions">
      <button type="button" class="admin-secondary" data-category-transfer-close>Vazgeç</button>
      <button type="submit" class="admin-danger">Boş Kategoriyi Sil</button>
    </div>
  </form>
</dialog>
@endsection
