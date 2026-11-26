@props(['post'])
<a class="blog-card" href="{{ route('blog.show', $post->slug) }}">
  <span class="blog-card-visual">
    @if($post->featured_image_path)
      <img src="{{ asset($post->featured_image_path) }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" width="640" height="360">
    @else
      <span class="blog-card-placeholder" aria-hidden="true">✳</span>
    @endif
  </span>
  <span class="blog-card-copy">
    <span class="blog-card-category">{{ $post->category?->name ?: 'Blog' }}</span>
    <strong>{{ $post->title }}</strong>
    @if(filled($post->excerpt))<span class="blog-card-excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, 116) }}</span>@endif
    <span class="blog-card-foot"><span>{{ $post->published_at?->translatedFormat('d F Y') }}</span><span aria-hidden="true">Oku ↗</span></span>
  </span>
</a>
