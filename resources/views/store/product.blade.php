@extends('layouts.store')
@section('title', $product->name.' | Polaris Kurşun')
@section('content')

{{-- Mobile-only product toolbar replaces the full catalogue navigation on this page. --}}
<header class="pdp-mobile-header" aria-label="Ürün detay menüsü">
  <a class="pdp-head-icon" href="{{ route('category.show', $product->category->slug) }}" data-product-back aria-label="Geri dön">
    <x-store-icon name="chevron-left" />
  </a>
  <span class="pdp-mobile-heading">{{ $product->name }}</span>
  <div class="pdp-head-actions">
    <a class="pdp-head-icon" href="{{ route('search') }}" aria-label="Ürün ara">
      <x-store-icon name="search" />
    </a>
    <button class="pdp-head-icon pdp-future-icon" type="button" disabled title="Favoriler yakında" aria-label="Favoriler yakında">
      <x-store-icon name="heart" />
    </button>
    <a class="pdp-head-icon pdp-cart-link header-cart-link" href="{{ route('cart.index') }}" title="Sepetim" aria-label="Sepetim">
      <x-store-icon name="shopping-cart" />
      @if(\App\Support\ShoppingCart::itemCount() > 0)<span class="cart-icon-badge">{{ \App\Support\ShoppingCart::itemCount() }}</span>@endif
    </a>
  </div>
</header>

