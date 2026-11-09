@extends('layouts.store')
@section('title', $product->name.' | Polaris Kurşun')
@section('content')
<main class="container inner-page" id="content">
  <nav class="breadcrumbs" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span><a href="{{ route('category.show', $product->category->slug) }}">{{ $product->category->name }}</a><span>/</span><span>{{ $product->name }}</span></nav>
  <div class="product-detail">
    <div class="detail-gallery">
      @php $gallery = $product->images; @endphp
      <div class="detail-art">
        @if($product->discount_percent > 0)<span class="detail-discount-pill">-%{{ $product->discount_percent }} İNDİRİM</span>@endif
        <img data-product-main-image src="{{ $product->image_path ? asset($product->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="{{ $product->name }}" width="600" height="600">
      </div>
      @if($gallery->isNotEmpty())
        <div class="detail-thumbnails" aria-label="Ürün fotoğrafları">
          @foreach($gallery as $image)
            <button type="button" data-gallery-thumb data-image-url="{{ asset($image->path) }}" aria-label="{{ $loop->iteration }}. ürün fotoğrafını göster" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
              <img src="{{ asset($image->path) }}" alt="" width="78" height="78" loading="lazy">
            </button>
          @endforeach
        </div>
      @endif
    </div>
    <div class="detail-intro">
      <p class="eyebrow">POLARIS KURŞUN</p><h1>{{ $product->name }}</h1>
      <p>{{ $product->description ?: 'Polaris Kurşun balıkçılık ekipmanları koleksiyonundan.' }}</p>
      <span class="sku">Stok kodu: {{ $product->sku }}</span>
      <div class="product-view-counter" aria-label="Toplam ürün görüntülenmesi">◉ {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</div>
      @if($product->discount_percent > 0)
        <div class="detail-original-price"><s>{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s><span>-%{{ $product->discount_percent }} indirim</span></div>
      @endif
      <div class="detail-price">{{ number_format((float) $product->price, 2, ',', '.') }} ₺ @if($product->is_demo)<span>DEMO FİYAT</span>@endif</div>
      <p class="detail-helper">Bu sayfa henüz geliştirme aşamasındadır. Sepete ekleme aktif değildir.</p>
    </div>
  </div>
  <section class="category-order" aria-labelledby="category-order-title">
    <div class="section-heading"><div><p class="eyebrow">TOPLU ÜRÜN SEÇİMİ</p><h2 id="category-order-title">{{ $product->category->name }} kategorisindeki tüm ürünler</h2></div></div>
    <p class="order-note">Farklı gramajlardan istediğiniz miktarı ayrı ayrı belirleyin. Sepete ekleme işlevi sonraki aşamada etkinleştirilecek.</p>
    <div class="order-list" data-order-list>
      @foreach($categoryProducts as $categoryProduct)
        <div class="order-item {{ $categoryProduct->is($product) ? 'is-current' : '' }}">
          <span class="order-item-image"><img src="{{ $categoryProduct->image_path ? asset($categoryProduct->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="" width="80" height="80" loading="lazy"></span>
          <span class="order-item-info"><a href="{{ route('product.show', $categoryProduct->slug) }}">{{ $categoryProduct->name }}</a><strong>{{ number_format((float) $categoryProduct->price, 2, ',', '.') }} ₺</strong>@if($categoryProduct->discount_percent > 0)<small class="order-discount">-%{{ $categoryProduct->discount_percent }} indirim</small>@endif @if($categoryProduct->is($product))<span class="current-product-label">Görüntülenen ürün</span>@endif</span>
          <label class="quantity-field"><span class="sr-only">{{ $categoryProduct->name }} adedi</span><input type="number" data-order-quantity data-price="{{ $categoryProduct->price }}" min="0" max="{{ $categoryProduct->stock_quantity }}" value="{{ $categoryProduct->is($product) ? '1' : '0' }}" inputmode="numeric" aria-label="{{ $categoryProduct->name }} miktarı"></label>
        </div>
      @endforeach
    </div>
    <div class="order-summary"><div><small>Seçiminiz (önizleme)</small><strong data-order-summary>1 ürün seçildi</strong></div><button type="button" disabled>Sepete ekleme yakında</button></div>
  </section>
</main>
@endsection
