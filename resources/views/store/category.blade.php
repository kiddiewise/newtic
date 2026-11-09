@extends('layouts.store')
@section('title', $category->name.' | Polaris Kurşun')
@section('description', $category->description ?: $category->name.' ürün çeşitleri, gramaj seçenekleri ve güncel fiyatları.')
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
    <nav class="category-children-section" aria-label="{{ $category->name }} alt kategorileri">
      <div class="category-children-grid">
        @foreach($subcategories as $subcategory)
          <a href="{{ route('category.show', $subcategory->slug) }}" class="category-child-card">
            <span class="category-child-art">
              <img src="{{ asset($subcategory->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" width="320" height="320" loading="lazy">
            </span>
            <strong>{{ $subcategory->name }}</strong>
            <span class="category-child-arrow" aria-hidden="true">›</span>
          </a>
        @endforeach
      </div>
    </nav>
  @endif

  <section class="category-products-section" aria-labelledby="category-products-heading">
    <div class="section-heading category-section-heading">
      <h2 id="category-products-heading">Ürünler</h2>
      <span class="category-product-count">{{ $products->total() }} ürün</span>
    </div>
    <div class="product-grid">
      @forelse($products as $product)
        @include('store.partials.product-card', ['product' => $product])
      @empty
        <p class="nothing-found">Bu kategoride henüz ürün bulunmuyor.</p>
      @endforelse
    </div>
    <div class="pagination-wrap">{{ $products->links() }}</div>
  </section>
</main>
@endsection
