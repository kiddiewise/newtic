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
<body>
<a class="skip-link" href="#content">İçeriğe geç</a>
<header class="site-header">
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
      @foreach(\App\Models\Category::query()->where('is_active', true)->orderBy('sort_order')->limit(7)->get() as $navigationCategory)
        <a href="{{ route('category.show', $navigationCategory->slug) }}">{{ $navigationCategory->name }}</a>
      @endforeach
    </div>
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
