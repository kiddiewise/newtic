@extends('layouts.store')
@section('content')
<main id="content" class="container blog-public newtic-blog-index">
  <nav class="blog-public-topline" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana sayfa</a><span aria-hidden="true">/</span>@if($selectedCategory)<a href="{{ route('blog.index') }}">Blog</a><span aria-hidden="true">/</span><span>{{ $selectedCategory->name }}</span>@else<span>Blog</span>@endif</nav>
  <div class="newtic-blog-index-heading"><h1>{{ $selectedCategory?->name ?: 'Blog' }}</h1><span>{{ $posts->total() }} yazı</span></div>
  <nav class="blog-category-tabs" aria-label="Blog kategorileri">
    <a href="{{ route('blog.index') }}" @class(['is-current' => !$selectedCategory]) @if(!$selectedCategory) aria-current="page" @endif>Tüm yazılar</a>
    @foreach($categories as $category)
      <a href="{{ route('blog.category', $category->slug) }}" @class(['is-current' => $selectedCategory?->id === $category->id]) @if($selectedCategory?->id === $category->id) aria-current="page" @endif>{{ $category->name }} <small>{{ $category->posts_count }}</small></a>
    @endforeach
  </nav>
  @if($posts->isNotEmpty())
    <div class="blog-cards-grid newtic-blog-archive-grid" aria-label="Blog yazıları">
      @foreach($posts as $post)<x-blog-card :post="$post" />@endforeach
    </div>
    <div class="blog-pagination">{{ $posts->links() }}</div>
  @else
    <div class="blog-public-empty"><h2>Henüz yayınlanmış yazı yok.</h2></div>
  @endif
</main>
@endsection
