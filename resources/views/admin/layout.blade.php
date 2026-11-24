<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="theme-color" content="#092037">
  <link rel="icon" type="image/webp" href="{{ asset(\App\Models\ShopAppearance::current()->compactLogo()) }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap">
  <title>@yield('title', 'NEWTIC Yönetim Paneli')</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.critical-styles')
</head>
@php
  $adminAppearance = \App\Models\ShopAppearance::current();
  $adminAuthenticated = auth()->check() && auth()->user()->is_admin;
  $pendingReviewCount = $adminAuthenticated
    ? \App\Models\ProductReview::query()->where('status', 'pending')->count()
    : 0;
  $pendingPaymentCount = $adminAuthenticated
    ? \App\Models\PaymentNotification::query()->where('status', 'pending')->count()
    : 0;
  $newticUpdateAvailable = $adminAuthenticated
    && app(\App\Services\NewticReleaseManager::class)->hasUpdate();
  $menuGroups = [
    ['label' => 'GENEL BAKIŞ', 'items' => [
      ['label' => 'Genel Bakış', 'name' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'home'],
    ]],
    ['label' => 'SATIŞ VE KATALOG', 'items' => [
      ['label' => 'Siparişler', 'name' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'bag'],
      ['label' => 'İptal ve İade Talepleri', 'name' => 'admin.returns.index', 'active' => 'admin.returns.*', 'icon' => 'bag'],
      ['label' => 'Ürünler', 'name' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'package'],
      ['label' => 'Kategoriler', 'name' => 'admin.categories.index', 'active' => 'admin.categories.*', 'icon' => 'grid'],
      ['label' => 'Stok Yönetimi', 'name' => 'admin.stock', 'active' => 'admin.stock', 'icon' => 'package'],
      ['label' => 'Ürün Yorumları', 'name' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'icon' => 'star', 'count' => $pendingReviewCount],
      ['label' => 'Ürün Rozetleri', 'name' => 'admin.product-badges.index', 'active' => 'admin.product-badges.*', 'icon' => 'star'],
      ['label' => 'Ürün Aktarımı', 'name' => 'admin.product-imports.index', 'active' => 'admin.product-imports.*', 'icon' => 'layers'],
      ['label' => 'Ödeme Bildirimleri', 'name' => 'admin.payment-notifications.index', 'active' => 'admin.payment-notifications.*', 'icon' => 'landmark', 'count' => $pendingPaymentCount],
      ['label' => 'Banka Hesapları', 'name' => 'admin.bank-accounts.index', 'active' => 'admin.bank-accounts.*', 'icon' => 'layers'],
    ]],
    ['label' => 'PAZARLAMA VE RAPORLAMA', 'items' => [
      ['label' => 'Pazarlama ve Analitik', 'name' => 'admin.analytics', 'active' => 'admin.analytics*', 'icon' => 'chart'],
      ['label' => 'Raporlar', 'name' => 'admin.reports', 'active' => 'admin.reports', 'icon' => 'chart'],
      ['label' => 'E-posta ve Bildirimler', 'name' => 'admin.email-center.index', 'active' => 'admin.email-center.*', 'icon' => 'mail'],
      ['label' => 'Kampanya E-postaları', 'name' => 'admin.marketing.index', 'active' => 'admin.marketing.*', 'icon' => 'mail'],
      ['label' => 'SEO Ayarları', 'name' => 'admin.seo.edit', 'active' => 'admin.seo.*', 'icon' => 'search'],
      ['label' => '301 Yönlendirmeler', 'name' => 'admin.redirects.index', 'active' => 'admin.redirects.*', 'icon' => 'arrow-right'],
    ]],
    ['label' => 'MAĞAZA GÖRÜNÜMÜ', 'items' => [
      ['label' => 'Vitrin · Slider', 'name' => 'admin.index', 'active' => 'admin.index', 'icon' => 'layout'],
      ['label' => 'Menüler', 'name' => 'admin.menus.mega-menu.edit', 'active' => 'admin.menus.*', 'icon' => 'menu'],
      ['label' => 'Blog & Rehber', 'name' => 'admin.blog.posts.index', 'active' => 'admin.blog.*', 'icon' => 'layers'],
      ['label' => 'Duyurular', 'name' => 'admin.announcements.index', 'active' => 'admin.announcements.*', 'icon' => 'mail'],
      ['label' => 'Logolar ve Görünüm', 'name' => 'admin.appearance.edit', 'active' => 'admin.appearance.*', 'icon' => 'image'],
    ]],
    ['label' => 'ENTEGRASYONLAR', 'items' => [
      ['label' => 'PAYTR Ödeme Ayarları', 'name' => 'admin.paytr-settings.edit', 'active' => 'admin.paytr-settings.*', 'icon' => 'settings'],
      ['label' => 'Google Merchant', 'name' => 'admin.google-merchant.edit', 'active' => 'admin.google-merchant.*', 'icon' => 'globe'],
      ['label' => 'Kargo Entegrasyonu', 'name' => 'admin.shipping-integrations.index', 'active' => 'admin.shipping-integrations.*', 'icon' => 'truck'],
      ['label' => 'Kargo Operasyonları', 'name' => 'admin.shipping-operations.index', 'active' => 'admin.shipping-operations.*', 'icon' => 'package'],
      ['label' => 'Kargo Ayarları', 'name' => 'admin.store-settings.edit', 'active' => 'admin.store-settings.*', 'icon' => 'truck'],
    ]],
    ['label' => 'SİSTEM YÖNETİMİ', 'items' => [
      ['label' => 'Kullanıcılar ve Müşteriler', 'name' => 'admin.customers', 'active' => 'admin.customers', 'icon' => 'users'],
      ['label' => 'Sistem Sağlığı', 'name' => 'admin.system-health', 'active' => 'admin.system-health', 'icon' => 'chart'],
      ['label' => 'Yazılım Güncellemeleri', 'name' => 'admin.software-updates.index', 'active' => 'admin.software-updates.*', 'icon' => 'refresh-cw', 'count' => $newticUpdateAvailable ? 1 : 0],
      ['label' => 'Güvenlik Kontrolü', 'name' => 'admin.security', 'active' => 'admin.security', 'icon' => 'shield', 'fragment' => '#guvenlik'],
      ['label' => 'Kullanıcı Girişi · Google', 'name' => 'admin.google-login.edit', 'active' => 'admin.google-login.*', 'icon' => 'users'],
      ['label' => 'Yasal Sayfalar & KVKK', 'name' => 'admin.legal.index', 'active' => 'admin.legal.*', 'icon' => 'shield'],
    ]],
  ];
  $activeGroupLabel = 'Mağazanın kontrol merkezi';
  foreach ($menuGroups as $group) {
    foreach ($group['items'] as $link) {
      if (request()->routeIs($link['active'])
          || ($link['name'] === 'admin.index' && request()->routeIs('admin.sections.*', 'admin.slides.*'))) {
        $activeGroupLabel = mb_convert_case(mb_strtolower($group['label']), MB_CASE_TITLE, 'UTF-8');
        break 2;
      }
    }
  }
