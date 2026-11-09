@extends('layouts.store')
@section('title', $category->name.' | Polaris Kurşun')
@section('description', $category->description ?: $category->name.' ürün çeşitleri, gramaj seçenekleri ve güncel fiyatları.')
@section('content')
<main class="container inner-page category-landing" id="content">
  <nav class="breadcrumbs category-breadcrumbs" aria-label="İçerik yolu">
    <a href="{{ route('home') }}">Ana sayfa</a>
    @foreach($breadcrumbs as $crumb)
      <span aria-hidden="true">/</span>
      @if(!$loop->last)
        <a href="{{ route('category.show', $crumb->slug) }}">{{ $crumb->name }}</a>
      @else
        <span aria-current="page">{{ $crumb->name }}</span>
      @endif
    @endforeach
  </nav>

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

  <div class="category-page-header">
    <p class="eyebrow">POLARIS KURŞUN / KATEGORİLER</p>
    <h1>{{ $category->name }}</h1>
    @if($category->description)
      <p>{{ $category->description }}</p>
    @else
      <p>Çeşitleri ve gramaj seçeneklerini keşfedin.</p>
    @endif
  </div>

  @if($subcategories->isNotEmpty())
    <section class="category-children-section" aria-labelledby="subcategory-title">
      <div class="section-heading category-section-heading">
        <div><p class="eyebrow">KATEGORİ SEÇİMİ</p><h2 id="subcategory-title">{{ $category->name }} çeşitleri</h2></div>
      </div>
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
    </section>
  @endif

  <section class="category-products-section" aria-labelledby="category-products-heading">
    <div class="section-heading category-section-heading">
      <div>
        <p class="eyebrow">ÜRÜNLERİ KEŞFET</p>
        <h2 id="category-products-heading">{{ $subcategories->isNotEmpty() ? 'Tüm alt kategori ürünleri' : $category->name.' ürünleri' }}</h2>
      </div>
      <span class="category-product-count">{{ $products->total() }} ürün</span>
    </div>
    <div class="product-grid">
      @forelse($products as $product)
        @include('store.partials.product-card', ['product' => $product])
      @empty
        <p class="nothing-found">Bu kategoride henüz ürün bulunmuyor. @if($subcategories->isNotEmpty())Yukarıdan bir alt kategori seçebilirsiniz.@endif</p>
      @endforelse
    </div>
    <div class="pagination-wrap">{{ $products->links() }}</div>
  </section>
</main>
@endsection
