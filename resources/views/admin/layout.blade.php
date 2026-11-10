<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="theme-color" content="#102f3a">
  <title>@yield('title', 'NEWTIC Yönetim Paneli')</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.critical-styles')
</head>
@php
  $adminAuthenticated = auth()->check() && auth()->user()->is_admin;
  $pendingReviewCount = $adminAuthenticated
    ? \App\Models\ProductReview::query()->where('status', 'pending')->count()
    : 0;
  $pendingPaymentCount = $adminAuthenticated
    ? \App\Models\PaymentNotification::query()->where('status', 'pending')->count()
    : 0;
  $menu = [
    ['label' => 'Genel Bakış', 'name' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'home'],
    ['label' => 'Siparişler', 'name' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'bag'],
    ['label' => 'Ürünler', 'name' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'package'],
    ['label' => 'Ürün Yorumları', 'name' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'icon' => 'star', 'count' => $pendingReviewCount],
    ['label' => 'Kategoriler', 'name' => 'admin.categories.index', 'active' => 'admin.categories.*', 'icon' => 'grid'],
    ['label' => 'Stok Yönetimi', 'name' => 'admin.stock', 'active' => 'admin.stock', 'icon' => 'package'],
    ['label' => 'Ödeme Bildirimleri', 'name' => 'admin.payment-notifications.index', 'active' => 'admin.payment-notifications.*', 'icon' => 'landmark', 'count' => $pendingPaymentCount],
    ['label' => 'Banka Hesapları', 'name' => 'admin.bank-accounts.index', 'active' => 'admin.bank-accounts.*', 'icon' => 'layers'],
    ['label' => 'Müşteriler', 'name' => 'admin.customers', 'active' => 'admin.customers', 'icon' => 'users'],
    ['label' => 'Raporlar', 'name' => 'admin.reports', 'active' => 'admin.reports', 'icon' => 'chart'],
    ['label' => 'Vitrin Yönetimi', 'name' => 'admin.index', 'active' => 'admin.index', 'icon' => 'layout'],
    ['label' => 'Duyurular', 'name' => 'admin.announcements.index', 'active' => 'admin.announcements.*', 'icon' => 'mail'],
    ['label' => 'Logolar', 'name' => 'admin.appearance.edit', 'active' => 'admin.appearance.*', 'icon' => 'image'],
    ['label' => 'Kargo Ayarları', 'name' => 'admin.store-settings.edit', 'active' => 'admin.store-settings.*', 'icon' => 'truck'],
  ];
@endphp
<body class="admin-body {{ $adminAuthenticated ? 'admin-app-active' : 'admin-auth-only' }}">
@if($adminAuthenticated)
<div class="admin-app-shell">
  <div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop hidden></div>
  <aside class="admin-sidebar" id="admin-sidebar" aria-label="Yönetim menüsü" data-admin-sidebar>
    <a href="{{ route('admin.dashboard') }}" class="admin-sidebar-brand" aria-label="NEWTIC Genel Bakış">
      <span class="admin-sidebar-symbol"><x-admin-icon name="bag" /></span>
      <span>NEWTIC <small>Yönetim Paneli</small></span>
    </a>
    <div class="admin-sidebar-scroll">
      <div class="admin-menu-caption">ANA MENÜ</div>
      <nav class="admin-sidebar-nav" aria-label="Ana yönetim">
        @foreach($menu as $item)
          @if($loop->index === 11)<div class="admin-menu-caption admin-menu-section">MAĞAZA AYARLARI</div>@endif
          <a href="{{ route($item['name']) }}"
            @class(['admin-side-link', 'is-active' => request()->routeIs($item['active']) || ($item['name'] === 'admin.index' && request()->routeIs('admin.sections.*', 'admin.slides.*'))])
            @if(request()->routeIs($item['active'])) aria-current="page" @endif>
            <x-admin-icon :name="$item['icon']" />
            <span>{{ $item['label'] }}</span>
            @if(($item['count'] ?? 0) > 0)<strong class="admin-side-count">{{ $item['count'] }}</strong>@endif
          </a>
        @endforeach
      </nav>
    </div>
    <div class="admin-sidebar-bottom">
      <div class="admin-help-card">
        <span class="admin-help-icon"><x-admin-icon name="info" /></span>
        <strong>Hızlı erişim</strong>
        <p>Ödeme bildirimi ve yeni siparişleri tek ekranda incele.</p>
        <a href="{{ route('admin.dashboard') }}">Genel Bakış <x-admin-icon name="arrow-right" /></a>
      </div>
      <div class="admin-sidebar-user">
        <span class="admin-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <div><strong>{{ auth()->user()->name }}</strong><small>Yönetici</small></div>
      </div>
    </div>
  </aside>
  <div class="admin-workspace">
    <header class="admin-topbar">
      <div class="admin-topbar-inner">
        <button class="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Menüyü aç" data-admin-menu-toggle><x-admin-icon name="menu" /></button>
        <div class="admin-topbar-heading"><strong>@yield('topbar', 'NEWTIC Yönetim')</strong><small>Mağazanın kontrol merkezi</small></div>
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
    <footer class="admin-footer">NEWTIC · Polaris Kurşun · Yönetim Paneli</footer>
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