<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#102f3e">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>@yield('title', 'Polaris Kurşun | Balıkçılık Ekipmanları')</title>
  <meta name="description" content="@yield('description', 'Polaris Kurşun balıkçılık ekipmanları. Yeni mağaza tasarımı hazırlanmaktadır.')">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ request()->routeIs('home') ? 'is-home' : (request()->routeIs('product.show') ? 'is-product-detail' : '') }}">
@php
  // Shared once by desktop navigation, mobile drawer and mobile category chips.
  $navigationCategories = \App\Models\Category::query()
      ->where('is_active', true)
      ->orderBy('sort_order')
      ->get();
@endphp
<a class="skip-link" href="#content">İçeriğe geç</a>
<header class="site-header desktop-site-header">
  <div class="utility-bar"><div class="container utility-content">
    <span>Balıkçılığa tutkuyla bağlı olanlara</span>
    <span>POLARIS / TÜRKİYE</span>
  </div></div>
  <div class="container header-main">
    <a class="brand" href="{{ route('home') }}" aria-label="Polaris Kurşun ana sayfa">
      <span class="brand-symbol" aria-hidden="true"><svg viewBox="0 0 36 36" fill="none"><path d="M7 19c5-10 14-13 22-7-4 0-7 2-9 6 4-2 7-2 10 0-8 1-14 6-18 12-2-4-3-8-5-11Z" fill="currentColor"/><circle cx="25" cy="12" r="1.5" fill="#102f3e"/></svg></span>
      <span class="brand-words"><strong>POLARIS</strong><small>KURŞUN</small></span>
    </a>
    <form class="header-search" action="{{ route('search') }}" method="get" role="search">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
      <input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Ne aramıştınız?" aria-label="Ürün ara">
      <button type="submit">Ara</button>
    </form>
    <div class="header-actions">
      <a class="icon-link header-search-shortcut" href="{{ route('search') }}" aria-label="Ürün arama">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
      </a>
      <a class="icon-link" href="{{ route('home') }}" aria-label="Ana sayfa">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"/><path d="M9 21v-8h6v8"/></svg>
      </a>
      <span class="icon-link cart-coming-soon" aria-label="Sepet geliştirme aşamasında" title="Sepet geliştirme aşamasında">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.3a2 2 0 0 0 2 1.7h8.7a2 2 0 0 0 2-1.6L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="19" cy="21" r="1"/></svg>
      </span>
    </div>
  </div>
  <nav class="desktop-nav" aria-label="Ana gezinme">
    <div class="container nav-content">
      <a href="{{ route('home') }}">Ana sayfa</a>
      @foreach($navigationCategories->take(7) as $navigationCategory)
        <a href="{{ route('category.show', $navigationCategory->slug) }}">{{ $navigationCategory->name }}</a>
      @endforeach
    </div>
  </nav>
</header>

<header class="mobile-site-header" aria-label="Mobil mağaza üst menüsü">
  <div class="mobile-topbar">
    <details class="mobile-menu">
      <summary aria-label="Kategoriler menüsünü aç"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></summary>
      <nav class="mobile-menu-panel" aria-label="Ürün kategorileri">
        <div class="mobile-menu-heading">Kategorileri keşfet</div>
        <a href="{{ route('home') }}">Ana sayfa</a>
        @foreach($navigationCategories as $mobileMenuCategory)
          <a href="{{ route('category.show', $mobileMenuCategory->slug) }}">{{ $mobileMenuCategory->name }} <span aria-hidden="true">›</span></a>
        @endforeach
        <a href="{{ route('search') }}">Tüm ürünler <span aria-hidden="true">›</span></a>
      </nav>
    </details>
    <a class="mobile-center-brand" href="{{ route('home') }}" aria-label="Polaris Kurşun ana sayfa">
      <span class="mobile-brand-mark" aria-hidden="true"><svg viewBox="0 0 36 36" fill="none"><path d="M7 19c5-10 14-13 22-7-4 0-7 2-9 6 4-2 7-2 10 0-8 1-14 6-18 12-2-4-3-8-5-11Z" fill="currentColor"/><circle cx="25" cy="12" r="1.5" fill="#fff"/></svg></span>
      <span class="mobile-brand-name">POLARIS</span>
    </a>
    <div class="mobile-top-actions">
      <span aria-label="Hesabım, geliştirme aşamasında" title="Hesabım geliştirme aşamasında" class="mobile-icon-disabled">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2Z"/></svg>
      </span>
      <span aria-label="Sepet, geliştirme aşamasında" title="Sepet geliştirme aşamasında" class="mobile-icon-disabled">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M2 3h2l2.5 12h13L22 7H5"/></svg>
      </span>
    </div>
  </div>
  <form class="mobile-search-bar" action="{{ route('search') }}" method="get" role="search">
    <input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Kurşun, gramaj veya ürün ara" aria-label="Ürün ara">
    <button type="submit" aria-label="Ara"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/></svg></button>
  </form>
  <nav class="mobile-category-pills" aria-label="Hızlı kategoriler">
    <a href="{{ route('home') }}" class="mobile-pill-home" aria-label="Ana sayfa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10H3z"/><path d="M9 21v-8h6v8"/></svg></a>
    @foreach($navigationCategories as $pillCategory)
      <a href="{{ route('category.show', $pillCategory->slug) }}" class="mobile-pill">{{ $pillCategory->name }}</a>
    @endforeach
  </nav>
</header>

@yield('content')

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <strong class="footer-brand">POLARIS <span>KURŞUN</span></strong>
      <p>Balıkçılık tutkusuna eşlik eden, her detayı özenle düşünülmüş ekipmanlar.</p>
    </div>
    <div><strong>Keşfet</strong><a href="{{ route('home') }}">Ana sayfa</a><a href="{{ route('search') }}">Ürünler</a></div>
    <div><strong>Bilgilendirme</strong><p>Yeni e-ticaret mağazamız test aşamasındadır. Bu ortamda sipariş alınmamaktadır.</p></div>
  </div>
  <div class="container footer-bottom">© {{ date('Y') }} Polaris Kurşun · Yeni mağaza önizlemesi</div>
</footer>
<nav class="mobile-bottom-nav" aria-label="Mobil gezinme">
  <a href="{{ route('home') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"/><path d="M9 21v-8h6v8"/></svg><span>Ana sayfa</span></a>
  <a href="{{ route('search') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Ürünler</span></a>
  <a href="{{ route('search') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg><span>Ara</span></a>
  <span class="mobile-nav-disabled" aria-label="Sepet geliştirme aşamasında"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.3a2 2 0 0 0 2 1.7h8.7a2 2 0 0 0 2-1.6L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="19" cy="21" r="1"/></svg><span>Sepet</span></span>
</nav>
</body>
</html>
