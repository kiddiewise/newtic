@php $shopAppearance = \App\Models\ShopAppearance::current(); @endphp
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="newtic-analytics-endpoint" content="{{ route('analytics.event') }}">
  <meta name="newtic-analytics-forget" content="{{ route('analytics.forget') }}">
  @if(request()->routeIs('product.show') && isset($product))
    <meta name="newtic-analytics-page" content="product">
    <meta name="newtic-analytics-product" content="{{ $product->id }}">
  @elseif(request()->routeIs('category.show') && isset($category))
    <meta name="newtic-analytics-page" content="category">
    <meta name="newtic-analytics-category" content="{{ $category->id }}">
  @elseif(request()->routeIs('search') && isset($products))
    <meta name="newtic-analytics-page" content="search">
    <meta name="newtic-analytics-search-count" content="{{ $products->total() }}">
  @endif
  <meta name="theme-color" content="#092037">
  <link rel="icon" type="image/webp" href="{{ asset($shopAppearance->compactLogo()) }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap">
  @php $seo = \App\Support\SeoMetadata::forRequest(request()); @endphp
  <meta name="robots" content="{{ $seo['robots'] }}">
  @if(request()->routeIs('checkout.success'))<meta name="referrer" content="no-referrer">@endif
  <title>{{ $seo['title'] }}</title>
  <meta name="description" content="{{ $seo['description'] }}">
  @if($seo['canonical'])
  <link rel="canonical" href="{{ $seo['canonical'] }}">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:type" content="{{ $seo['og_type'] }}">
  <meta property="og:title" content="{{ $seo['title'] }}">
  <meta property="og:description" content="{{ $seo['description'] }}">
  <meta property="og:url" content="{{ $seo['canonical'] }}">
  <meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
  <meta name="twitter:title" content="{{ $seo['title'] }}">
  <meta name="twitter:description" content="{{ $seo['description'] }}">
  @if($seo['image'])
  <meta property="og:image" content="{{ $seo['image'] }}">
  <meta name="twitter:image" content="{{ $seo['image'] }}">
  @endif
  @endif
  @if($seo['verification'])
  <meta name="google-site-verification" content="{{ $seo['verification'] }}">
  @endif
  @if($seo['structured'])
  <script type="application/ld+json">{!! json_encode($seo['structured'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
  @endif
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ request()->routeIs('home') ? 'is-home' : (request()->routeIs('product.show') ? 'is-product-detail' : (request()->routeIs('cart.index') ? 'is-cart-page' : (request()->routeIs('checkout.*') ? 'is-checkout' : (request()->routeIs('account.login','account.register') ? 'is-auth' : '')))) }}">
@include('store.partials.ui-icons')
@php
  $headerCartCount = \App\Support\ShoppingCart::itemCount();
  $storeName = \App\Support\StoreIdentity::name();
  $storeDescription = \App\Support\StoreIdentity::description();
  $megaMenuSettings = \App\Models\MegaMenuSetting::current();
  $shopAnnouncements = \App\Models\ShopAnnouncement::query()->visible()->orderBy('sort_order')->orderBy('id')->limit(30)->get();
  $activeCategorySlugs = request()->routeIs('category.show') && isset($breadcrumbs)
      ? array_map(fn ($crumb) => $crumb->slug, $breadcrumbs)
      : [];
  // Shared once by desktop navigation, mobile drawer and mobile category chips.
  $navigationCategories = \App\Models\Category::query()
      ->where('is_active', true)
      ->whereNull('parent_id')
      ->orderBy('sort_order')
      ->with('childrenRecursive')
      ->get();
@endphp
<a class="skip-link" href="#content">İçeriğe geç</a>
<header class="site-header desktop-site-header">
  <div class="container header-main">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $storeName }} ana sayfa">
      <img class="store-logo store-logo-desktop" src="{{ asset($shopAppearance->desktopLogo()) }}" alt="{{ $storeName }} logosu" width="176" height="44">
    </a>
    <form class="header-search" action="{{ route('search') }}" method="get" role="search" data-search-suggest-form data-suggest-url="{{ route('search.suggest') }}">
      <x-store-icon name="search" />
      <input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Ne aramıştınız?" aria-label="Ürün ara" autocomplete="off" aria-autocomplete="list" aria-expanded="false">
      <button type="submit" aria-label="Ürün ara" title="Ara"><x-store-icon name="search" /></button>
      <div class="search-suggest-results" data-suggest-results role="listbox" aria-label="Ürün önerileri" hidden></div>
    </form>
    <div class="header-actions">
      <a class="icon-link header-search-shortcut" href="{{ route('search') }}" aria-label="Ürün arama">
        <x-store-icon name="search" />
      </a>
      <a class="icon-link" href="{{ route('account.index') }}" aria-label="Hesabım">
        <x-store-icon name="user-round" />
      </a>
      <a class="icon-link header-cart-link" href="{{ route('cart.index') }}" aria-label="Sepetim, {{ $headerCartCount }} adet">
        <x-store-icon name="shopping-cart" />
        <span class="desktop-cart-label">Sepetim</span>
        @if($headerCartCount > 0)<span class="cart-icon-badge">{{ $headerCartCount }}</span>@endif
      </a>
    </div>
  </div>
