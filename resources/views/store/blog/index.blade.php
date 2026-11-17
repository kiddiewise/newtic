@extends('layouts.store')
@section('content')
<main id="content" class="container blog-public">
  <div class="blog-public-topline"><a href="{{ route('home') }}">Ana sayfa</a><span aria-hidden="true">/</span><span>Blog</span></div>
  <header class="blog-public-header">
    <div><span class="blog-public-kicker"><span aria-hidden="true">✳</span> REHBER & İLHAM</span>
      <h1>{{ $selectedCategory?->name ?: 'Keşfet. Öğren. İlham al.' }}</h1>
      <p>{{ $selectedCategory?->description ?: 'İpuçları, ürün rehberleri ve yeni fikirler. Merak ettiğiniz konuları keşfedin.' }}</p>
    </div>
    <span class="blog-public-header-symbol" aria-hidden="true">✳</span>
  </header>
  <nav class="blog-category-tabs" aria-label="Blog kategorileri">
    <a href="{{ route('blog.index') }}" @class(['is-current' => !$selectedCategory])>Tüm yazılar</a>
    @foreach($categories as $category)
      <a href="{{ route('blog.category', $category->slug) }}" @class(['is-current' => $selectedCategory?->id === $category->id])>{{ $category->name }} <small>{{ $category->posts_count }}</small></a>
    @endforeach
  </nav>
  @if($posts->isNotEmpty())
    @if($posts->currentPage() === 1)
      @php $featured = $posts->first(); @endphp
      <section class="blog-featured-story" aria-labelledby="blog-feature-title">
        <a href="{{ route('blog.show', $featured->slug) }}" class="blog-featured-media">
          @if($featured->featured_image_path)
            <img src="{{ asset($featured->featured_image_path) }}" alt="{{ $featured->featured_image_alt ?: $featured->title }}" width="1200" height="675" fetchpriority="high">
          @else
            <span aria-hidden="true">✳</span>
          @endif
        </a>
        <div class="blog-featured-copy">
          <span class="blog-feature-badge">ÖNE ÇIKAN YAZI</span>
          <span class="blog-card-category">{{ $featured->category?->name ?: 'Blog' }}</span>
          <h2 id="blog-feature-title"><a href="{{ route('blog.show', $featured->slug) }}">{{ $featured->title }}</a></h2>
          <p>{{ \Illuminate\Support\Str::limit($featured->excerpt ?: 'Bu yazıyı keşfetmek için devam edin.', 210) }}</p>
          <a class="blog-feature-action" href="{{ route('blog.show', $featured->slug) }}">Yazıyı oku <span aria-hidden="true">↗</span></a>
        </div>
      </section>
    @endif
    @php $gridPosts = $posts->currentPage() === 1 ? $posts->skip(1) : $posts->getCollection(); @endphp
    @if($gridPosts->isNotEmpty())
      <section class="blog-latest-section" aria-labelledby="blog-latest-title">
        <div class="blog-section-heading"><div><span class="blog-public-kicker">BLOG ARŞİVİ</span><h2 id="blog-latest-title">Son yazılar</h2></div><span>{{ $posts->total() }} yazı</span></div>
        <div class="blog-cards-grid">
          @foreach($gridPosts as $post)<x-blog-card :post="$post" />@endforeach
        </div>
      </section>
    @endif
    <div class="blog-pagination">{{ $posts->links() }}</div>
  @else
    <div class="blog-public-empty"><span aria-hidden="true">✳</span><h2>Henüz yayınlanmış yazı yok.</h2><p>Yeni rehberler ve haberler burada yerini alacak.</p></div>
  @endif
</main>
@endsection
