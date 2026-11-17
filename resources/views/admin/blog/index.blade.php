@extends('admin.layout')
@section('title', 'Blog Yazıları | NEWTIC')
@section('content')
<div class="admin-page-heading blog-admin-page-head">
  <div><p class="admin-eyebrow">İÇERİK STÜDYOSU</p><h1>Blog yönetimi</h1><p>Yazıları, görselleri ve yayın durumlarını tek yerden yönetin.</p></div>
  <div class="blog-admin-head-actions">
    <a class="admin-secondary" href="{{ route('admin.blog.categories.index') }}">Kategoriler</a>
    <a class="admin-primary" href="{{ route('admin.blog.posts.create') }}">+ Yeni yazı</a>
  </div>
</div>
<div class="blog-admin-stats">
  <div><small>TOPLAM YAZI</small><strong>{{ $posts->total() }}</strong></div>
  <div><small>YAYINDA</small><strong>{{ $publishedCount }}</strong></div>
  <div><small>TASLAK</small><strong>{{ $draftCount }}</strong></div>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Yazı arşivi</h2><a href="{{ route('blog.index') }}" target="_blank" rel="noopener noreferrer" class="admin-secondary">Blogu görüntüle ↗</a></div>
  <div class="blog-admin-post-list">
    @forelse($posts as $post)
      <article class="blog-admin-post-row">
        <div class="blog-admin-post-thumb">
          @if($post->featured_image_path)
            <img src="{{ asset($post->featured_image_path) }}" alt="" loading="lazy">
          @else
            <span aria-hidden="true">✦</span>
          @endif
        </div>
        <div class="blog-admin-post-info">
          <strong>{{ $post->title }}</strong>
          <small>{{ $post->category?->name ?: 'Kategorisiz' }} · {{ $post->updated_at?->format('d.m.Y') }}</small>
        </div>
        <span class="blog-admin-status {{ $post->is_published && $post->published_at?->isPast() ? 'is-live' : '' }}">
          {{ $post->is_published && $post->published_at?->isPast() ? 'Yayında' : 'Taslak' }}
        </span>
        <div class="blog-admin-post-actions">
          <a class="admin-secondary" href="{{ route('admin.blog.posts.edit', $post) }}">Düzenle</a>
          <form method="POST" action="{{ route('admin.blog.posts.destroy', $post) }}" onsubmit="return confirm('Bu yazı, içerik blokları ve görselleri kalıcı olarak silinsin mi?')">
            @csrf @method('DELETE')
            <button class="admin-danger" type="submit" aria-label="{{ $post->title }} yazısını sil"><x-admin-icon name="trash-2"/> Sil</button>
          </form>
        </div>
      </article>
    @empty
      <div class="blog-admin-empty"><span aria-hidden="true">✳</span><h3>Henüz yazı oluşturulmadı.</h3><p>İlk yazınızı hazırlayın. Başlık, görseller ve bloklar ekledikten sonra yayına alabilirsiniz.</p><a class="admin-primary" href="{{ route('admin.blog.posts.create') }}">İlk yazıyı oluştur</a></div>
    @endforelse
  </div>
  <div class="blog-admin-pagination">{{ $posts->links() }}</div>
</div>
@endsection
