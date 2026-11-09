@extends('layouts.store')

@section('content')
<main id="content">
  <h1 class="sr-only">Polaris Kurşun — Balıkçılık ekipmanları</h1>
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
                  <img src="{{ $slide->desktop_image_url }}" alt="Polaris Kurşun koleksiyon görseli" width="1440" height="560" {{ $loop->first ? 'fetchpriority=high' : 'loading=lazy' }} decoding="async">
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
      <section class="container shop-section category-section" aria-labelledby="section-{{ $section->id }}">
        <div class="section-heading">
          <div><p class="eyebrow">KEŞFET</p><h2 id="section-{{ $section->id }}">{{ $section->title ?: 'Kategoriler' }}</h2></div>
          <a class="section-all" href="{{ route('search') }}">Tüm ürünler <span aria-hidden="true">↗</span></a>
        </div>
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
      <section class="container shop-section" aria-labelledby="section-{{ $section->id }}">
        <div class="section-heading">
          <div><p class="eyebrow">POLARIS SEÇKİSİ</p><h2 id="section-{{ $section->id }}">{{ $section->title ?: 'Popüler Ürünler' }}</h2></div>
          <a class="section-all" href="{{ route('search') }}">Tümünü gör <span aria-hidden="true">↗</span></a>
        </div>
        <div class="product-grid">
          @foreach($section->products as $product)
            @include('store.partials.product-card', ['product' => $product])
          @endforeach
        </div>
      </section>
    @elseif($section->type === \App\Models\HomeSection::TYPE_BANNER && $section->slides->isNotEmpty())
      <section class="container banner-strip" aria-label="Koleksiyon bannerları"><div class="promo-track">
        @foreach($section->slides as $slide)
          @if($slide->link_url)<a href="{{ $slide->link_url }}" aria-label="Koleksiyonu incele">@endif
            <picture><source media="(max-width: 640px)" srcset="{{ $slide->mobile_image_url }}"><img src="{{ $slide->desktop_image_url }}" alt="Polaris Kurşun koleksiyon görseli" width="1440" height="560" loading="lazy"></picture>
          @if($slide->link_url)</a>@endif
        @endforeach
        </div>
      </section>
    @endif
  @empty
    <section class="container empty-home"><span class="empty-symbol" aria-hidden="true">✦</span><p class="eyebrow">POLARIS KURŞUN</p><h1>Yeni bir deneyim hazırlanıyor.</h1><p>Ürünlerimiz ve koleksiyonlarımız yakında burada.</p></section>
  @endforelse

  <section class="container reassurance-strip" aria-label="Polaris ayrıcalıkları">
    <div><span class="benefit-icon" aria-hidden="true">◇</span><div><strong>Özenli ürün seçkisi</strong><small>Balıkçılık için tasarlandı</small></div></div>
    <div><span class="benefit-icon" aria-hidden="true">◎</span><div><strong>Kolay ürün keşfi</strong><small>Mobil odaklı alışveriş</small></div></div>
    <div><span class="benefit-icon" aria-hidden="true">↗</span><div><strong>Hızlı gezinme</strong><small>Yalın ve akıcı tasarım</small></div></div>
  </section>
</main>
@endsection