<main class="container inner-page pdp-main" id="content">
  <nav class="breadcrumbs pdp-breadcrumbs" aria-label="İçerik yolu">
    <a href="{{ route('home') }}">Ana sayfa</a><span>/</span>
    <a href="{{ route('category.show', $product->category->slug) }}">{{ $product->category->name }}</a>
    <span>/</span><span>{{ $product->name }}</span>
  </nav>

  <div class="product-detail pdp-product-detail">
    <div class="detail-gallery pdp-gallery" data-product-gallery>
      @php
        $gallery = $product->images;
        $cover = $product->image_path ? asset($product->image_path) : asset('assets/products/demo-sinker.svg');
        $galleryCount = max(1, $gallery->count());
      @endphp
      <div class="detail-art pdp-art" data-gallery-swipe aria-label="Ürün görseli">
        @if($product->discount_percent > 0)
          <span class="detail-discount-pill">-%{{ $product->discount_percent }}</span>
        @endif
        <img data-product-main-image src="{{ $cover }}" alt="{{ $product->name }}" width="600" height="600" fetchpriority="high">
        <span class="pdp-image-counter" aria-label="Ürün fotoğrafı sırası">
          <span data-gallery-current>1</span> / {{ $galleryCount }}
        </span>
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

    <div class="detail-intro pdp-intro">
      <p class="eyebrow pdp-brand">POLARIS KURŞUN</p>
      <h1>{{ $product->name }}</h1>
      @if($product->discount_percent > 0)
        <div class="detail-original-price pdp-original-price">
          <s>{{ number_format((float) $product->compare_at_price, 2, ',', '.') }} ₺</s>
          <span>-%{{ $product->discount_percent }} indirim</span>
        </div>
      @endif
      <div class="detail-price pdp-price" data-detail-price-anchor>
        {{ number_format((float) $product->price, 2, ',', '.') }} ₺
        @if($product->is_demo)<span>DEMO FİYAT</span>@endif
      </div>
      <a class="pdp-rating-link" href="#urun-yorumlari" aria-label="Ürün yorumlarına ve değerlendirmelere git">
        <span class="pdp-rating-stars" aria-hidden="true">★★★★★</span>
        <strong>{{ $reviewCount ? number_format($reviewAverage, 1, ',', '.') : 'Yeni' }}</strong>
        <span>{{ $reviewCount ? $reviewCount.' doğrulanmış yorum' : 'İlk yorumunuzu bırakın' }}</span>
        <x-store-icon name="chevron-right" />
      </a>
      <div class="pdp-availability">
        @if($product->stock_quantity > 0)
          <span class="pdp-in-stock">● Stokta</span>
        @else
          <span class="pdp-out-of-stock">Stokta yok</span>
        @endif
        <span class="product-view-counter" aria-label="Ürün görüntülenme sayısı">◉ {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</span>
      </div>
      <div class="pdp-current-product-note">
        <strong>Seçili ürün:</strong> {{ $product->name }}
        @if($product->options->isNotEmpty())<small>· {{ $product->option_label }} seçimi gerekli</small>@endif
        <button type="button" data-sheet-open-inline>Tüm kategori ürünlerini seç <span aria-hidden="true">›</span></button>
      </div>
      {{-- Desktop: the primary purchase action is immediately visible beside the product. --}}
      <div class="pdp-desktop-purchase" aria-label="Ürün satın alma seçenekleri">
        <div class="pdp-desktop-purchase-head">
          <strong>Adet seçin</strong>
          <span>Stok: {{ $product->stock_quantity }} adet</span>
        </div>
        @if($product->options->isNotEmpty())
          <div class="pdp-desktop-variant">
            <label for="pdp-desktop-option">{{ $product->option_label }} <span class="pdp-required-star" aria-hidden="true">*</span></label>
            <select id="pdp-desktop-option" data-option-mirror data-product-id="{{ $product->id }}" data-option-location="desktop"
              aria-describedby="pdp-desktop-option-help">
              <option value="">Seçiniz</option>
              @foreach($product->options as $option)
                <option value="{{ $option->id }}">{{ $option->value }}</option>
              @endforeach
            </select>
            <small id="pdp-desktop-option-help" class="pdp-option-help" data-option-help="desktop" role="status" aria-live="polite">Sepete eklemek için önce {{ mb_strtolower($product->option_label) }} seçin.</small>
          </div>
        @endif
        <div class="pdp-desktop-purchase-row">
          <div class="pdp-desktop-stepper" aria-label="{{ $product->name }} adedi">
            <button type="button" data-desktop-minus aria-label="{{ $product->name }} azalt">−</button>
            <output data-desktop-qty aria-label="Seçili adet">{{ $product->stock_quantity > 0 && !$pickerReset ? 1 : 0 }}</output>
            <button type="button" data-desktop-plus aria-label="{{ $product->name }} artır" @disabled($product->stock_quantity <= 0)>+</button>
          </div>
          <button type="submit" form="pdp-cart-form" class="pdp-desktop-add" data-cart-add @disabled($product->stock_quantity <= 0)>
            <x-store-icon name="shopping-cart-plus" />
            Sepete Ekle
          </button>
        </div>
        <p class="pdp-desktop-purchase-summary" data-desktop-summary aria-live="polite">Seçimler hesaplanıyor</p>
        <a href="#pdp-picker" class="pdp-desktop-more">Diğer gramaj ve ürünleri birlikte seç <span aria-hidden="true">↓</span></a>
      </div>
    </div>
  </div>

  {{-- The category products remain independent SKUs; both buttons submit the same secure form. --}}
  <form id="pdp-cart-form" data-quick-add data-quick-product="{{ $product->id }}" method="POST" action="{{ route('cart.add') }}" autocomplete="off">@csrf</form>
  @if($errors->any())
    <div class="cart-flash is-error" role="alert">{{ $errors->first() }}</div>
  @endif
  {{-- The same category order list is a regular section on desktop and a bottom sheet on mobile. --}}
  <section id="pdp-picker" class="category-order pdp-picker" data-category-id="{{ $product->primary_category_id }}" data-product-sheet aria-labelledby="category-order-title">
    <div class="pdp-sheet-handle" aria-hidden="true"></div>
    <div class="pdp-sheet-heading">
      <div>
        <p class="eyebrow">ÇOKLU ÜRÜN SEÇİMİ</p>
        <h2 id="category-order-title">{{ $product->category->name }} ürünleri</h2>
        <p>Aynı kategorideki bütün ürünlerin adetlerini ayrı ayrı belirleyin. Seçenekli ürünlerde iğne kalınlığını da seçin.</p>
      </div>
      <button type="button" class="pdp-sheet-close" data-sheet-close aria-label="Seçim panelini kapat">✕</button>
    </div>

    <div class="order-list pdp-order-list" data-order-list>
      @foreach($categoryProducts as $categoryProduct)
        @php
          $current = $categoryProduct->is($product);
          $initialQty = $current && $categoryProduct->stock_quantity > 0 && !$pickerReset ? 1 : 0;
        @endphp
        <div class="order-item pdp-order-item {{ $current ? 'is-current' : '' }}">
          <span class="order-item-image">
            <img src="{{ $categoryProduct->image_path ? asset($categoryProduct->image_path) : asset('assets/products/demo-sinker.svg') }}" alt="" width="80" height="80" loading="lazy">
          </span>
          <span class="order-item-info">
            <a href="{{ route('product.show', $categoryProduct->slug) }}">{{ $categoryProduct->name }}</a>
            <strong>{{ number_format((float) $categoryProduct->price, 2, ',', '.') }} ₺</strong>
            @if($categoryProduct->discount_percent > 0)<small class="order-discount">-%{{ $categoryProduct->discount_percent }} indirim</small>@endif
            @if($current)<span class="current-product-label">Görüntülenen ürün</span>@endif
            @if($categoryProduct->stock_quantity === 0)<span class="pdp-stock-note">Tükendi</span>@endif
          </span>
          @if($categoryProduct->options->isNotEmpty())
            <label class="pdp-variant-field">
              <span>{{ $categoryProduct->option_label }} <small>· seçiniz</small></span>
              <select form="pdp-cart-form" name="options[{{ $categoryProduct->id }}]" data-product-option data-product-id="{{ $categoryProduct->id }}"
                aria-label="{{ $categoryProduct->name }} için {{ $categoryProduct->option_label }} seçimi">
                <option value="">Seçiniz</option>
                @foreach($categoryProduct->options as $option)
                  <option value="{{ $option->id }}">{{ $option->value }}</option>
                @endforeach
              </select>
            </label>
          @endif
          <div class="pdp-stepper" aria-label="{{ $categoryProduct->name }} adedi">
            <button type="button" data-step-minus aria-label="{{ $categoryProduct->name }} azalt">−</button>
            <label class="quantity-field">
              <span class="sr-only">{{ $categoryProduct->name }} adedi</span>
              <input type="number" form="pdp-cart-form" name="items[{{ $categoryProduct->id }}]" data-order-quantity data-product-id="{{ $categoryProduct->id }}" data-price="{{ $categoryProduct->price }}" min="0" max="{{ $categoryProduct->stock_quantity }}" value="{{ $initialQty }}" autocomplete="off" inputmode="numeric" aria-label="{{ $categoryProduct->name }} miktarı">
            </label>
            <button type="button" data-step-plus aria-label="{{ $categoryProduct->name }} arttır" @disabled($categoryProduct->stock_quantity === 0)>+</button>
          </div>
        </div>
      @endforeach
    </div>

    <div class="order-summary pdp-sheet-footer">
      <div>
        <small>Seçilen ürünler</small>
        <strong data-order-summary>Seçim hesaplanıyor</strong>
        <small class="pdp-variant-hint" data-variant-hint role="status" aria-live="polite"></small>
      </div>
      <button type="submit" form="pdp-cart-form" data-cart-add>Seçilenleri Sepete Ekle</button>
    </div>
  </section>

  <section class="pdp-description-section" aria-labelledby="pdp-description-title">
    <h2 id="pdp-description-title">Ürün açıklaması</h2>
    <div class="pdp-description-copy" id="pdp-description-content" data-description-content>
      <p>{{ $product->description ?: 'Polaris Kurşun balıkçılık ekipmanları koleksiyonundan.' }}</p>
    </div>
    <button type="button" class="pdp-description-toggle" data-description-toggle aria-expanded="false" aria-controls="pdp-description-content">
      Daha fazla göster <span aria-hidden="true">⌄</span>
    </button>
    <div class="pdp-specifications">
      <div><span>Marka</span><strong>Polaris Kurşun</strong></div>
      <div><span>Stok kodu</span><strong>{{ $product->sku }}</strong></div>
      <div><span>Kategori</span><a href="{{ route('category.show', $product->category->slug) }}">{{ $product->category->name }}</a></div>
    </div>
  </section>
  @include('store.partials.product-reviews')
  <p class="pdp-order-notice">Bu test mağazasında henüz sipariş ve ödeme alınmamaktadır. Sepet kullanılabilir; sipariş ve ödeme henüz aktif değildir.</p>
</main>

<div class="pdp-sheet-backdrop" data-sheet-backdrop aria-hidden="true"></div>
<div class="pdp-sticky-bar" data-sticky-bar aria-label="Hızlı ürün seçimi">
  <button class="pdp-sticky-choose pdp-sticky-gramages" type="button" data-sheet-open data-scroll-ribbon
    aria-label="Diğer gramaj seçeneklerini ve kategorideki ürünleri görüntüle" aria-hidden="true" inert>
    <span>Diğer gramaj seçenekleri</span>
    <x-store-icon name="chevron-up" />
  </button>
  <div class="pdp-sticky-meta pdp-sticky-meta-a11y">
    @if($product->options->isNotEmpty())
      <span class="pdp-sticky-option-help" id="pdp-sticky-option-help" data-option-help="sticky" role="status" aria-live="polite">Önce {{ mb_strtolower($product->option_label) }} seçin.</span>
      <span class="pdp-sticky-variant-total" data-sticky-total aria-label="Seçilen toplam tutar">Hesaplanıyor</span>
      <span class="sr-only" data-sticky-selection aria-live="polite">Seçimler hazırlanıyor</span>
    @else
      <span class="pdp-sticky-price" data-sticky-price>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</span>
      <span class="pdp-sticky-selection" data-sticky-selection aria-live="polite">Seçimler hazırlanıyor</span>
      <span class="sr-only" data-sticky-total>Hesaplanıyor</span>
    @endif
  </div>
  <div @class(['pdp-sticky-actions', 'has-variant' => $product->options->isNotEmpty()])>
    @if($product->options->isNotEmpty())
      <label class="pdp-sticky-variant" for="pdp-sticky-option">
        <span class="pdp-sticky-variant-title">{{ $product->option_label }} <em aria-hidden="true">*</em></span>
        <select id="pdp-sticky-option" data-option-mirror data-product-id="{{ $product->id }}" data-option-location="sticky"
          aria-describedby="pdp-sticky-option-help">
          <option value="">Seçiniz</option>
          @foreach($product->options as $option)
            <option value="{{ $option->id }}">{{ $option->value }}</option>
          @endforeach
        </select>
      </label>
    @endif
    <div class="pdp-sticky-quick">
      <div class="pdp-sticky-stepper" aria-label="Görüntülenen ürünün adedi">
        <button type="button" data-quick-minus aria-label="{{ $product->name }} azalt">−</button>
        <output data-quick-qty>{{ $product->stock_quantity > 0 && !$pickerReset ? 1 : 0 }}</output>
        <button type="button" data-quick-plus aria-label="{{ $product->name }} arttır" @disabled($product->stock_quantity === 0)>+</button>
      </div>
    </div>
    <button class="pdp-sticky-add" type="submit" form="pdp-cart-form" data-cart-add
      aria-label="Seçilen ürünleri sepete ekle" title="Sepete ekle">
      <x-store-icon name="shopping-cart" />
      <span>Sepete ekle</span>
    </button>
  </div>
</div>
@endsection