@endphp
<body class="admin-body {{ $adminAuthenticated ? 'admin-app-active' : 'admin-auth-only' }}">
@if($adminAuthenticated)
<div class="admin-app-shell">
  <div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop hidden></div>
  <aside class="admin-sidebar" id="admin-sidebar" aria-label="Yönetim menüsü" data-admin-sidebar>
    <a href="{{ route('admin.dashboard') }}" class="admin-sidebar-brand" aria-label="NEWTIC Genel Bakış">
      <span class="admin-sidebar-logo-surface"><img class="admin-sidebar-brand-logo" src="{{ asset($adminAppearance->desktopLogo()) }}" alt="NEWTIC" width="150" height="38"></span>
      <span class="admin-sidebar-brand-caption">Yönetim Paneli</span>
    </a>
    <div class="admin-sidebar-scroll">
      <nav class="admin-sidebar-nav" aria-label="Ana yönetim">
        @foreach($menuGroups as $group)
          @php
            $groupActive = collect($group['items'])->contains(fn ($link) =>
              request()->routeIs($link['active'])
              || ($link['name'] === 'admin.index' && request()->routeIs('admin.sections.*', 'admin.slides.*'))
            );
          @endphp
          <details class="admin-nav-section" name="admin-menu-groups" @if($groupActive) open @endif>
            <summary><span>{{ $group['label'] }}</span><x-admin-icon name="arrow-right" class="admin-nav-group-arrow" /></summary>
            <div class="admin-nav-section-items">
              @foreach($group['items'] as $item)
                @php $active = request()->routeIs($item['active'])
                  || ($item['name'] === 'admin.index' && request()->routeIs('admin.sections.*', 'admin.slides.*')); @endphp
                <a href="{{ route($item['name']).($item['fragment'] ?? '') }}"
                  @class(['admin-side-link', 'is-active' => $active])
                  @if($active) aria-current="page" @endif>
                  <x-admin-icon :name="$item['icon']" />
                  <span>{{ $item['label'] }}</span>
                  @if(($item['count'] ?? 0) > 0)<strong class="admin-side-count">{{ $item['count'] }}</strong>@endif
                </a>
              @endforeach
            </div>
          </details>
        @endforeach
      </nav>
    </div>
    <div class="admin-sidebar-bottom">
      <div class="admin-sidebar-user">
        <span class="admin-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <div><strong>{{ auth()->user()->name }}</strong><small>Yönetici</small></div>
      </div>
      <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="admin-sidebar-logout"><x-admin-icon name="logout" /> Güvenli çıkış</button>
      </form>
    </div>
  </aside>
  <div class="admin-workspace">
    <header class="admin-topbar">
      <div class="admin-topbar-inner">
        <button class="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Menüyü aç" data-admin-menu-toggle><x-admin-icon name="menu" /></button>
        <img class="admin-topbar-brandmark" src="{{ asset($adminAppearance->compactLogo()) }}" alt="" width="30" height="30">
        <div class="admin-topbar-heading"><strong>@yield('topbar', 'NEWTIC Yönetim')</strong><small>{{ $activeGroupLabel }}</small></div>
        <form class="admin-global-search" action="{{ route('admin.search') }}" method="GET" role="search">
          <x-admin-icon name="search" />
          <input type="search" name="q" minlength="2" maxlength="100" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" placeholder="Sipariş, ürün veya müşteri ara..." aria-label="Sipariş, ürün veya müşteri ara" required>
          <button type="submit" aria-label="Ara"><x-admin-icon name="arrow-right" /></button>
        </form>
        <div class="admin-top-actions">
          <a class="admin-top-icon-link" href="{{ route('admin.payment-notifications.index') }}" aria-label="Ödeme bildirimleri{{ $pendingPaymentCount ? ' ('.$pendingPaymentCount.' bekliyor)' : '' }}" title="Ödeme bildirimleri">
            <x-admin-icon name="bell" />
            @if($pendingPaymentCount)<span class="admin-top-badge">{{ $pendingPaymentCount }}</span>@endif
          </a>
          <a class="admin-top-icon-link" href="{{ route('home') }}" target="_blank" rel="noopener noreferrer" aria-label="Mağazayı yeni sekmede aç" title="Mağazayı aç"><x-admin-icon name="external-link" /></a>
          <span class="admin-top-avatar" title="{{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        </div>
      </div>
    </header>
    <main class="admin-main" id="admin-content">
      @if(session('success'))<div class="admin-alert admin-alert-success" role="status"><x-admin-icon name="check" /> {{ session('success') }}</div>@endif
      @if($errors->any())
        <div class="admin-alert admin-alert-error" role="alert">
          <x-admin-icon name="alert" /><div><strong>Kaydedilemedi:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
      @endif
      @yield('content')
    </main>
    <footer class="admin-footer"><img class="admin-footer-brandmark" src="{{ asset($adminAppearance->compactLogo()) }}" width="22" height="22" alt=""> NEWTIC · {{ \App\Support\StoreIdentity::name() }} · Yönetim Paneli</footer>
  </div>
</div>
@else
  <main class="admin-main admin-login-main">
    @if($errors->any())<div class="admin-alert admin-alert-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @yield('content')
  </main>
@endif
</body>
</html>