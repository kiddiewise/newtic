@extends('layouts.store')
@section('content')
<main class="container blog-story-page" id="content">
  <nav class="blog-story-breadcrumbs" aria-label="İçerik yolu">
    <a href="{{ route('home') }}">Ana sayfa</a><span>/</span><a href="{{ route('blog.index') }}">Blog</a>
    @if($post->category)<span>/</span><a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a>@endif
  </nav>
  <article class="blog-story">
    <header class="blog-story-heading">
      <span class="blog-public-kicker">{{ mb_strtoupper($post->category?->name ?: 'BLOG', 'UTF-8') }}</span>
      <h1>{{ $post->title }}</h1>
      @if($post->excerpt)<p class="blog-story-lede">{{ $post->excerpt }}</p>@endif
      <div class="blog-story-byline"><span class="blog-story-author-mark" aria-hidden="true">✳</span><strong>{{ $post->author_name ?: \App\Support\StoreIdentity::name() }}</strong><span aria-hidden="true">·</span><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('d F Y') }}</time></div>
    </header>
    @if($post->featured_image_path)
      <figure class="blog-story-cover"><img src="{{ asset($post->featured_image_path) }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="1600" height="900" fetchpriority="high"></figure>
    @endif
    <div class="blog-story-body">
      @foreach($post->blocks as $block)
        @if($block->type === 'heading' && filled($block->content))
          <h2>{{ $block->content }}</h2>
        @elseif($block->type === 'text' && filled($block->content))
          <div class="blog-story-text">{!! nl2br(e($block->content)) !!}</div>
        @elseif($block->type === 'image' && $block->images->isNotEmpty())
          @foreach($block->images->take(1) as $image)
            <figure class="blog-story-figure"><img src="{{ asset($image->path) }}" alt="{{ $image->alt ?: $post->title }}" loading="lazy" width="1600" height="1000">@if($image->caption)<figcaption>{{ $image->caption }}</figcaption>@endif</figure>
          @endforeach
        @elseif($block->type === 'slider' && $block->images->isNotEmpty())
          <div class="blog-story-gallery" data-blog-gallery>
            <div class="blog-story-gallery-track" data-blog-gallery-track tabindex="0" role="region" aria-roledescription="carousel" aria-label="Yazı görsel galerisi">
              @foreach($block->images as $image)
                <figure class="blog-story-gallery-slide"><img src="{{ asset($image->path) }}" alt="{{ $image->alt ?: $post->title }}" loading="lazy" width="1600" height="900">@if($image->caption)<figcaption>{{ $image->caption }}</figcaption>@endif</figure>
              @endforeach
            </div>
            @if($block->images->count() > 1)
              <button type="button" class="blog-story-gallery-arrow is-prev" data-blog-gallery-prev aria-label="Önceki görsel">‹</button>
              <button type="button" class="blog-story-gallery-arrow is-next" data-blog-gallery-next aria-label="Sonraki görsel">›</button>
              <span class="blog-story-gallery-count"><span data-blog-gallery-current>1</span> / {{ $block->images->count() }}</span>
            @endif
          </div>
        @endif
      @endforeach
      @if(!empty($post->sources))
      <section class="blog-story-sources" aria-labelledby="blog-source-heading">
        <div><span aria-hidden="true">↗</span><h2 id="blog-source-heading">Kaynaklar</h2></div>
        <ul>@foreach($post->sources as $source)<li><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer nofollow">{{ $source['title'] }} <span aria-hidden="true">↗</span></a></li>@endforeach</ul>
      </section>
      @endif
    </div>
  </article>
  @if($related->isNotEmpty())
  <aside class="blog-related" aria-label="İlgili blog yazıları">
    <div class="blog-section-heading"><div><span class="blog-public-kicker">KEŞFETMEYE DEVAM ET</span><h2>İlginizi çekebilir</h2></div><a href="{{ route('blog.index') }}">Tüm yazılar ↗</a></div>
    <div class="blog-cards-grid">@foreach($related as $relatedPost)<x-blog-card :post="$relatedPost" />@endforeach</div>
  </aside>
  @endif
</main>
@endsection