</header>
<nav class="desktop-nav" aria-label="Ana gezinme">
    <div class="container nav-content">
      <a href="{{ route('home') }}">Ana sayfa</a>
      <a href="{{ route('blog.index') }}">Blog</a>
      @foreach($navigationCategories->take(7) as $navigationCategory)
        <a href="{{ route('category.show', $navigationCategory->slug) }}">{{ $navigationCategory->name }}</a>
      @endforeach
    </div>
</nav>

<header class="mobile-site-header" aria-label="Mobil mağaza üst menüsü">
  <div class="mobile-topbar">
    <details class="mobile-menu" data-mega-menu>
      <summary aria-label="Kategoriler menüsünü aç" aria-controls="store-mega-menu" data-mega-toggle><x-store-icon name="menu" /></summary>
      <div class="mobile-menu-panel mega-menu-shell" id="store-mega-menu" role="dialog" aria-label="{{ $storeName }} mağaza menüsü" aria-modal="true" data-mega-panel>
        <div class="mega-menu-top">
          <button type="button" class="mega-menu-close" data-mega-close aria-label="Menüyü kapat">
            <x-store-icon name="x" />
          </button>
          <a class="mega-menu-brand" href="{{ route('home') }}" aria-label="{{ $storeName }} ana sayfa">
            <img class="store-logo store-logo-mega mega-brand-logo" src="{{ asset($shopAppearance->desktopLogo()) }}" alt="{{ $storeName }} logosu" width="148" height="42">
          </a>
          <a href="{{ route('cart.index') }}" class="mega-cart-link" aria-label="Sepetim, {{ $headerCartCount }} adet">
            <x-store-icon name="shopping-cart" />
            @if($headerCartCount > 0)<span class="cart-icon-badge">{{ $headerCartCount }}</span>@endif
          </a>
        </div>
        <div class="mega-menu-scroll" data-mega-scroll>
          @if($megaMenuSettings->hero_image_path)
          <div class="mega-menu-banner">
            <img src="{{ asset($megaMenuSettings->hero_image_path) }}" alt="{{ $storeName }} menü görseli" width="1200" height="750" decoding="async">
          </div>
          @endif

          <div class="mega-section-title">
            <div><h3>Kategorileri keşfet</h3></div>
          </div>
          <nav class="mega-category-list" aria-label="Ürün kategorileri">
            @include('store.partials.category-menu-items', ['items' => $navigationCategories, 'level' => 0])
          </nav>
          <a class="mega-all-products" href="{{ route('blog.index') }}">
            <span class="mega-all-products-icon" aria-hidden="true">✳</span>
            Blog ve Rehber <span aria-hidden="true">↗</span>
          </a>
          <a class="mega-all-products" href="{{ route('search') }}">
            <span class="mega-all-products-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
            Tüm ürünler <span aria-hidden="true">↗</span>
          </a>
          @if($megaMenuSettings->instagram_url || $megaMenuSettings->youtube_url || $megaMenuSettings->facebook_url || $megaMenuSettings->x_url)
          <div class="mega-social-section">
            <div class="mega-social-head"><h3>Bizimle bağlantıda kal.</h3></div>
            <div class="mega-social-grid">
              @if($megaMenuSettings->instagram_url)
              <a href="{{ $megaMenuSettings->instagram_url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $storeName }} Instagram">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/></svg>
                <span>Instagram</span>
              </a>
              @endif
              @if($megaMenuSettings->youtube_url)
              <a href="{{ $megaMenuSettings->youtube_url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $storeName }} YouTube">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3Z"/></svg>
                <span>YouTube</span>
              </a>
              @endif
              @if($megaMenuSettings->facebook_url)
              <a href="{{ $megaMenuSettings->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $storeName }} Facebook">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 4h-2.2C11.6 4 11 5.3 11 7.2V10H8v4h3v7h4v-7h3l.5-4H15V7.5c0-.4.2-.5.7-.5H18V4Z"/></svg>
                <span>Facebook</span>
              </a>
              @endif
              @if($megaMenuSettings->x_url)
              <a href="{{ $megaMenuSettings->x_url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $storeName }} X">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h4l12 18h-4L4 3ZM20 3 4 21"/></svg>
                <span>X</span>
              </a>
              @endif
            </div>
          </div>
          @endif
          @if($megaMenuSettings->whatsappUrl())
          <a class="mega-whatsapp" href="{{ $megaMenuSettings->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp destek hattı">
            <span class="mega-whatsapp-symbol"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.8A9 9 0 0 1 8 20l-5 1 1.3-4.5A9 9 0 1 1 21 11.8Z"/><path d="M9 8c.5 4 3 6 7 7l1.5-1.6-2.7-1.5-1.3 1c-1.5-.6-2.5-1.6-3-3l1-1.3L10 6.5Z"/></svg></span>
            <span><strong>WhatsApp Destek</strong><small>Soruların için yanındayız</small></span>
            <svg class="mega-action-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
          </a>
          @endif
          @if(filled($megaMenuSettings->footer_text))
          <div class="mega-menu-footnote">{{ $megaMenuSettings->footer_text }}</div>
          @endif
        </div>
      </div>
    </details>
    <a class="mobile-center-brand" href="{{ route('home') }}" aria-label="{{ $storeName }} ana sayfa">
      @if($shopAppearance->mobile_logo_path || ! $shopAppearance->desktop_logo_path)
        <img class="store-logo store-logo-mobile" src="{{ asset($shopAppearance->mobileLogo()) }}" alt="{{ $storeName }} logosu" width="54" height="54">
      @else
        <img class="store-logo store-logo-mobile store-logo-mobile-from-desktop" src="{{ asset($shopAppearance->desktopLogo()) }}" alt="{{ $storeName }} logosu" width="90" height="42">
      @endif
    </a>
    <div class="mobile-top-actions">
      <a href="{{ route('account.index') }}" aria-label="Hesabım" title="Hesabım" class="mobile-account-link">
        <x-store-icon name="user-round" />
      </a>
      <a class="mobile-cart-link header-cart-link" href="{{ route('cart.index') }}" aria-label="Sepetim, {{ $headerCartCount }} adet">
        <x-store-icon name="shopping-cart" />
        @if($headerCartCount > 0)<span class="cart-icon-badge">{{ $headerCartCount }}</span>@endif
      </a>
    </div>
  </div>
