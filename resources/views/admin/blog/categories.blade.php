@extends('admin.layout')
@section('title', 'Blog Kategorileri | NEWTIC')
@section('content')
<div class="admin-page-heading blog-admin-page-head">
  <div><a class="admin-back" href="{{ route('admin.blog.posts.index') }}">← Blog yönetimi</a><p class="admin-eyebrow">İÇERİK STÜDYOSU</p><h1>Blog kategorileri</h1><p>Rehber ve makaleleri ziyaretçilerin kolay keşfedebileceği biçimde düzenleyin.</p></div>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Yeni kategori oluştur</h2></div>
  <form action="{{ route('admin.blog.categories.store') }}" method="POST" class="admin-form admin-settings-grid">
    @csrf
    <label>Kategori adı *<input name="name" maxlength="190" required placeholder="Örn. Balıkçılık Rehberi"></label>
    <label>Bağlantı kısa adı (isteğe bağlı)<input name="slug" maxlength="190" placeholder="balikcilik-rehberi"><small>Boşsa başlıktan otomatik oluşturulur.</small></label>
    <label>Sıralama<input type="number" name="sort_order" value="0" min="0" max="10000"></label>
    <label class="admin-check"><input type="checkbox" name="is_active" value="1" checked>Yayında göster</label>
    <label class="admin-full">Kısa açıklama<textarea name="description" rows="3" maxlength="1500"></textarea></label>
    <div class="admin-form-actions"><button class="admin-primary">Kategori ekle</button></div>
  </form>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Kategoriler</h2><span>{{ $categories->count() }} kategori</span></div>
  @forelse($categories as $category)
    <article class="blog-admin-category-row">
      <form method="POST" action="{{ route('admin.blog.categories.update', $category) }}" class="admin-form admin-settings-grid">
        @csrf @method('PATCH')
        <label>Kategori adı<input name="name" required maxlength="190" value="{{ $category->name }}"></label>
        <label>URL kısa adı<input name="slug" required maxlength="190" value="{{ $category->slug }}"></label>
        <label>Sıra<input type="number" name="sort_order" min="0" max="10000" value="{{ $category->sort_order }}" required></label>
        <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)>Aktif</label>
        <label class="admin-full">Açıklama<textarea name="description" rows="2" maxlength="1500">{{ $category->description }}</textarea></label>
        <div class="admin-form-actions"><span class="blog-admin-muted">{{ $category->posts_count }} yazı</span><button class="admin-secondary">Değişiklikleri kaydet</button></div>
      </form>
      <form method="POST" action="{{ route('admin.blog.categories.destroy', $category) }}" onsubmit="return confirm('Bu kategori silinsin mi? Yazılar silinmeyecek, kategorisiz kalacak.')">
        @csrf @method('DELETE')
        <button class="admin-danger">Kategoriyi sil</button>
      </form>
    </article>
  @empty
    <p class="admin-empty">Henüz kategori oluşturulmadı.</p>
  @endforelse
</div>
@endsection
