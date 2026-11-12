@php
  $authParams = array_filter(['next' => $authNext, 'favorite' => $authNext === 'cart' ? $favoriteId : '', 'review_product' => $authNext === 'review' ? $reviewProduct : '']);
  // The child auth view renders before the parent layout initializes its
  // own variables, so resolve the current logo here independently.
  $authAppearance = \App\Models\ShopAppearance::current();
@endphp
<header class="mobile-auth-header" aria-label="Üyelik ekranı üst menüsü">
  <a href="{{ route('home') }}" class="mobile-auth-brand" aria-label="Polaris Kurşun ana sayfa">
    @if($authAppearance->mobile_logo_path)
      <img src="{{ asset($authAppearance->mobile_logo_path) }}" alt="Polaris Kurşun" width="156" height="48">
    @elseif($authAppearance->desktop_logo_path)
      <img src="{{ asset($authAppearance->desktop_logo_path) }}" alt="Polaris Kurşun" width="156" height="48">
    @else
      <span class="mobile-auth-fallback">
        <svg viewBox="0 0 36 36" aria-hidden="true"><path d="M7 19c5-10 14-13 22-7-4 0-7 2-9 6 4-2 7-2 10 0-8 1-14 6-18 12-2-4-3-8-5-11Z" fill="currentColor"/></svg>
        <span><strong>POLARIS</strong><small>KURŞUN</small></span>
      </span>
    @endif
  </a>
  <a href="{{ route('home') }}" class="mobile-auth-close" aria-label="Üyelik ekranından çık"><x-store-icon name="x" /></a>
</header>
<nav class="mobile-auth-tabs" aria-label="Giriş ve üyelik seçimi">
  <a href="{{ route('account.login', $authParams) }}" @class(['is-active' => request()->routeIs('account.login')])
    @if(request()->routeIs('account.login')) aria-current="page" @endif>Giriş Yap</a>
  <a href="{{ route('account.register', $authParams) }}" @class(['is-active' => request()->routeIs('account.register')])
    @if(request()->routeIs('account.register')) aria-current="page" @endif>Üye Ol</a>
</nav>