</header>
@if(request()->routeIs('home', 'category.show', 'search'))
<div class="mobile-subnav">
  <form class="mobile-search-bar" action="{{ route('search') }}" method="get" role="search" data-search-suggest-form data-suggest-url="{{ route('search.suggest') }}">
    <input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Ürün, marka veya kategori ara" aria-label="Ürün ara" autocomplete="off" aria-autocomplete="list" aria-expanded="false">
    <button type="submit" aria-label="Ara"><x-store-icon name="search" /></button>
    <div class="search-suggest-results" data-suggest-results role="listbox" aria-label="Ürün önerileri" hidden></div>
  </form>
  <nav class="mobile-category-pills" aria-label="Hızlı kategoriler">
    <a href="{{ route('home') }}" class="mobile-pill-home {{ request()->routeIs('home') ? 'is-active' : '' }}" @if(request()->routeIs('home')) aria-current="page" @endif aria-label="Ana sayfa"><x-store-icon name="house" /></a>
    @foreach($navigationCategories as $pillCategory)
      <a href="{{ route('category.show', $pillCategory->slug) }}" class="mobile-pill {{ in_array($pillCategory->slug, $activeCategorySlugs, true) ? 'is-active' : '' }}" @if(request()->routeIs('category.show') && request()->route('slug') === $pillCategory->slug) aria-current="page" @endif>{{ $pillCategory->name }}</a>
    @endforeach
  </nav>
