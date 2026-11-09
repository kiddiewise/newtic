@extends('admin.layout')
@section('title', ($category->exists ? 'Kategori Düzenle' : 'Yeni Kategori').' | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><a class="admin-back" href="{{ route('admin.categories.index') }}">← Kategoriler</a><p class="admin-eyebrow">KATEGORİ YÖNETİMİ</p><h1>{{ $category->exists ? 'Kategoriyi düzenle' : 'Yeni kategori oluştur' }}</h1><p>Kategori adı, açıklaması, görseli ve yayın durumunu belirleyin.</p></div>
</div>
<div class="admin-panel">
  <form class="admin-form admin-settings-grid" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($category->exists) @method('PATCH') @endif
    <label>Kategori adı *<input name="name" value="{{ old('name', $category->name) }}" maxlength="190" required placeholder="Örn. Surf Kurşun"></label>
    <label>Görünme sırası *<input type="number" name="sort_order" min="0" max="100000" required value="{{ old('sort_order', $category->sort_order ?? 0) }}"></label>
    <label class="admin-full">Kategori açıklaması<textarea name="description" rows="4" maxlength="3000" placeholder="İsteğe bağlı">{{ old('description', $category->description) }}</textarea></label>
    @if($category->image_path)
      <div class="admin-full"><img class="admin-category-preview" src="{{ asset($category->image_path) }}" alt="{{ $category->name }}"></div>
    @endif
    <label class="admin-full">Kategori görseli (JPG, PNG, WebP, AVIF · en çok 4 MB)
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/avif">
    </label>
    <label class="admin-check admin-full"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true))> Kategori mağazada görünsün</label>
    <div class="admin-form-actions"><a class="admin-secondary" href="{{ route('admin.categories.index') }}">Geri</a><button class="admin-primary" type="submit">{{ $category->exists ? 'Değişiklikleri kaydet' : 'Kategori oluştur' }}</button></div>
  </form>
</div>
<p class="admin-description">Kategori adı değiştirildiğinde bağlantı adresi korunur; mevcut ürün bağlantıları bozulmaz. Ana sayfada görünmesi için ayrıca Vitrin Yönetimi'ndeki kategori bloğuna ekleyin.</p>
@endsection
