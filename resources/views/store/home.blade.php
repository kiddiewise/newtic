@extends('layouts.store')

@section('content')
@php $storeName = \App\Support\StoreIdentity::name(); @endphp
<main id="content">
  <h1 class="sr-only">{{ $storeName }} — Online Mağaza</h1>
  @forelse($sections as $section)
    @if($section->type === \App\Models\HomeSection::TYPE_SLIDER && $section->slides->isNotEmpty())
      <section class="container homepage-hero" aria-label="Ana görsel kaydırıcısı">
        <div class="visual-slider" data-slider data-interval="{{ (int) data_get($section->settings, 'interval_ms', 5500) }}">
          <div class="slide-track">
            @foreach($section->slides as $slide)
              <div class="slide-item {{ $loop->first ? 'is-active' : '' }}" data-slide aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                @if($slide->link_url)
                  <a href="{{ $slide->link_url }}" aria-label="Kampanya görselini incele" tabindex="{{ $loop->first ? '0' : '-1' }}">
                @endif
                <picture>
                  <source media="(max-width: 640px)" srcset="{{ $slide->mobile_image_url }}">
                  <img src="{{ $slide->desktop_image_url }}" alt="{{ $storeName }} koleksiyon görseli" width="1440" height="560" {{ $loop->first ? 'fetchpriority=high' : 'loading=lazy' }} decoding="async">
                </picture>
                @if($slide->link_url)</a>@endif
              </div>
            @endforeach
          </div>
          @if($section->slides->count() > 1)
            <button type="button" class="slider-arrow slider-prev" data-slide-prev aria-label="Önceki görsel">‹</button>
            <button type="button" class="slider-arrow slider-next" data-slide-next aria-label="Sonraki görsel">›</button>
            <div class="slider-dots sr-only" aria-label="Görsel seçimi">
              @foreach($section->slides as $slide)
                <button type="button" data-slide-dot="{{ $loop->index }}" aria-label="{{ $loop->iteration }}. görsele geç" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
              @endforeach
            </div>
            <div class="slider-count" aria-label="Görsel {{ $section->slides->count() }} görselden birincisi"><span data-slide-current>1</span>/<span>{{ $section->slides->count() }}</span></div>
          @endif
        </div>
      </section>
    @elseif($section->type === \App\Models\HomeSection::TYPE_CATEGORIES && $section->categories->isNotEmpty())
      <section class="container shop-section category-section" aria-label="Ürün kategorileri">
        
        <div class="category-grid">
          @foreach($section->categories as $category)
            <a class="category-card" href="{{ route('category.show', $category->slug) }}">
              <span class="category-art"><img src="{{ asset($category->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" width="180" height="180" loading="lazy"></span>
              <span class="category-name">{{ $category->name }}</span>
              <span class="category-arrow" aria-hidden="true">↗</span>
            </a>
          @endforeach
        </div>
      </section>
    @elseif($section->type === \App\Models\HomeSection::TYPE_PRODUCTS && $section->products->isNotEmpty())
      <section class="container shop-section showcase-products" aria-labelledby="section-{{ $section->id }}">
        <div class="section-heading">
          <div class="showcase-heading-copy"><p class="eyebrow">ÖNE ÇIKANLAR</p><h2 id="section-{{ $section->id }}">{{ $section->title ?: 'Popüler Ürünler' }}</h2>
            @if(data_get($section->settings, 'countdown_ends_at'))
              @php
                $countdownEnd = \Illuminate\Support\Carbon::createFromFormat('Y-m-d\TH:i', data_get($section->settings, 'countdown_ends_at'), 'Europe/Istanbul');
                $countdownTimestamp = $countdownEnd?->timestamp;
              @endphp
              @if($countdownTimestamp && $countdownTimestamp > now()->timestamp)
                <div class="showcase-countdown" data-showcase-countdown data-end="{{ $countdownTimestamp * 1000 }}"><span>{{ data_get($section->settings, 'countdown_label') ?: 'Kampanyanın bitmesine:' }}</span><time aria-label="Kalan süre" data-countdown-text></time></div>
              @endif
            @endif
          </div>
          <a class="section-all" href="{{ route('search') }}">Tümünü gör <span aria-hidden="true">↗</span></a>
        </div>
        <div class="showcase-carousel-wrap" data-showcase-carousel>
          <button type="button" class="showcase-arrow showcase-arrow-prev" data-carousel-prev aria-label="Önceki ürünler">‹</button>
          <div class="product-grid showcase-product-track" data-carousel-track tabindex="0" role="region" aria-roledescription="carousel" aria-label="{{ $section->title ?: 'Ürünler' }}">
          @foreach($section->products as $product)
            @include('store.partials.product-card', ['product' => $product])
          @endforeach
          </div>
          <button type="button" class="showcase-arrow showcase-arrow-next" data-carousel-next aria-label="Sonraki ürünler">›</button>
        </div>
      </section>
    @elseif($section->type === \App\Models\HomeSection::TYPE_BANNER && $section->slides->isNotEmpty())
      <section class="container banner-strip" aria-label="Koleksiyon bannerları"><div class="promo-track">
        @foreach($section->slides as $slide)
          @if($slide->link_url)<a href="{{ $slide->link_url }}" aria-label="Koleksiyonu incele">@endif
            <picture><source media="(max-width: 640px)" srcset="{{ $slide->mobile_image_url }}"><img src="{{ $slide->desktop_image_url }}" alt="{{ $storeName }} koleksiyon görseli" width="1440" height="560" loading="lazy"></picture>
          @if($slide->link_url)</a>@endif
        @endforeach
        </div>
      </section>
    @endif
  @empty
    <section class="container empty-home"><span class="empty-symbol" aria-hidden="true">✦</span><p class="eyebrow">{{ mb_strtoupper($storeName, 'UTF-8') }}</p><h1>Yeni bir deneyim hazırlanıyor.</h1><p>Ürünlerimiz ve koleksiyonlarımız yakında burada.</p></section>
  @endforelse

  @if($latestPosts->isNotEmpty())
    <section class="container blog-home-section newtic-home-blog" aria-labelledby="home-blog-heading" data-home-blog>
      <div class="blog-section-heading">
        <h2 id="home-blog-heading">Blogdan son yazılar</h2>
        <div class="newtic-home-blog-actions">
          <a href="{{ route('blog.index') }}">Tüm yazılar <span aria-hidden="true">↗</span></a>
          @if($latestPosts->count()>1)
            <div class="newtic-home-blog-controls" data-home-blog-controls hidden>
              <button type="button" data-home-blog-prev aria-label="Önceki yazılar" aria-controls="home-blog-track"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
              <button type="button" data-home-blog-next aria-label="Sonraki yazılar" aria-controls="home-blog-track"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            </div>
          @endif
        </div>
      </div>
      <div id="home-blog-track" class="newtic-home-blog-track" data-home-blog-track tabindex="0" role="region" aria-label="Son blog yazıları">
        @foreach($latestPosts->take(4) as $post)<x-blog-card :post="$post" />@endforeach
      </div>
    </section>
  @endif

</main>
@endsection