</div>
@endif
@if(request()->routeIs('category.show') && isset($siblingParent, $siblingCategories) && $siblingCategories->isNotEmpty())
  @include('store.partials.sticky-sibling-navigation')
@endif

@yield('content')
@include('store.partials.quick-cart-sheet')
@if(session('success'))
<div class="cart-add-toast cart-add-fallback-toast" role="status">
  <span class="cart-add-toast-icon" aria-hidden="true"><x-store-icon name="check" /></span>
  <span>{{ session('success') }}</span>
</div>
@endif

@php
  $merchantLegal = \App\Models\MerchantLegalProfile::current();
  $publishedLegalPages = \App\Models\MerchantLegalPage::publishedLinks();
  $merchantPaymentMarks = \App\Models\MerchantFooterMark::visible('payment');
  $merchantTrustMarks = \App\Models\MerchantFooterMark::visible('trust');
  $merchantAppMarks = \App\Models\MerchantFooterMark::visible('app');
  $livePaytr = !config('newtic.checkout_test_mode', true)
      && config('newtic.paytr_card_payments_enabled', false)
      && $merchantLegal->show_paytr_disclosure
      && \App\Models\PaytrSetting::query()->where('is_enabled', true)->first()?->hasCredentials();
@endphp
<footer class="site-footer legal-site-footer">
  <div class="container legal-footer-primary">
    <div class="legal-footer-brand-col">
      <a class="footer-brandmark" href="{{ route('home') }}" aria-label="{{ $storeName }} ana sayfa">
        <img class="store-logo store-logo-footer" src="{{ asset($shopAppearance->desktopLogo()) }}" alt="{{ $storeName }} logosu" width="170" height="43" loading="lazy">
      </a>
      <p>{{ $storeDescription }}</p>
    </div>

    <div class="legal-footer-col">
      <strong>Kurumsal</strong>
      @foreach(['hakkimizda','iletisim','islem-rehberi','uyelik-sozlesmesi'] as $slug)
        @if(isset($publishedLegalPages[$slug]))
          <a href="{{ route('legal.show',$slug) }}">{{ $publishedLegalPages[$slug]->title }}</a>
        @endif
      @endforeach
      <a href="{{ route('search') }}">Tüm ürünler</a>
      <a href="{{ route('blog.index') }}">Blog & Rehber</a>
    </div>

    <div class="legal-footer-col">
      <strong>Yardım Merkezi</strong>
      @foreach(['on-bilgilendirme-formu','mesafeli-satis-sozlesmesi','teslimat-ve-iade'] as $slug)
        @if(isset($publishedLegalPages[$slug]))
          <a href="{{ route('legal.show',$slug) }}">{{ $publishedLegalPages[$slug]->title }}</a>
        @endif
      @endforeach
      <a href="{{ route('account.index') }}">Siparişlerim</a>
    </div>

    <div class="legal-footer-col">
      <strong>Gizlilik & KVKK</strong>
      @foreach(['kvkk-aydinlatma','gizlilik-politikasi','cerez-politikasi'] as $slug)
        @if(isset($publishedLegalPages[$slug]))
          <a href="{{ route('legal.show',$slug) }}">{{ $publishedLegalPages[$slug]->title }}</a>
        @endif
      @endforeach
      <button class="legal-cookie-open" type="button" data-open-cookie-preferences>Çerez tercihleri</button>
    </div>

    <div class="legal-footer-col legal-footer-service">
      <strong>Müşteri Hizmetleri</strong>
      @if($merchantLegal->contact_phone)
        <a class="legal-footer-phone" href="tel:{{ preg_replace('/[^0-9+]/','',$merchantLegal->contact_phone) }}">{{ $merchantLegal->contact_phone }}</a>
      @endif
      @if($merchantLegal->contact_email)
        <a href="mailto:{{ $merchantLegal->contact_email }}">{{ $merchantLegal->contact_email }}</a>
      @endif
      @if($merchantLegal->support_hours)<small>{{ $merchantLegal->support_hours }}</small>@endif
      @if($merchantLegal->legal_name)<small>{{ $merchantLegal->legal_name }}</small>@endif
      @if($merchantLegal->registered_address)<small>{{ $merchantLegal->registered_address }}</small>@endif
      @if($merchantLegal->mersis_number)<small>MERSİS: {{ $merchantLegal->mersis_number }}</small>@endif
      @if($merchantLegal->kep_address)<small>KEP: {{ $merchantLegal->kep_address }}</small>@endif
    </div>
  </div>

  <div class="container legal-footer-secondary">
    <div class="legal-footer-trust">
      <strong>Ödeme Yöntemleri</strong>
      @if($livePaytr)
        <div class="legal-footer-brand-badges" aria-label="Kartlı ödeme">
          @forelse($merchantPaymentMarks as $mark)
            <span class="legal-footer-mark">
              @if($mark->verification_url)<a href="{{ $mark->verification_url }}" target="_blank" rel="noopener noreferrer">@endif
                <img src="{{ asset($mark->image_path) }}" alt="{{ $mark->title }}" width="112" height="42" loading="lazy">
              @if($mark->verification_url)</a>@endif
            </span>
          @empty
            <span>Güvenli kartlı ödeme</span>
          @endforelse
        </div>
        <p>Bu internet sitesindeki ödeme hizmetleri PAYTR Ödeme ve Elektronik Para Kuruluşu A.Ş. tarafından sağlanmaktadır.
          <a href="https://www.paytr.com/" target="_blank" rel="noopener noreferrer">PAYTR</a>
        </p>
      @else
        <p>Etkin ödeme seçenekleri siparişin ödeme adımında gösterilir.</p>
      @endif
    </div>
    <div class="legal-footer-trust">
      <strong>Alışveriş Bilgileri</strong>
      <p>Sepet, ödeme ve teslimat ayrıntılarını siparişi onaylamadan önce kontrol edebilirsiniz.</p>
      @if(config('newtic.checkout_test_mode',true))
        <p class="legal-footer-test">Test modu: Gerçek kart tahsilatı yapılmaz.</p>
      @endif
    </div>
    <div class="legal-footer-trust">
      @if($merchantTrustMarks->isNotEmpty())
        <strong>Doğrulanabilir işaretler</strong>
        <div class="legal-footer-brand-badges legal-footer-trust-marks">
          @foreach($merchantTrustMarks as $mark)
            <a class="legal-footer-mark" href="{{ $mark->verification_url }}" target="_blank" rel="noopener noreferrer">
              <img src="{{ asset($mark->image_path) }}" alt="{{ $mark->title }}" width="112" height="42" loading="lazy">
            </a>
          @endforeach
        </div>
      @else
        <strong>Bizi Takip Edin</strong>
      @endif
      <div class="legal-footer-social">
        @if($megaMenuSettings->instagram_url)<a href="{{ $megaMenuSettings->instagram_url }}" target="_blank" rel="noopener noreferrer">Instagram</a>@endif
        @if($megaMenuSettings->youtube_url)<a href="{{ $megaMenuSettings->youtube_url }}" target="_blank" rel="noopener noreferrer">YouTube</a>@endif
        @if($megaMenuSettings->facebook_url)<a href="{{ $megaMenuSettings->facebook_url }}" target="_blank" rel="noopener noreferrer">Facebook</a>@endif
        @if($megaMenuSettings->x_url)<a href="{{ $megaMenuSettings->x_url }}" target="_blank" rel="noopener noreferrer">X</a>@endif
      </div>
      @if($merchantAppMarks->isNotEmpty())
        <div class="legal-footer-app-links" aria-label="Resmi mobil uygulamalar">
          @foreach($merchantAppMarks as $mark)
            <a class="legal-footer-mark" href="{{ $mark->verification_url }}" target="_blank" rel="noopener noreferrer">
              <img src="{{ asset($mark->image_path) }}" alt="{{ $mark->title }}" width="125" height="44" loading="lazy">
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </div>
  <div class="container footer-bottom legal-footer-bottom">© {{ date('Y') }} {{ $storeName }} · Tüm hakları saklıdır · NEWTIC</div>
</footer>
@include('store.partials.cookie-preferences')

<nav class="mobile-bottom-nav" aria-label="Mobil gezinme">
  <a href="{{ route('home') }}"><x-store-icon name="house" /><span>Ana sayfa</span></a>
  <a href="{{ route('search') }}"><x-store-icon name="grid-2x2" /><span>Ürünler</span></a>
  <a href="{{ route('search') }}"><x-store-icon name="search" /><span>Ara</span></a>
  <a href="{{ route('cart.index') }}" class="mobile-nav-cart"><x-store-icon name="shopping-cart" /><span>Sepet @if($headerCartCount > 0)({{ $headerCartCount }})@endif</span></a>
</nav>
</body>
</html>
