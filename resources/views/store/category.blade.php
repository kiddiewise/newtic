@extends('layouts.store')
@section('content')
<main class="container inner-page category-landing" id="content">
  @if($banners->isNotEmpty())
    <section class="category-banner-carousel" aria-label="{{ $category->name }} kampanyaları" data-category-carousel>
      <div class="category-banner-track" data-category-track>
        @foreach($banners as $banner)
          <div class="category-banner-slide">
            @if($banner->link_url)<a href="{{ $banner->link_url }}" aria-label="{{ $banner->alt_text ?: $category->name.' kampanyası' }}">@endif
              <picture>
                @if($banner->mobile_image_path)
                  <source media="(max-width: 760px)" srcset="{{ asset($banner->mobile_image_path) }}">
                @endif
                <img src="{{ asset($banner->desktop_image_path) }}" alt="{{ $banner->alt_text ?: $category->name.' kampanyası' }}" width="1200" height="600" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
              </picture>
            @if($banner->link_url)</a>@endif
          </div>
        @endforeach
      </div>
      @if($banners->count() > 1)
        <span class="category-banner-counter"><span data-category-index>1</span>/{{ $banners->count() }}</span>
      @endif
    </section>
  @endif

  <header class="category-page-header">
    <h1>{{ $category->name }}</h1>
    @if(filled($category->description))
      <p class="category-description">{{ $category->description }}</p>
    @endif
  </header>

  @if($subcategories->isNotEmpty())
    @php $subcategoryCount = $subcategories->count(); @endphp
    <nav class="category-children-section category-children-section--count-{{ min($subcategoryCount, 4) }}"
      aria-label="{{ $category->name }} alt kategorileri"
      @if($subcategoryCount > 3) data-subcategory-carousel data-autoplay-interval="3000" @endif>
      <div class="category-children-grid{{ $subcategoryCount > 3 ? ' category-children-grid--scrolling' : '' }}"
        @if($subcategoryCount > 3) data-subcategory-track tabindex="0" aria-label="Alt kategoriler, yatay kaydırılabilir" @endif>
        @foreach($subcategories as $subcategory)
          <a href="{{ route('category.show', $subcategory->slug) }}" class="category-child-card"
            @if($subcategoryCount > 3) data-subcategory-slide @endif>
            <span class="category-child-art">
              <img src="{{ asset($subcategory->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" width="320" height="320" loading="lazy">
            </span>
            <strong>{{ $subcategory->name }}</strong>
          </a>
        @endforeach
      </div>
      @if($subcategoryCount > 3)
        <div class="category-children-toolbar" data-subcategory-toolbar>
          <span class="category-children-hint">Diğer kategorileri keşfet</span>
          <div class="category-children-controls">
            <span class="category-children-position" aria-hidden="true"><span data-subcategory-current>1</span> / {{ $subcategoryCount }}</span>
            <button class="category-children-arrow" type="button" data-subcategory-prev aria-label="Önceki alt kategori" disabled>‹</button>
            <button class="category-children-arrow" type="button" data-subcategory-next aria-label="Sonraki alt kategori">›</button>
          </div>
        </div>
      @endif
    </nav>
  @endif

  <section class="category-products-section" aria-labelledby="category-products-heading">
    <div class="section-heading category-section-heading">
      <h2 id="category-products-heading">Ürünler</h2>
      <span class="category-product-count">{{ $products->total() }} ürün</span>
    </div>
    @include('store.partials.infinite-products', ['products' => $products, 'emptyMessage' => 'Bu kategoride henüz ürün bulunmuyor.'])
  </section>
</main>
@endsection
